<?php

namespace Base\Music\Tests\Service;

use Base\Music\Service\Peaks;
use PHPUnit\Framework\TestCase;

/** Peaks::reduce(): raw PCM (s16le, mono) to normalised RMS bars - no ffmpeg needed. */
final class PeaksTest extends TestCase
{
    /** @param list<int> $samples */
    private static function pcm(array $samples): string
    {
        return pack('v*', ...array_map(static fn (int $s) => $s & 0xFFFF, $samples));
    }

    public function testLoudestWindowIsOneAndTheOthersFollowTheirRms(): void
    {
        // Four windows of 2 000 samples: silence, a quarter, a half, full scale (as a square wave).
        $samples = [];
        foreach ([0, 8192, 16384, 32767] as $level) {
            for ($i = 0; $i < 2000; ++$i) {
                $samples[] = 0 === $i % 2 ? $level : -$level;
            }
        }
        $peaks = Peaks::reduce(self::pcm($samples), 4);

        self::assertCount(4, $peaks);
        self::assertSame(0.0, $peaks[0]);
        self::assertEqualsWithDelta(0.25, $peaks[1], 0.001);
        self::assertEqualsWithDelta(0.5, $peaks[2], 0.001);
        self::assertSame(1.0, $peaks[3]);
    }

    public function testASineGivesAnEvenWaveOfTwoHundredBars(): void
    {
        // Ten seconds of a 440 Hz sine at 8 kHz, its amplitude rising from nothing to full.
        $samples = [];
        $n = Peaks::RATE * 10;
        for ($i = 0; $i < $n; ++$i) {
            $samples[] = (int) round(sin(2 * M_PI * 440 * $i / Peaks::RATE) * 30000 * $i / $n);
        }
        $peaks = Peaks::reduce(self::pcm($samples));

        self::assertCount(Peaks::COUNT, $peaks);
        self::assertSame(1.0, max($peaks));
        self::assertLessThan(0.01, $peaks[0]);
        // Rising amplitude: every bar louder than the one before.
        for ($i = 1; $i < Peaks::COUNT; ++$i) {
            self::assertGreaterThanOrEqual($peaks[$i - 1], $peaks[$i]);
        }
        foreach ($peaks as $peak) {
            self::assertGreaterThanOrEqual(0.0, $peak);
            self::assertLessThanOrEqual(1.0, $peak);
        }
    }

    public function testNegativeSamplesAreReadAsSigned(): void
    {
        $peaks = Peaks::reduce(self::pcm([-32768, -32768, 16384, 16384]), 2);

        self::assertSame([1.0, 0.5], $peaks);
    }

    public function testShortSilentAndEmptyBuffers(): void
    {
        self::assertSame([], Peaks::reduce(''));
        self::assertSame([], Peaks::reduce("\x01"), 'half a sample is no sample');
        self::assertCount(3, Peaks::reduce(self::pcm([100, 200, 300])), 'fewer samples than bars: one bar a sample');
        self::assertSame([0.0, 0.0], Peaks::reduce(self::pcm([0, 0, 0, 0]), 2));
    }
}
