<?php

namespace Base\Music\Service;

use Base\Music\Entity\Playlist;
use Base\Music\Entity\Release;
use Base\Music\Entity\Track;
use Omnisong\Exception\OmnisongException;
use Omnisong\Model\Embed;
use Omnisong\Model\PlatformLink;
use Omnisong\Platform;
use Omnisong\Player\EmbedderInterface;
use Omnisong\Player\EmbedOptions;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * What a track plays from, in the order music.player.order says: the
 * site's own excerpt (file), a catalogue's 30 s preview (preview), and -
 * when there is no audio at all - the iframe of the first platform link
 * Omnisong's Embedder can play (embed), loaded only on a click.
 */
class Player
{
    public const FILE = 'file';
    public const PREVIEW = 'preview';
    public const EMBED = 'embed';

    /**
     * @param list<string> $order
     * @param list<string> $full  the platforms "Listen in full" offers, in order
     */
    public function __construct(
        private readonly ?EmbedderInterface $embedder = null,
        private readonly ?EmbedOptions $embedOptions = null,
        private readonly ?LoggerInterface $logger = null,
        #[Autowire('%music.player.order%')] private readonly array $order = [self::FILE, self::PREVIEW, self::EMBED],
        #[Autowire('%music.player.theme%')] private readonly string $theme = 'light',
        #[Autowire('%music.player.full%')] private readonly array $full = ['spotify', 'apple_music', 'deezer'],
    ) {
    }

    /**
     * The whole release on the platforms "Listen in full" offers
     * (music.player.full), each an iframe built on a click: a listener
     * logged in there streams it - and the stream counts for the musician,
     * where a 30 s preview does not.
     *
     * @return list<Embed>
     */
    public function full(Release $release): array
    {
        $links = $release->getPlatformLinks();
        $embeds = [];
        foreach ($this->full as $value) {
            $platform = Platform::tryFrom((string) $value);
            $link = $platform ? $links->get($platform) : null;
            if ($link && null !== $embed = $this->embedLink($link)) {
                $embeds[] = $embed;
            }
        }

        return $embeds;
    }

    /** A playlist's (or a profile's) iframe; null when its platform cannot be embedded. */
    public function playlist(Playlist $playlist): ?Embed
    {
        $link = $playlist->getLink();

        return $link ? $this->embedLink($link) : null;
    }

    /**
     * The source of one track for player.js: what it is and where it is.
     * Null when it has nothing to play (no file, no preview, no embed).
     *
     * @return array{kind: string, src: string}|null
     */
    public function source(Track $track): ?array
    {
        foreach ($this->order as $kind) {
            $src = match ($kind) {
                self::FILE => $track->hasSample() ? $this->safely(fn () => $track->getSample()) : null,
                self::PREVIEW => $track->getPreviewUrl(),
                self::EMBED => $track->getRelease() ? $this->embed($track->getRelease())?->src : null,
                default => null,
            };
            if (\is_string($src) && '' !== $src) {
                return ['kind' => $kind, 'src' => $src];
            }
        }

        return null;
    }

    /** Whether music.player.order lets the embeds in at all. */
    public function embedsAllowed(): bool
    {
        return \in_array(self::EMBED, $this->order, true);
    }

    /**
     * The iframe of a release on one platform, or on the first platform of
     * its links the Embedder can play. Null when none can be, or when
     * Omnisong's bundle is not registered.
     */
    public function embed(Release $release, ?Platform $platform = null, ?EmbedOptions $options = null): ?Embed
    {
        if (null === $this->embedder) {
            return null;
        }
        $links = $release->getPlatformLinks();
        $candidates = $platform ? array_filter([$links->get($platform)]) : $links->embeddable();
        foreach ($candidates as $link) {
            if (null !== $embed = $this->embedLink($link, $options)) {
                return $embed;
            }
        }

        return null;
    }

    /** One link's iframe, through Omnisong's Embedder; null when it cannot be embedded. */
    public function embedLink(PlatformLink $link, ?EmbedOptions $options = null): ?Embed
    {
        if (null === $this->embedder || !$this->embedder->supports($link->platform)) {
            return null;
        }
        try {
            return $this->embedder->embed($link, $options ?? $this->options());
        } catch (OmnisongException $e) {
            $this->logger?->info('music: no embed for {url}: {error}', ['url' => $link->url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** The site's options (Omnisong's own when its bundle defines them), on the theme music.player.theme names. */
    public function options(): EmbedOptions
    {
        $options = $this->embedOptions;
        if (null === $options) {
            return new EmbedOptions($this->theme);
        }

        return $options->theme === $this->theme ? $options : new EmbedOptions($this->theme, $options->compact, $options->autoplay, $options->country, $options->color);
    }

    private function safely(callable $read): ?string
    {
        try {
            $value = $read();
        } catch (\Throwable $e) {
            $this->logger?->warning('music: a file could not be read: {error}', ['error' => $e->getMessage()]);

            return null;
        }

        return \is_string($value) ? $value : null;
    }
}
