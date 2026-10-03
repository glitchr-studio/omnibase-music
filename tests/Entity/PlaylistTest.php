<?php

namespace Base\Music\Tests\Entity;

use Base\Music\Entity\Playlist;
use Omnisong\Platform;
use PHPUnit\Framework\TestCase;

final class PlaylistTest extends TestCase
{
    public function testThePlatformIsReadOffTheAddress(): void
    {
        self::assertSame(Platform::SPOTIFY, (new Playlist())->setUrl('https://open.spotify.com/artist/0abc')->getPlatform());
        self::assertSame(Platform::DEEZER, (new Playlist())->setUrl('https://www.deezer.com/fr/playlist/123')->getPlatform());
        // Platform::fromUrl() reads an Apple Music album or song only.
        self::assertSame(Platform::APPLE_MUSIC, (new Playlist())->setUrl('https://music.apple.com/de/playlist/pl.u-abc')->getPlatform());
        self::assertNull((new Playlist())->setUrl('https://example.org/list')->getPlatform());
        self::assertNull((new Playlist())->getLink());
    }

    public function testTheLinkCarriesTheTitle(): void
    {
        $link = (new Playlist())->setTitle('Anaëlle Tourret')->setUrl('https://open.spotify.com/artist/0abc')->getLink();

        self::assertSame(Platform::SPOTIFY, $link->platform);
        self::assertSame('Anaëlle Tourret', $link->title());
    }
}
