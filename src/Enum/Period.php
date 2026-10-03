<?php

namespace Base\Music\Enum;

/** When a work was written, in the broad strokes a programme uses. */
enum Period: string
{
    case BAROQUE = 'baroque';
    case CLASSICAL = 'classical';
    case ROMANTIC = 'romantic';
    case MODERN = 'modern';
    case CONTEMPORARY = 'contemporary';

    /** Oldest first. */
    public function rank(): int
    {
        return match ($this) {
            self::BAROQUE => 0, self::CLASSICAL => 1, self::ROMANTIC => 2,
            self::MODERN => 3, self::CONTEMPORARY => 4,
        };
    }
}
