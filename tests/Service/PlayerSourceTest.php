<?php

namespace Base\Music\Tests\Service;

use Base\Music\Entity\Track;
use Base\Music\Service\Player;
use PHPUnit\Framework\TestCase;

/**
 * What the player plays for a track: the site's file first - by its address
 * on the site, the whole track only when the box was ticked -, else a
 * catalogue's 30 seconds. Nothing plays a work in full by default.
 */
final class PlayerSourceTest extends TestCase
{
    /** A track whose upload sits at $path (omnibase's Uploader answers the file's path on the disk; it needs the kernel). */
    private static function track(?string $path): Track
    {
        $track = new class extends Track {
            public ?string $stored = null;

            public function getSample(): ?string
            {
                return $this->stored;
            }
        };
        $track->stored = $path;
        if (null !== $path) {
            $track->setSample(basename($path));
        }

        return $track;
    }

    public function testTheSitesFileByItsAddressAnExcerptByDefault(): void
    {
        $track = self::track('/srv/app/public/uploads/_/track/_sample/338bb7fb-9b03-4bb3-ba5a-d3eb00398ce7')->setPreviewUrl('https://audio-ssl.itunes.apple.com/preview.m4a');

        self::assertSame(['kind' => Player::FILE, 'src' => '/uploads/_/track/_sample/338bb7fb-9b03-4bb3-ba5a-d3eb00398ce7', 'whole' => false], (new Player())->source($track), 'never the path on the disk; an excerpt unless ticked');
        self::assertFalse($track->isWhole(), 'the box is unticked by default');
    }

    public function testTheWholeTrackWhenTheBoxIsTicked(): void
    {
        $source = (new Player())->source(self::track('/srv/app/public/uploads/_/track/_sample/a')->setWhole(true));

        self::assertTrue($source['whole']);
        self::assertSame('/uploads/_/track/_sample/a', $source['src']);
    }

    public function testWithoutAFileACataloguesThirtySecondsNeverWhole(): void
    {
        $source = (new Player())->source(self::track(null)->setPreviewUrl('https://audio-ssl.itunes.apple.com/preview.m4a')->setWhole(true));

        self::assertSame(['kind' => Player::PREVIEW, 'src' => 'https://audio-ssl.itunes.apple.com/preview.m4a', 'whole' => false], $source);
        self::assertNull((new Player())->source(self::track(null)), 'nothing to play');
    }

    public function testTheSheetHasNothingToOfferWithoutAPlatformsPlayer(): void
    {
        $release = (new \ReflectionClass(\Base\Music\Entity\Release::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(\Base\Music\Entity\Release::class, 'tracks'))->setValue($release, new \Doctrine\Common\Collections\ArrayCollection());

        self::assertSame([], (new Player())->full($release), 'no Embedder (glitchr/omnisong), no iframe');
        self::assertSame([], (new Player())->listen($release, 'https://site.example/music/a'));
        self::assertFalse((new Player())->hasFull($release));
    }
}
