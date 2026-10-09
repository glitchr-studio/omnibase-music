<?php

namespace Base\Music\Tests\Twig;

use PHPUnit\Framework\TestCase;

/**
 * Every waveform canvas carries a stable id: transparentjs keeps a page's
 * canvases across a swap by their id, and one without (the player's wave)
 * stopped the others being kept, with "Unexpected canvas without ID found.."
 * in the console (transparentjs before 3.0.31).
 */
final class WaveCanvasTest extends TestCase
{
    public function testTheTemplatesGiveTheirCanvasAnId(): void
    {
        $templates = \dirname(__DIR__, 2).'/templates';
        $found = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($templates, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }
            preg_match_all('~<canvas\b[^>]*>~', file_get_contents($file->getPathname()), $canvases);
            foreach ($canvases[0] as $canvas) {
                ++$found;
                self::assertMatchesRegularExpression('~\bid="~', $canvas, $file->getFilename().': '.$canvas);
            }
        }
        self::assertGreaterThanOrEqual(3, $found, 'the player\'s two and a track\'s');
    }

    public function testThePlayersWaveHasOneIdEitherWay(): void
    {
        $bar = file_get_contents(\dirname(__DIR__, 2).'/templates/client/_player_bar.html.twig');

        self::assertSame(2, substr_count($bar, 'id="music-player-wave"'), 'with waves or flat: never both on a page');
    }
}
