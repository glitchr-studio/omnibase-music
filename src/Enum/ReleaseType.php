<?php

namespace Base\Music\Enum;

/**
 * What a release is. A string enum (not omnibase's EnumType) so the column
 * reads plainly in the database and in the admin's filters.
 */
enum ReleaseType: string
{
    case ALBUM = 'album';
    case SINGLE = 'single';
    case EP = 'ep';
    case LIVE = 'live';
    case COMPILATION = 'compilation';

    /** schema.org's albumProductionType / albumReleaseType, for the JSON-LD. */
    public function schema(): string
    {
        return match ($this) {
            self::SINGLE => 'https://schema.org/SingleRelease',
            self::EP => 'https://schema.org/EPRelease',
            default => 'https://schema.org/AlbumRelease',
        };
    }

    /** The type a catalogue (Omnisong\Model\Release) names, to ours. */
    public static function fromCatalogue(?string $type): self
    {
        return self::tryFrom((string) $type) ?? self::ALBUM;
    }
}
