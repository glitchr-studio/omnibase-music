<?php

namespace Base\Music\Twig;

use Base\Music\Service\HeroFrame;
use Base\Music\Entity\Instrument;
use Base\Music\Entity\Release;
use Base\Music\Entity\Track;
use Base\Music\Entity\Video;
use Base\Music\Entity\Playlist;
use Base\Music\Enum\PlaylistKind;
use Base\Music\Enum\ReleaseType;
use Base\Music\Repository\InstrumentRepository;
use Base\Music\Repository\PlaylistRepository;
use Base\Music\Repository\ReleaseRepository;
use Base\Music\Repository\VideoRepository;
use Base\Music\Service\Player;
use Base\Service\SettingBagInterface;
use Omnisong\Platform;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * What a host's own pages ask the music: the player's bar (once, in the
 * layout), a release's player and links - the label first - the last
 * releases and films for a home page, the hero film, the instrument, the
 * line of who plays with the musician, and durations as a sleeve prints them.
 */
final class MusicExtension extends AbstractExtension
{
    public function __construct(
        private readonly ReleaseRepository $releases,
        private readonly VideoRepository $videos,
        private readonly InstrumentRepository $instruments,
        private readonly PlaylistRepository $playlists,
        private readonly Player $player,
        private readonly ?SettingBagInterface $settingBag = null,
        #[Autowire('%music.player.waves%')] private readonly bool $waves = true,
        #[Autowire('%music.player.live_waves%')] private readonly bool $liveWaves = true,
        #[Autowire('%music.label_first%')] private readonly bool $labelFirst = true,
    ) {
    }

    public function getFunctions(): array
    {
        $html = ['is_safe' => ['html'], 'needs_environment' => true];

        return [
            new TwigFunction('music_player', $this->renderPlayer(...), $html),
            new TwigFunction('music_player_bar', $this->renderBar(...), $html),
            new TwigFunction('music_links', fn (Environment $twig, Release $release): string => $twig->render('@Music/client/_links.html.twig', ['release' => $release, 'label_first' => $this->labelFirst]), $html),
            new TwigFunction('music_hero_video', $this->renderHero(...), $html),
            new TwigFunction('music_embed', $this->renderEmbed(...), $html),
            new TwigFunction('music_listen_embed', $this->renderEmbed(...), $html),
            new TwigFunction('music_full', fn (Environment $twig, Release $release): string => $twig->render('@Music/client/_full.html.twig', ['release' => $release, 'embeds' => $this->player->full($release)]), $html),
            new TwigFunction('music_playlist_embed', $this->renderPlaylist(...), $html),
            new TwigFunction('music_playlists', fn (?string $kind = null): array => $this->playlists->findVisible(null !== $kind ? PlaylistKind::tryFrom($kind) : null)),
            new TwigFunction('music_latest', fn (int $limit = 3, ?string $type = null): array => $this->releases->findLatest($limit, null !== $type ? ReleaseType::tryFrom($type) : null)),
            new TwigFunction('music_releases', fn (): array => $this->releases->findPublished()),
            new TwigFunction('music_featured', fn (): ?Release => $this->releases->findFeatured()),
            new TwigFunction('music_upcoming', fn (): array => $this->releases->findUpcoming()),
            new TwigFunction('music_videos', fn (?int $limit = null): array => $this->videos->findPublished($limit)),
            new TwigFunction('music_instrument', fn (): ?Instrument => $this->instruments->findFeatured()),
            new TwigFunction('music_performers', self::performers(...)),
            new TwigFunction('music_platform_icon', self::platformIcon(...)),
            new TwigFunction('music_source', $this->player->source(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('music_duration', Track::formatDuration(...)),
        ];
    }

    /**
     * The Font Awesome classes of a platform's pill: its brand's mark where
     * Font Awesome (free) draws one, else a neutral one - a record for the
     * shops that sell files and discs, a note for the other streamers.
     */
    public static function platformIcon(Platform|string|null $platform): string
    {
        $platform = \is_string($platform) ? Platform::tryFrom($platform) : $platform;

        return match ($platform) {
            Platform::SPOTIFY => 'fa-brands fa-spotify',
            Platform::APPLE_MUSIC, Platform::ITUNES => 'fa-brands fa-apple',
            Platform::YOUTUBE, Platform::YOUTUBE_MUSIC => 'fa-brands fa-youtube',
            Platform::DEEZER => 'fa-brands fa-deezer',
            Platform::AMAZON_MUSIC, Platform::AMAZON => 'fa-brands fa-amazon',
            Platform::BANDCAMP => 'fa-brands fa-bandcamp',
            Platform::SOUNDCLOUD => 'fa-brands fa-soundcloud',
            Platform::NAPSTER => 'fa-brands fa-napster',
            Platform::SHOP => 'fa-solid fa-bag-shopping',
            Platform::LABEL, Platform::PRESTO, Platform::HIGHRESAUDIO => 'fa-solid fa-compact-disc',
            default => 'fa-solid fa-music',
        };
    }

    /**
     * Who plays with the musician, as one line: "Guillaume Vincent, piano",
     * "Ensemble Sur Le Pont, Michel Deneuve, conductor" - the template puts
     * "with" before it in the visitor's language.
     */
    public static function performers(Release|Video|null $subject): string
    {
        if (null === $subject) {
            return '';
        }
        $names = array_map(static fn ($performer) => (string) $performer, $subject->getPerformers()->toArray());

        return implode(' · ', array_filter($names));
    }

    /**
     * A release's track list with its play buttons, or one track's button
     * alone. `fold: true` (a page showing several records) puts one "listen"
     * button first and the track list behind a disclosure.
     *
     * @param array{fold?: bool} $options
     */
    private function renderPlayer(Environment $twig, Release|Track $subject, array $options = []): string
    {
        if ($subject instanceof Track) {
            return $twig->render('@Music/client/_track.html.twig', ['track' => $subject, 'release' => $subject->getRelease(), 'waves' => $this->waves, 'single' => true]);
        }

        return $twig->render('@Music/client/_player.html.twig', [
            'release' => $subject,
            'waves' => $this->waves,
            'fold' => (bool) ($options['fold'] ?? false),
            'playable' => $this->hasAudio($subject),
            'full' => $this->player->full($subject),
            'embed' => $this->hasAudio($subject) || !$this->player->embedsAllowed() ? null : $this->player->embed($subject),
        ]);
    }

    /** The bar at the bottom of the page: the host includes it once, in its layout. */
    private function renderBar(Environment $twig): string
    {
        return $twig->render('@Music/client/_player_bar.html.twig', [
            'waves' => $this->waves,
            'live_waves' => $this->liveWaves,
        ]);
    }

    /** The iframe of a release on a platform (or the first that plays), behind a click. */
    private function renderEmbed(Environment $twig, Release $release, Platform|string|null $platform = null): string
    {
        $platform = \is_string($platform) ? Platform::tryFrom($platform) : $platform;
        $embed = $this->player->embed($release, $platform);

        return $embed ? $twig->render('@Music/client/_embed.html.twig', ['embed' => $embed, 'release' => $release]) : '';
    }

    /** A playlist's iframe behind a click; nothing when its platform cannot be embedded. */
    private function renderPlaylist(Environment $twig, Playlist $playlist): string
    {
        $embed = $this->player->playlist($playlist);

        return $embed ? $twig->render('@Music/client/_embed.html.twig', ['embed' => $embed, 'playlist' => $playlist]) : '';
    }

    /**
     * The film of the home page: muted, looping, playing by itself - with a
     * button to hear it, and its poster alone for a visitor who asks for
     * less motion. The settings music.hero.video and music.hero.poster
     * (uploads in the back office's settings) say which.
     */
    private function renderHero(Environment $twig, array $attributes = []): string
    {
        $video = $this->setting('music.hero.video');
        // The host's own still (attributes.poster) until one is set in the back office.
        $poster = $this->setting('music.hero.poster') ?? ($attributes['poster'] ?? null);
        if (null === $video && null === $poster) {
            return '';
        }

        // The frame, set by an administrator on the page itself (HeroFrameController):
        // where the picture is anchored, and how close.
        $position = $this->setting('music.hero.position');
        $zoom = (float) ($this->setting('music.hero.zoom') ?? 1);

        return $twig->render('@Music/client/_hero_video.html.twig', [
            'video' => $video,
            'poster' => $poster,
            'attributes' => $attributes,
            'position' => $position && preg_match(HeroFrame::POSITION, $position) ? $position : null,
            'zoom' => $zoom >= 1 && $zoom <= HeroFrame::MAX_ZOOM ? $zoom : 1.0,
        ]);
    }

    private function hasAudio(Release $release): bool
    {
        foreach ($release->getTracks() as $track) {
            if ($track->hasAudio()) {
                return true;
            }
        }

        return false;
    }

    private function setting(string $path): ?string
    {
        try {
            $value = $this->settingBag?->getScalar($path);
        } catch (\Throwable) {
            return null;
        }
        if (\is_array($value)) {
            $value = reset($value) ?: null;
        }

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
