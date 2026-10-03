<?php

namespace Base\Music\Service;

use Base\Service\SettingBagInterface;

/**
 * The home page's picture as an administrator frames it on the page: the
 * point of the picture kept in view (an object-position, "52% 31%") and
 * how close (1 to 2.5). Two settings, music.hero.position and
 * music.hero.zoom; null puts the host's own framing back.
 */
final class HeroFrame
{
    public const POSITION = '/^\d{1,3}(\.\d{1,2})?% \d{1,3}(\.\d{1,2})?%$/';
    public const MAX_ZOOM = 2.5;

    public function __construct(private readonly SettingBagInterface $settings)
    {
    }

    public function save(?string $position, ?float $zoom): void
    {
        if (null !== $position && !preg_match(self::POSITION, $position)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a position ("50% 30%").', $position));
        }
        if (null !== $zoom && ($zoom < 1 || $zoom > self::MAX_ZOOM)) {
            throw new \InvalidArgumentException(\sprintf('A zoom goes from 1 to %s.', self::MAX_ZOOM));
        }
        $this->settings->set('music.hero.position', $position);
        $this->settings->set('music.hero.zoom', null === $zoom || 1.0 === $zoom ? null : (string) round($zoom, 2));
    }

    public function reset(): void
    {
        $this->save(null, null);
    }
}
