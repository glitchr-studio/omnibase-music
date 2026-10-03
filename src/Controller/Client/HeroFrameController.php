<?php

namespace Base\Music\Controller\Client;

use Base\Music\Service\HeroFrame;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Where the "Reframe" of the home page's picture is kept (public/js/hero-frame.js):
 * an administrator drags the picture, zooms, saves - without leaving the page.
 */
#[IsGranted('ROLE_ADMIN')]
final class HeroFrameController extends AbstractController
{
    public const TOKEN = 'music-hero-frame';

    #[Route('/admin/music/hero-frame', name: 'music_hero_frame', methods: ['POST'])]
    public function save(Request $request, HeroFrame $frame): Response
    {
        if (!$this->isCsrfTokenValid(self::TOKEN, (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }
        $data = json_decode($request->getContent(), true) ?: [];
        try {
            if (!empty($data['reset'])) {
                $frame->reset();
            } else {
                $frame->save(isset($data['position']) ? (string) $data['position'] : null, isset($data['zoom']) ? (float) $data['zoom'] : null);
            }
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['saved' => true]);
    }
}
