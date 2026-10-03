<?php

namespace Base\Music\Tests\Twig;

use Base\Music\Twig\MusicExtension;
use Omnisong\Platform;
use PHPUnit\Framework\TestCase;

/** music_platform_icon(): a pill's mark - the brand's where Font Awesome draws it, else a neutral one. */
final class PlatformIconTest extends TestCase
{
    public function testABrandGetsItsOwnMark(): void
    {
        self::assertSame('fa-brands fa-spotify', MusicExtension::platformIcon(Platform::SPOTIFY));
        self::assertSame('fa-brands fa-apple', MusicExtension::platformIcon(Platform::APPLE_MUSIC));
        self::assertSame('fa-brands fa-deezer', MusicExtension::platformIcon('deezer'), 'the stored value works too');
    }

    public function testThePlatformsWithoutAMarkGetANeutralOne(): void
    {
        self::assertSame('fa-solid fa-compact-disc', MusicExtension::platformIcon(Platform::HIGHRESAUDIO));
        self::assertSame('fa-solid fa-music', MusicExtension::platformIcon(Platform::QOBUZ));
        self::assertSame('fa-solid fa-music', MusicExtension::platformIcon('nowhere'));
        self::assertSame('fa-solid fa-music', MusicExtension::platformIcon(null));
    }

    public function testEveryPlatformHasOne(): void
    {
        foreach (Platform::cases() as $platform) {
            self::assertMatchesRegularExpression('/^fa-(solid|brands) fa-[a-z-]+$/', MusicExtension::platformIcon($platform), $platform->value);
        }
    }
}
