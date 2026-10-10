<?php

namespace Base\Music\Controller\Client;

use Base\Attributes\Attribute\Sitemap;
use Base\Music\Enum\PlaylistKind;
use Base\Music\Repository\InstrumentRepository;
use Base\Music\Repository\PlaylistRepository;
use Base\Music\Repository\ReleaseRepository;
use Base\Music\Repository\TrackRepository;
use Base\Music\Repository\VideoRepository;
use Base\Music\Repository\WorkRepository;
use Base\Music\Service\JsonLd;
use Base\Music\Service\Player;
use Base\Music\Service\Plays;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The musician's pages: the discography (the featured record first, then
 * the others, the announced ones, the films), one record with its label,
 * its player and its notes, the repertoire, the films, the instrument.
 */
class MusicController extends AbstractController
{
    public function __construct(
        private readonly ReleaseRepository $releases,
        private readonly VideoRepository $videos,
        private readonly WorkRepository $works,
        private readonly InstrumentRepository $instruments,
        private readonly PlaylistRepository $playlists,
        private readonly TrackRepository $tracks,
        private readonly Plays $plays,
        private readonly JsonLd $jsonLd,
        private readonly Player $player,
        #[Autowire('%music.jsonld%')] private readonly bool $withJsonLd = true,
        #[Autowire('%music.repertoire.enabled%')] private readonly bool $repertoire = true,
        #[Autowire('%music.repertoire.group_by%')] private readonly string $groupBy = WorkRepository::BY_FORMATION,
        #[Autowire('%music.instrument.enabled%')] private readonly bool $instrument = true,
    ) {
    }

    #[Sitemap(priority: 0.9, changefreq: 'weekly')]
    #[Route('/music', name: 'music_index')]
    public function index(): Response
    {
        $featured = $this->releases->findFeatured();
        // Compared here, by identity: Twig's == and "in" compare entities field by field.
        $upcoming = array_values(array_filter($this->releases->findUpcoming(), fn ($release) => $release !== $featured));
        $others = array_values(array_filter($this->releases->findPublished(), fn ($release) => $release !== $featured && !\in_array($release, $upcoming, true)));

        return $this->render('@Music/client/index.html.twig', [
            'featured' => $featured,
            'upcoming' => $upcoming,
            'others' => $others,
            'videos' => $this->videos->findPublished(6),
            // "Listen in full": the profile first, then the musician's own playlists, then the picks.
            'artist' => $this->playlists->findFeatured(PlaylistKind::ARTIST),
            'own' => $this->playlists->findVisible(PlaylistKind::OWN),
            'recommended' => $this->playlists->findVisible(PlaylistKind::RECOMMENDED),
        ]);
    }

    #[Sitemap(priority: 0.6, changefreq: 'monthly')]
    #[Route('/repertoire', name: 'music_repertoire')]
    public function repertoire(Request $request): Response
    {
        if (!$this->repertoire) {
            throw $this->createNotFoundException('The repertoire is not enabled (music.repertoire.enabled).');
        }
        $by = (string) $request->query->get('by', $this->groupBy);
        $by = \in_array($by, WorkRepository::GROUPS, true) ? $by : $this->groupBy;

        return $this->render('@Music/client/repertoire.html.twig', [
            'groups' => $this->works->findVisibleGrouped($by),
            'by' => $by,
            'choices' => WorkRepository::GROUPS,
        ]);
    }

    #[Sitemap(priority: 0.7, changefreq: 'weekly')]
    #[Route('/videos', name: 'music_videos')]
    public function videos(): Response
    {
        return $this->render('@Music/client/videos.html.twig', [
            'videos' => $this->videos->findPublished(),
        ]);
    }

    #[Route('/videos/{slug}', name: 'music_video', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function video(string $slug): Response
    {
        $video = $this->videos->findOnePublished($slug)
            ?? throw $this->createNotFoundException(sprintf('No published video "%s".', $slug));

        return $this->render('@Music/client/video.html.twig', [
            'video' => $video,
            'others' => array_values(array_filter($this->videos->findPublished(4), fn ($other) => $other !== $video)),
        ]);
    }

    #[Sitemap(priority: 0.5, changefreq: 'yearly')]
    #[Route('/instrument', name: 'music_instrument')]
    public function instrument(): Response
    {
        $instruments = $this->instrument ? $this->instruments->findVisible() : [];
        if (!$instruments) {
            throw $this->createNotFoundException('No visible instrument (or music.instrument.enabled is off).');
        }

        return $this->render('@Music/client/instrument.html.twig', [
            'instrument' => $instruments[0],
            'others' => \array_slice($instruments, 1),
        ]);
    }

    /**
     * For the player's bar, wherever the visitor is: what its sheet says about
     * the record, and "listen in full" - per platform, the iframe of the whole release (built by player.js only
     * when asked for) and the record's page there, for "See the album".
     */
    #[Route('/music/{slug}/full', name: 'music_release_full', requirements: ['slug' => '[a-z0-9\-]+'], methods: ['GET'], format: 'json')]
    public function full(string $slug): JsonResponse
    {
        $release = $this->releases->findOnePublished($slug)
            ?? throw $this->createNotFoundException(sprintf('No published release "%s".', $slug));
        $page = $this->generateUrl('music_release', ['slug' => $release->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);

        $response = $this->json([
            'release' => $release->getSlug(),
            'title' => $release->getTitle(),
            'page' => $page,
            'platforms' => $this->player->listen($release, $page),
            // The sheet's words about the record, in the reader's language.
            'about' => $this->renderView('@Music/client/_sheet_about.html.twig', ['release' => $release]),
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response->setPublic()->setMaxAge(300);
    }

    /**
     * One play of a track, told by player.js once the visitor has heard it
     * (Service\Plays: once at a time for the same visitor, never a robot's).
     * The people of the back office are not counted.
     */
    #[Route('/music/play/{id}', name: 'music_track_play', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function play(int $id, Request $request): Response
    {
        $track = $this->tracks->find($id);
        $release = $track?->getRelease();
        if (null === $release || $this->releases->findOnePublished((string) $release->getSlug()) !== $release) {
            throw $this->createNotFoundException(sprintf('No track %d on a published release.', $id));
        }
        $response = new Response(null, Response::HTTP_NO_CONTENT, ['X-Robots-Tag' => 'noindex']);
        if ($this->isGranted('ROLE_STAFF')) {
            return $response;
        }
        $this->plays->count($track, $request->getClientIp(), $request->headers->get('User-Agent'));

        return $response;
    }

    /** Last, so /music/{slug} never takes another page's path. */
    #[Route('/music/{slug}', name: 'music_release', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function release(string $slug): Response
    {
        $release = $this->releases->findOnePublished($slug)
            ?? throw $this->createNotFoundException(sprintf('No published release "%s".', $slug));

        return $this->render('@Music/client/release.html.twig', [
            'release' => $release,
            'videos' => $this->videos->findByRelease($release),
            'jsonld' => $this->withJsonLd ? $this->jsonLd->album($release, $this->generateUrl('music_release', ['slug' => $release->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL)) : null,
        ]);
    }
}
