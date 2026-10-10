<?php

namespace Base\Music\Entity;

use Base\Database\Attribute\DiscriminatorEntry;
use Base\Database\Attribute\Uploader;
use Base\Entity\Thread;
use Base\Enum\ThreadState;
use Base\Music\Enum\ReleaseType;
use Base\Music\Repository\ReleaseRepository;
use Base\Service\Model\LinkableInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Omnisong\Model\Label as CatalogueLabel;
use Omnisong\Model\PlatformLink;
use Omnisong\Model\PlatformLinks;
use Omnisong\Platform;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * An album, a single, an EP: what the musician recorded. An omnibase
 * thread - title, headline, excerpt and text translated (the text is the
 * liner notes), slug, publication state and date - written in the back
 * office (Controller\Admin\Crud\ReleaseCrudController), with on top what a
 * record is: its label and its number there, its UPC, its date, its cover,
 * its tracks, who plays on it, the prizes it won, and where it is listened
 * to or bought - the label first (getPlatformLinks()).
 */
#[ORM\Entity(repositoryClass: ReleaseRepository::class)]
#[ORM\Table(name: 'music_release')]
#[DiscriminatorEntry(value: 'music_release')]
class Release extends Thread implements LinkableInterface
{
    public static function __iconizeStatic(): ?array
    {
        return ['fa-solid fa-record-vinyl'];
    }

    public function __toLink(array $routeParameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        return $this->getRouter()->generate('music_release', array_merge($routeParameters, ['slug' => $this->getSlug()]), $referenceType);
    }

    #[ORM\Column(type: 'string', length: 16, enumType: ReleaseType::class)]
    protected ReleaseType $type = ReleaseType::ALBUM;

    /** The house it came out on: shown first. */
    #[ORM\ManyToOne(targetEntity: Label::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    protected ?Label $label = null;

    /** This release's page at the label; set, it wins over the label's pattern. */
    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $labelUrl = null;

    /** The label's catalogue number: "ES 2084". */
    #[ORM\Column(length: 64, nullable: true)]
    protected ?string $catalogue = null;

    /** UPC/EAN: what names the release on every platform. */
    #[ORM\Column(length: 14, nullable: true)]
    protected ?string $upc = null;

    #[ORM\Column(type: 'date', nullable: true)]
    protected ?\DateTimeInterface $releasedAt = null;

    /** Announced, not out yet: the page says when, and offers the pre-save. */
    #[ORM\Column(type: 'boolean')]
    protected bool $upcoming = false;

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $presaveUrl = null;

    /** The sleeve: an upload. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Uploader(max_size: '16MB', mime_types: ['image/*'])]
    protected $cover = null;

    /** The sleeve as a catalogue gave it (a remote URL), shown as long as none is uploaded. */
    #[ORM\Column(length: 500, nullable: true)]
    protected ?string $coverUrl = null;

    /** @var array<string, string>|null Omnisong\Platform value => URL */
    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $links = null;

    /** One per line: "CD of the year — Radio România Muzical". */
    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $awards = null;

    /** @var Collection<int, Performer> who plays on it, in the performers' own order */
    #[ORM\ManyToMany(targetEntity: Performer::class)]
    #[ORM\JoinTable(name: 'music_release_performer')]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    protected Collection $performers;

    /** @var Collection<int, Track> */
    #[ORM\OneToMany(targetEntity: Track::class, mappedBy: 'release', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['disc' => 'ASC', 'position' => 'ASC'])]
    protected Collection $tracks;

    /** Shown first and larger on /music, whatever its date. */
    #[ORM\Column(type: 'boolean')]
    protected bool $featured = false;

    public function __construct(?\Base\Entity\User $owner = null, ?Thread $parent = null, ?string $title = null, ?string $slug = null)
    {
        parent::__construct($owner, $parent, $title, $slug);
        $this->performers = new ArrayCollection();
        $this->tracks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getTitle() ?? '';
    }

    public function getType(): ReleaseType { return $this->type; }
    public function setType(ReleaseType $type): self { $this->type = $type; return $this; }

    public function getLabel(): ?Label { return $this->label; }
    public function setLabel(?Label $label): self { $this->label = $label; return $this; }

    public function getLabelUrl(): ?string { return $this->labelUrl; }
    public function setLabelUrl(?string $labelUrl): self { $this->labelUrl = $labelUrl ?: null; return $this; }

    public function getCatalogue(): ?string { return $this->catalogue; }
    public function setCatalogue(?string $catalogue): self { $this->catalogue = $catalogue ?: null; return $this; }

    public function getUpc(): ?string { return $this->upc; }
    public function setUpc(?string $upc): self { $this->upc = $upc ? (preg_replace('/\D/', '', $upc) ?: null) : null; return $this; }

    public function getReleasedAt(): ?\DateTimeInterface { return $this->releasedAt; }
    public function setReleasedAt(?\DateTimeInterface $releasedAt): self { $this->releasedAt = $releasedAt; return $this; }

    public function isUpcoming(): bool { return $this->upcoming; }
    public function setUpcoming(bool $upcoming): self { $this->upcoming = $upcoming; return $this; }

    public function getPresaveUrl(): ?string { return $this->presaveUrl; }
    public function setPresaveUrl(?string $presaveUrl): self { $this->presaveUrl = $presaveUrl ?: null; return $this; }

    public function getCover(): ?string { return Uploader::getPublic($this, 'cover'); }
    public function getCoverFile(): ?File { return Uploader::get($this, 'cover'); }
    public function setCover($cover): self { $this->cover = $cover; return $this; }
    public function hasCover(): bool { return null !== $this->cover && '' !== $this->cover && [] !== $this->cover; }

    public function getCoverUrl(): ?string { return $this->coverUrl; }
    public function setCoverUrl(?string $coverUrl): self { $this->coverUrl = $coverUrl ?: null; return $this; }

    /**
     * The sleeve to show, by its address: the upload's on the site ("/uploads/..."), else the
     * catalogue's. The upload's own path is the file's on disk (/srv/app/public/uploads/...): the
     * pages printed it as an image's src, and no browser could load it.
     */
    public function getArtwork(): ?string
    {
        if ($this->hasCover() && null !== ($path = $this->getCover()) && '' !== $path) {
            $public = strpos($path, '/public/');

            return false !== $public ? substr($path, $public + \strlen('/public')) : $path;
        }

        return $this->coverUrl;
    }

    /** @return array<string, string> platform value => URL, as typed in the back office */
    public function getLinks(): array { return $this->links ?? []; }

    /** @param array<string, ?string>|null $links */
    public function setLinks(?array $links): self
    {
        $links = array_filter($links ?? [], static fn ($url) => \is_string($url) && '' !== trim($url));
        $this->links = $links ? array_map('trim', $links) : null;

        return $this;
    }

    public function setLink(Platform $platform, ?string $url): self
    {
        return $this->setLinks([$platform->value => $url] + $this->getLinks());
    }

    /**
     * Where it is, in the order the site shows it: the label first - at
     * this release's own page there (labelUrl), else the one the label's
     * pattern gives, else the label's site - then its shop, then the
     * platforms by Platform::rank().
     */
    public function getPlatformLinks(): PlatformLinks
    {
        $links = PlatformLinks::fromArray($this->getLinks(), $this->label?->getName());
        if ($this->label) {
            return $links->withLabel(new CatalogueLabel(
                (string) $this->label->getName(),
                $this->labelUrl ?? $this->label->releaseUrl($this) ?? $this->label->getUrl(),
                $this->label->getShopUrl(),
            ));
        }
        if ($this->labelUrl) {
            return $links->with(new PlatformLink(Platform::LABEL, $this->labelUrl));
        }

        return $links;
    }

    /**
     * What "buy it at the label" opens: this record's own page there
     * (labelUrl, or the label's pattern), else the label's shop, else its site.
     */
    public function getLabelLink(): ?PlatformLink
    {
        $links = $this->getPlatformLinks();
        $ownPage = null !== $this->labelUrl || null !== $this->label?->releaseUrl($this);

        return $ownPage
            ? $links->get(Platform::LABEL)
            : $links->get(Platform::SHOP) ?? $links->get(Platform::LABEL);
    }

    public function getAwards(): ?string { return $this->awards; }
    public function setAwards(?string $awards): self { $this->awards = $awards ?: null; return $this; }

    /**
     * The prizes, a line each: what was won, and who gave it when the line
     * names them after a dash ("CD of the year — Radio România Muzical").
     *
     * @return list<array{title: string, by: ?string}>
     */
    public function getAwardList(): array
    {
        $awards = [];
        foreach (preg_split('/\R/u', (string) $this->awards) ?: [] as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            $parts = preg_split('/\s+[—–-]\s+/u', $line, 2) ?: [$line];
            $awards[] = ['title' => trim($parts[0]), 'by' => isset($parts[1]) ? trim($parts[1]) : null];
        }

        return $awards;
    }

    /** @return Collection<int, Performer> */
    public function getPerformers(): Collection { return $this->performers; }

    public function addPerformer(Performer $performer): self
    {
        if (!$this->performers->contains($performer)) {
            $this->performers->add($performer);
        }

        return $this;
    }

    public function removePerformer(Performer $performer): self
    {
        $this->performers->removeElement($performer);

        return $this;
    }

    /** @return Collection<int, Track> */
    public function getTracks(): Collection { return $this->tracks; }

    public function addTrack(Track $track): self
    {
        if (!$this->tracks->contains($track)) {
            if (null === $track->getPosition()) {
                $track->setPosition($this->tracks->count() + 1);
            }
            $this->tracks->add($track);
            $track->setRelease($this);
        }

        return $this;
    }

    public function removeTrack(Track $track): self
    {
        if ($this->tracks->removeElement($track) && $track->getRelease() === $this) {
            $track->setRelease(null);
        }

        return $this;
    }

    /** @return list<Track> by disc then place, whatever order they were added in */
    public function getOrderedTracks(): array
    {
        $tracks = $this->tracks->toArray();
        usort($tracks, static fn (Track $a, Track $b) => [$a->getDisc(), $a->getPosition() ?? \PHP_INT_MAX] <=> [$b->getDisc(), $b->getPosition() ?? \PHP_INT_MAX]);

        return $tracks;
    }

    /** @return array<int, list<Track>> disc => its tracks */
    public function getDiscs(): array
    {
        $discs = [];
        foreach ($this->getOrderedTracks() as $track) {
            $discs[$track->getDisc()][] = $track;
        }

        return $discs;
    }

    /** How many times the site's player played its tracks, all together. */
    public function getPlays(): int
    {
        return array_sum(array_map(static fn (Track $track): int => $track->getPlays(), $this->tracks->toArray()));
    }

    /** Seconds, all tracks together; null as long as one has no duration. */
    public function getDuration(): ?int
    {
        $total = 0;
        foreach ($this->tracks as $track) {
            if (null === $track->getDuration()) {
                return null;
            }
            $total += $track->getDuration();
        }

        return $total ?: null;
    }

    public function isFeatured(): bool { return $this->featured; }
    public function setFeatured(bool $featured): self { $this->featured = $featured; return $this; }

    /** Out: not announced only, and its date (when it has one) is behind us. */
    public function isOut(): bool
    {
        return !$this->upcoming && (null === $this->releasedAt || $this->releasedAt <= new \DateTimeImmutable('today'));
    }

    /** Publish it now (or at $at), or take it back to draft. */
    public function publish(bool $published = true, ?\DateTimeInterface $at = null): self
    {
        $this->setState($published ? ThreadState::PUBLISH : ThreadState::DRAFT);
        if ($published) {
            $this->setPublishedAt($at ? \DateTime::createFromInterface($at) : ($this->getPublishedAt() ?? new \DateTime()));
        }

        return $this;
    }
}
