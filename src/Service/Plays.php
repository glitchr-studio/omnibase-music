<?php

namespace Base\Music\Service;

use Base\Music\Entity\Track;
use Base\Music\Repository\TrackRepository;
use Psr\Cache\CacheItemPoolInterface;

/**
 * One play of a track, as player.js tells it once the visitor has heard it
 * (30 seconds, or most of a shorter file). Counted once at a time for the
 * same visitor on the same track (EVERY seconds: nobody hears it twice in
 * less - a page reloaded, the same beacon sent again), never for a robot
 * (a crawler's or an AI's user agent, or none: omnibase's
 * UserAgentClassifier, when the core has it), and never for the people of
 * the back office (the controller's business: it knows who signs in).
 * Nothing about the listener is kept: the key is a hash of the address and
 * the track, gone after EVERY seconds.
 */
class Plays
{
    /** Seconds before the same visitor's play of the same track counts again. */
    public const EVERY = 20;

    /** @param object|null $classifier Base\Service\Analytics\UserAgentClassifier (classify(?string): string) */
    public function __construct(
        private readonly TrackRepository $tracks,
        private readonly CacheItemPoolInterface $cache,
        private readonly ?object $classifier = null,
    ) {
    }

    /** Whether this play was counted. */
    public function count(Track $track, ?string $address, ?string $userAgent): bool
    {
        if ($this->isRobot($userAgent)) {
            return false;
        }
        $heard = $this->cache->getItem('music.play.'.hash('xxh128', (string) $address.'|'.$track->getId()));
        if ($heard->isHit()) {
            return false;
        }
        $this->cache->save($heard->set(true)->expiresAfter(self::EVERY));
        $this->tracks->countPlay($track);

        return true;
    }

    public function isRobot(?string $userAgent): bool
    {
        if (null === $this->classifier || !method_exists($this->classifier, 'classify')) {
            return '' === trim((string) $userAgent);
        }

        return 'human' !== $this->classifier->classify($userAgent);
    }
}
