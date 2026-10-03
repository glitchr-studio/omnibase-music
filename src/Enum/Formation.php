<?php

namespace Base\Music\Enum;

/** What a work is written for: the first way the repertoire is grouped. */
enum Formation: string
{
    case SOLO = 'solo';
    case CHAMBER = 'chamber';
    case CONCERTO = 'concerto';
    case ORCHESTRA = 'orchestra';
    case VOCAL = 'vocal';
    case OTHER = 'other';

    /** The order of the repertoire page: alone first, then with others. */
    public function rank(): int
    {
        return match ($this) {
            self::SOLO => 0, self::CONCERTO => 1, self::CHAMBER => 2,
            self::ORCHESTRA => 3, self::VOCAL => 4, self::OTHER => 9,
        };
    }
}
