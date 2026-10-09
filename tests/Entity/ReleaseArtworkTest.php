<?php

namespace Base\Music\Tests\Entity;

use Base\Music\Entity\Release;
use PHPUnit\Framework\TestCase;

/**
 * The sleeve a page prints is an address a browser loads: an uploaded cover
 * by its path on the site ("/uploads/..."), never the file's on disk
 * (/srv/app/public/uploads/...), which the pages printed as an image's src;
 * without an upload, the catalogue's address. As omnibase/scholar's
 * Publication::getCoverUrl() does.
 */
final class ReleaseArtworkTest extends TestCase
{
    /** A release whose upload sits at $path (omnibase's Uploader answers the file's path; it needs the kernel). */
    private static function release(?string $path, ?string $catalogue = null): Release
    {
        $release = new class extends Release {
            public ?string $stored = null;

            public function __construct()
            {
            }

            public function getCover(): ?string
            {
                return $this->stored;
            }
        };
        $release->stored = $path;
        if (null !== $path) {
            $release->setCover(basename($path));
        }

        return $release->setCoverUrl($catalogue);
    }

    public function testAnUploadedCoverIsItsAddressOnTheSite(): void
    {
        self::assertSame('/uploads/release/cover/perspectives.jpg', self::release('/srv/app/public/uploads/release/cover/perspectives.jpg', 'https://is1-ssl.mzstatic.com/x.jpg')->getArtwork(), 'the upload first, by its address');
        self::assertSame('/uploads/release/cover/a.jpg', self::release('/uploads/release/cover/a.jpg')->getArtwork(), 'already an address: as it is');
    }

    public function testWithoutAnUploadTheCataloguesAddress(): void
    {
        self::assertSame('https://is1-ssl.mzstatic.com/x.jpg', self::release(null, 'https://is1-ssl.mzstatic.com/x.jpg')->getArtwork());
        self::assertNull(self::release(null)->getArtwork());
    }
}
