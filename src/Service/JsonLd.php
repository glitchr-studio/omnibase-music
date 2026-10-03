<?php

namespace Base\Music\Service;

use Base\Music\Entity\Release;
use Base\Service\SettingBagInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A release as schema.org reads it: a MusicAlbum by the musician, on its
 * label (recordLabel), with its tracks, its catalogue number and its links
 * (sameAs) - what search engines show next to the record.
 */
class JsonLd
{
    public function __construct(
        private readonly ?SettingBagInterface $settingBag = null,
        #[Autowire('%music.artist%')] private readonly ?string $artist = null,
    ) {
    }

    /** The musician's name: music.artist, else the site's title (base.settings.title). */
    public function artist(): ?string
    {
        if ($this->artist) {
            return $this->artist;
        }
        try {
            $title = $this->settingBag?->getScalar('base.settings.title');
        } catch (\Throwable) {
            $title = null;
        }

        return \is_string($title) && '' !== $title ? $title : null;
    }

    /** @return array<string, mixed> */
    public function album(Release $release, ?string $url = null, ?string $image = null): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'MusicAlbum',
            'name' => $release->getTitle(),
            'url' => $url,
            'image' => $image ?? $release->getArtwork(),
            'datePublished' => $release->getReleasedAt()?->format('Y-m-d'),
            'albumReleaseType' => $release->getType()->schema(),
            'albumProductionType' => 'https://schema.org/StudioAlbum',
            'catalogNumber' => $release->getCatalogue(),
            'gtin' => $release->getUpc(),
            'description' => $release->getExcerpt() ?? $release->getHeadline(),
            'numTracks' => $release->getTracks()->count() ?: null,
        ];
        if (null !== $artist = $this->artist()) {
            $data['byArtist'] = ['@type' => 'MusicGroup', 'name' => $artist];
        }
        $contributors = [];
        foreach ($release->getPerformers() as $performer) {
            $contributors[] = array_filter(['@type' => $performer->getKind()->schema(), 'name' => $performer->getName(), 'url' => $performer->getUrl()]);
        }
        if ($contributors) {
            $data['contributor'] = $contributors;
        }
        if ($label = $release->getLabel()) {
            $data['recordLabel'] = array_filter([
                '@type' => 'Organization',
                'name' => $label->getName(),
                'url' => $label->getUrl(),
            ]);
        }
        $tracks = [];
        foreach ($release->getOrderedTracks() as $i => $track) {
            $tracks[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'item' => array_filter([
                    '@type' => 'MusicRecording',
                    'name' => $track->getDisplayTitle(),
                    'duration' => null !== $track->getDuration() ? sprintf('PT%dM%dS', intdiv($track->getDuration(), 60), $track->getDuration() % 60) : null,
                    'isrcCode' => $track->getIsrc(),
                    'recordingOf' => $track->getWork() ? array_filter([
                        '@type' => 'MusicComposition',
                        'name' => $track->getWork()->getFullTitle(),
                        'composer' => ['@type' => 'Person', 'name' => $track->getWork()->getComposer()],
                    ]) : null,
                ]),
            ];
        }
        if ($tracks) {
            $data['track'] = ['@type' => 'ItemList', 'numberOfItems' => \count($tracks), 'itemListElement' => $tracks];
        }
        $sameAs = array_values(array_map(static fn ($link) => $link->url, $release->getPlatformLinks()->all()));
        if ($sameAs) {
            $data['sameAs'] = $sameAs;
        }

        return array_filter($data, static fn ($value) => null !== $value && '' !== $value && [] !== $value);
    }
}
