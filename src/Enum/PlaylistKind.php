<?php

namespace Base\Music\Enum;

/**
 * What a playlist is: the musician's own records, the musician's picks
 * (colleagues, recordings loved), or the musician's profile on a platform
 * (its top tracks) - the one "Listen in full" opens first.
 */
enum PlaylistKind: string
{
    case OWN = 'own';
    case RECOMMENDED = 'recommended';
    case ARTIST = 'artist';

    /** The order of the /music page: the profile, the records, the picks. */
    public function rank(): int
    {
        return match ($this) {
            self::ARTIST => 0, self::OWN => 1, self::RECOMMENDED => 2,
        };
    }
}
