<?php

namespace Base\Music\Tests\Entity;

use Base\Music\Entity\Video;
use PHPUnit\Framework\TestCase;

final class VideoTest extends TestCase
{
    private static function video(): Video
    {
        return (new \ReflectionClass(Video::class))->newInstanceWithoutConstructor();
    }

    public function testYoutubeIdFromAnyAddress(): void
    {
        foreach (['dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=3s', 'https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'] as $value) {
            self::assertSame('dQw4w9WgXcQ', self::video()->setYoutubeId($value)->getYoutubeId(), $value);
        }
        self::assertSame('76979871', self::video()->setVimeoId('https://vimeo.com/76979871')->getVimeoId());
    }

    public function testEmbedWithoutTracking(): void
    {
        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&modestbranding=1&autoplay=1', self::video()->setYoutubeId('dQw4w9WgXcQ')->getEmbedUrl());
        self::assertSame('https://player.vimeo.com/video/76979871?dnt=1', self::video()->setVimeoId('76979871')->getEmbedUrl(false));
        self::assertNull(self::video()->getEmbedUrl());
        self::assertFalse(self::video()->isPlayable());
    }

    public function testALocalFilmHasNoEmbed(): void
    {
        $video = self::video()->setYoutubeId('dQw4w9WgXcQ')->setFile('films/concert.mp4');

        self::assertTrue($video->isLocal());
        self::assertNull($video->getEmbedUrl());
        self::assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $video->getWatchUrl());
    }
}
