<?php

namespace Base\Music\Controller\Client;

use Base\Attributes\Attribute\Sitemap;
use Base\Music\Enum\PlaylistKind;
use Base\Music\Repository\InstrumentRepository;
use Base\Music\Repository\PlaylistRepository;
use Base\Music\Repository\ReleaseRepository;
use Base\Music\Repository\VideoRepository;
use Base\Music\Repository\WorkRepository;
use Base\Music\Service\JsonLd;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        private readonly JsonLd $jsonLd,
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
