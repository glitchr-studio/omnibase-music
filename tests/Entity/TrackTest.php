<?php

namespace Base\Music\Tests\Entity;

use Base\Music\Entity\Track;
use Base\Music\Entity\Work;
use Base\Music\Enum\Formation;
use PHPUnit\Framework\TestCase;

final class TrackTest extends TestCase
{
    public function testDurationAsASleevePrintsIt(): void
    {
        self::assertSame('0:07', Track::formatDuration(7));
        self::assertSame('4:07', Track::formatDuration(247));
        self::assertSame('10:00', Track::formatDuration(600));
        self::assertSame('1:02:45', Track::formatDuration(3765));
        self::assertSame('0:30', Track::formatDuration(29.6));
        self::assertSame('', Track::formatDuration(null));
        self::assertSame('', Track::formatDuration(-1));
    }

    public function testDurationOfATrack(): void
    {
        $track = (new Track())->setDuration(1385);
        self::assertSame('23:05', $track->getDurationText());
        self::assertNull($track->setDuration(0)->getDuration(), 'no length is null, not zero');
        self::assertSame('', $track->getDurationText());
    }

    public function testTitleFallsBackToTheWorkAndItsMovement(): void
    {
        $work = (new Work())->setComposer('César Franck')->setTitle('Sonata for Violin and Piano in A major')->setOpus('FWV 8')->setFormation(Formation::CHAMBER);
        $track = (new Track())->setWork($work)->setMovement('II. Allegro');

        self::assertSame('César Franck: Sonata for Violin and Piano in A major, FWV 8 – II. Allegro', $track->getDisplayTitle());
        self::assertSame('Allegro', $track->setTitle('Allegro')->getDisplayTitle());
    }

    public function testIsrcIsNormalised(): void
    {
        self::assertSame('DEA622100101', (new Track())->setIsrc('de-a62-21-00101')->getIsrc());
        self::assertNull((new Track())->setIsrc('  ')->getIsrc());
    }

    public function testPeaksAreClampedAndANewExcerptForgetsThem(): void
    {
        $track = (new Track())->setPeaks([0.5, 1.4, -0.2, 0.12345]);
        self::assertSame([0.5, 1.0, 0.0, 0.123], $track->getPeaks());

        $track->setSample(null);
        self::assertNull($track->getPeaks());
        self::assertFalse($track->hasAudio());
        self::assertTrue($track->setPreviewUrl('https://audio-ssl.itunes.apple.com/preview.m4a')->hasAudio());
    }

    public function testWholeNeedsAFileOfTheSite(): void
    {
        $track = (new Track())->setPreviewUrl('https://audio-ssl.itunes.apple.com/preview.m4a')->setWhole(true);
        self::assertFalse($track->isWhole(), 'a preview is never the whole track');
        self::assertTrue($track->setSample('track.mp3')->isWhole());
        self::assertFalse($track->setWhole(null)->isWhole());
        self::assertSame(0, $track->getPlays());
    }

    public function testDiscIsOneAtLeast(): void
    {
        self::assertSame(1, (new Track())->setDisc(0)->getDisc());
        self::assertSame(2, (new Track())->setDisc(2)->getDisc());
    }
}
