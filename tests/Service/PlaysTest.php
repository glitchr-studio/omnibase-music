<?php

namespace Base\Music\Tests\Service;

use Base\Music\Entity\Track;
use Base\Music\Repository\TrackRepository;
use Base\Music\Service\Plays;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * A play counted once it was heard: once at a time for the same visitor on
 * the same track - the same beacon sent again, a page reloaded, count one -,
 * again after Plays::EVERY seconds, never for a robot.
 */
final class PlaysTest extends TestCase
{
    private int $counted = 0;

    private function plays(?object $classifier = null): Plays
    {
        $tracks = $this->createStub(TrackRepository::class);
        $tracks->method('countPlay')->willReturnCallback(function () { ++$this->counted; });

        return new Plays($tracks, new ArrayAdapter(), $classifier);
    }

    private static function track(int $id): Track
    {
        $track = new Track();
        (new \ReflectionProperty(Track::class, 'id'))->setValue($track, $id);

        return $track;
    }

    private const BROWSER = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15';

    public function testOneListeningIsOnePlay(): void
    {
        $plays = $this->plays();

        self::assertTrue($plays->count(self::track(76), '203.0.113.7', self::BROWSER));
        self::assertFalse($plays->count(self::track(76), '203.0.113.7', self::BROWSER), 'the same beacon again');
        self::assertSame(1, $this->counted);

        self::assertTrue($plays->count(self::track(77), '203.0.113.7', self::BROWSER), 'another track');
        self::assertTrue($plays->count(self::track(76), '198.51.100.4', self::BROWSER), 'another visitor');
        self::assertSame(3, $this->counted);
    }

    public function testARobotIsNotAListener(): void
    {
        $classifier = new class {
            public function classify(?string $userAgent): string { return str_contains((string) $userAgent, 'bot') ? 'bot' : ('' === trim((string) $userAgent) ? 'bot' : 'human'); }
        };
        $plays = $this->plays($classifier);

        self::assertFalse($plays->count(self::track(76), '203.0.113.7', 'Googlebot/2.1 (+http://www.google.com/bot.html)'));
        self::assertFalse($plays->count(self::track(76), '203.0.113.8', ''), 'no user agent at all');
        self::assertTrue($plays->count(self::track(76), '203.0.113.9', self::BROWSER));
        self::assertSame(1, $this->counted);
    }

    public function testWithoutTheCoresClassifierOnlyAnEmptyUserAgentIsARobot(): void
    {
        self::assertTrue($this->plays()->isRobot(null));
        self::assertTrue($this->plays()->isRobot('  '));
        self::assertFalse($this->plays()->isRobot(self::BROWSER));
    }

    public function testTheVisitorIsKeptNowhere(): void
    {
        $cache = new ArrayAdapter();
        $tracks = $this->createStub(TrackRepository::class);
        (new Plays($tracks, $cache))->count(self::track(76), '203.0.113.7', self::BROWSER);

        foreach (array_keys($cache->getValues()) as $key) {
            self::assertStringNotContainsString('203.0.113.7', $key, 'a hash, not the address');
        }
        self::assertSame(20, Plays::EVERY);
    }
}
