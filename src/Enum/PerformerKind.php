<?php

namespace Base\Music\Enum;

/** Who plays with the musician: a person, an ensemble, an orchestra, the one who conducts. */
enum PerformerKind: string
{
    case PERSON = 'person';
    case ENSEMBLE = 'ensemble';
    case ORCHESTRA = 'orchestra';
    case CONDUCTOR = 'conductor';

    /** schema.org's type, for the JSON-LD. */
    public function schema(): string
    {
        return match ($this) {
            self::PERSON, self::CONDUCTOR => 'Person',
            default => 'MusicGroup',
        };
    }
}
