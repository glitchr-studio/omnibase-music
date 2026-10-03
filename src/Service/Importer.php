<?php

namespace Base\Music\Service;

use Base\Music\Entity\Label;
use Base\Music\Entity\Release;
use Base\Music\Entity\Track;
use Base\Music\Enum\ReleaseType;
use Base\Music\Repository\LabelRepository;
use Base\Music\Repository\ReleaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Omnisong\Catalog\Catalog;
use Omnisong\Exception\OmnisongException;
use Omnisong\Model\Label as CatalogueLabel;
use Omnisong\Model\PlatformLinks;
use Omnisong\Model\Reference;
use Omnisong\Model\Release as CatalogueRelease;
use Omnisong\Model\Track as CatalogueTrack;
use Omnisong\Platform;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Completes a release from what the catalogues know (Omnisong: Odesli for
 * the links, iTunes for the tracks and their 30 s previews): one URL or one
 * UPC, and the links to every platform, the UPC, the cover, the date, the
 * label and the track list come in.
 *
 * It only ever fills what is empty: a field set by hand is never
 * overwritten, a track is matched by its ISRC, else by its disc and place,
 * and gets what it lacks. The label's own links are not taken from a
 * catalogue: they come from the Label the release is given.
 *
 * Nothing is flushed here. A catalogue that is down is not "not found":
 * the report says which did not answer (ImportReport::$incomplete).
 */
class Importer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LabelRepository $labels,
        private readonly ReleaseRepository $releases,
        private readonly ?Catalog $catalog = null,
        #[Autowire('%music.country%')] private readonly ?string $country = 'DE',
    ) {
    }

    /** False when Omnisong's bundle is not registered: there is no catalogue to ask. */
    public function isAvailable(): bool
    {
        return null !== $this->catalog;
    }

    /** What a loose string names (a URL, a UPC/EAN, an ISRC), for the site's country. */
    public function reference(string $value): Reference
    {
        return Reference::parse($value, $this->country);
    }

    /** What names a release for the catalogues: its UPC, else the first platform link it has. */
    public function referenceOf(Release $release): ?Reference
    {
        if ($release->getUpc()) {
            return Reference::upc($release->getUpc(), $this->country);
        }
        foreach ($release->getLinks() as $platform => $url) {
            if (!\in_array($platform, [Platform::LABEL->value, Platform::SHOP->value, Platform::OTHER->value], true)) {
                return Reference::url($url, $this->country);
            }
        }

        return null;
    }

    /**
     * Fills the release's empty fields from the catalogues.
     *
     * @throws OmnisongException         what a catalogue throws beyond being down (a bad configuration...)
     * @throws \InvalidArgumentException when the release has neither a UPC nor a link and no reference is given
     */
    public function complete(Release $release, ?Reference $reference = null): ImportReport
    {
        $catalog = $this->catalog ?? throw new \LogicException('Omnisong\'s bundle is not registered: no catalogue to ask.');
        $reference ??= $this->referenceOf($release)
            ?? throw new \InvalidArgumentException('A release needs a UPC or one platform link before the catalogues can be asked.');

        $label = $this->catalogueLabel($release);
        $found = $catalog->release($reference, $label);
        $incomplete = $catalog->incomplete;
        if (null === $found) {
            // No catalogue knows the release itself: the links alone are still worth having.
            $links = $catalog->links($reference, $label);
            $incomplete = array_values(array_unique([...$incomplete, ...$catalog->incomplete]));
            $filled = $this->fillLinks($release, $links);

            return new ImportReport([] !== $filled, $filled, 0, 0, $incomplete);
        }

        return $this->apply($release, $found, $incomplete);
    }

    /**
     * One URL or UPC to a release of the site: the one that already has
     * this UPC, completed, or a new draft. Null when no catalogue knows it
     * ($report says whether one was down). The new release is persisted,
     * not flushed.
     */
    public function import(Reference $reference, ?ImportReport &$report = null): ?Release
    {
        $catalog = $this->catalog ?? throw new \LogicException('Omnisong\'s bundle is not registered: no catalogue to ask.');
        $found = $catalog->release($reference);
        $incomplete = $catalog->incomplete;
        if (null === $found) {
            $report = new ImportReport(false, [], 0, 0, $incomplete);

            return null;
        }
        $upc = $found->upc ?? $reference->upc;
        $release = $upc ? $this->releases->findOneBy(['upc' => $upc]) : null;
        if (null === $release) {
            $release = new Release();
            $release->setTitle($found->title);
            $release->setType(ReleaseType::fromCatalogue($found->type));
            $this->entityManager->persist($release);
        }
        $report = $this->apply($release, $found, $incomplete);

        return $release;
    }

    /**
     * What a catalogue found, onto the release: only the nulls are filled.
     *
     * @param list<string> $incomplete
     */
    public function apply(Release $release, CatalogueRelease $found, array $incomplete = []): ImportReport
    {
        $filled = [];
        if (!$release->getTitle() && '' !== $found->title) {
            $release->setTitle($found->title);
            $filled[] = 'title';
        }
        if (null === $release->getUpc() && $found->upc) {
            $release->setUpc($found->upc);
            $filled[] = 'upc';
        }
        if (null === $release->getReleasedAt() && $found->releasedAt) {
            $release->setReleasedAt(\DateTime::createFromImmutable($found->releasedAt));
            $filled[] = 'releasedAt';
        }
        if (!$release->hasCover() && null === $release->getCoverUrl() && $found->coverUrl) {
            $release->setCoverUrl($found->coverUrl);
            $filled[] = 'coverUrl';
        }
        if (null === $release->getLabel() && $found->label?->name) {
            $release->setLabel($this->label($found->label));
            $filled[] = 'label';
        }
        $filled = [...$filled, ...$this->fillLinks($release, $found->links)];

        [$created, $updated] = $this->fillTracks($release, $found->tracks);

        return new ImportReport(true, $filled, $created, $updated, $incomplete);
    }

    /** @return list<string> "links.spotify", "links.qobuz"... the platforms that had no link and have one now */
    private function fillLinks(Release $release, ?PlatformLinks $links): array
    {
        $filled = [];
        $own = $release->getLinks();
        foreach ($links ?? [] as $link) {
            // The label's and its shop's come from the Label entity (and labelUrl), never from a catalogue.
            if (\in_array($link->platform, [Platform::LABEL, Platform::SHOP], true) || isset($own[$link->platform->value])) {
                continue;
            }
            $own[$link->platform->value] = $link->url;
            $filled[] = 'links.'.$link->platform->value;
        }
        if ($filled) {
            $release->setLinks($own);
        }

        return $filled;
    }

    /**
     * @param list<CatalogueTrack> $tracks
     *
     * @return array{0: int, 1: int} created, updated
     */
    private function fillTracks(Release $release, array $tracks): array
    {
        $byIsrc = $byPlace = [];
        foreach ($release->getTracks() as $track) {
            if ($track->getIsrc()) {
                $byIsrc[$track->getIsrc()] = $track;
            }
            if (null !== $track->getPosition()) {
                $byPlace[$track->getDisc().'.'.$track->getPosition()] = $track;
            }
        }

        $created = $updated = 0;
        $matched = [];
        foreach ($tracks as $index => $found) {
            $disc = $found->disc ?? 1;
            $position = $found->position ?? $index + 1;
            $isrc = $found->isrc ? strtoupper($found->isrc) : null;
            $track = $isrc ? $byIsrc[$isrc] ?? null : null;
            if (null === $track) {
                // At its place, unless the track there is another recording (another ISRC) or was matched already.
                $atPlace = $byPlace[$disc.'.'.$position] ?? null;
                $track = $atPlace && !isset($matched[spl_object_id($atPlace)]) && (null === $isrc || null === $atPlace->getIsrc() || $atPlace->getIsrc() === $isrc) ? $atPlace : null;
            }
            if (null !== $track) {
                $matched[spl_object_id($track)] = true;
            }
            if (null === $track) {
                $track = (new Track())->setDisc($disc)->setPosition($position)->setTitle($found->title)
                    ->setDuration($found->duration)->setIsrc($isrc)->setPreviewUrl($found->previewUrl);
                $release->addTrack($track);
                $matched[spl_object_id($track)] = true;
                $byPlace[$disc.'.'.$position] ??= $track;
                ++$created;
                continue;
            }
            $changed = false;
            if (null === $track->getTitle() && null === $track->getWork() && '' !== $found->title) {
                $track->setTitle($found->title);
                $changed = true;
            }
            if (null === $track->getDuration() && $found->duration) {
                $track->setDuration($found->duration);
                $changed = true;
            }
            if (null === $track->getIsrc() && $isrc) {
                $track->setIsrc($isrc);
                $changed = true;
            }
            if (null === $track->getPreviewUrl() && $found->previewUrl) {
                $track->setPreviewUrl($found->previewUrl);
                $changed = true;
            }
            $updated += $changed ? 1 : 0;
        }

        return [$created, $updated];
    }

    /** The label of the site a catalogue's label is, made when the site did not know it. */
    private function label(CatalogueLabel $found): Label
    {
        $label = $this->labels->findOneByName($found->name);
        if (null === $label) {
            $label = (new Label())->setName($found->name)->setUrl($found->url)->setShopUrl($found->shopUrl);
            $this->entityManager->persist($label);
        }

        return $label;
    }

    private function catalogueLabel(Release $release): ?CatalogueLabel
    {
        $label = $release->getLabel();

        return $label ? new CatalogueLabel((string) $label->getName(), $release->getLabelUrl() ?? $label->releaseUrl($release) ?? $label->getUrl(), $label->getShopUrl()) : null;
    }
}
