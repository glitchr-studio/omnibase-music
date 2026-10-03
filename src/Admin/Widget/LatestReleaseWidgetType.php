<?php

namespace Base\Music\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Widget\DashboardWidgetTypeInterface;
use Base\Music\Repository\ReleaseRepository;
use Base\Music\Repository\TrackRepository;

/**
 * The dashboard's tile: the last record out, the next one announced, and
 * how many tracks the player has nothing to play for (no excerpt, no
 * preview). `yield MenuItem::block('music_latest', ...)` in the
 * dashboard's configureWidgetItems() places it.
 */
final class LatestReleaseWidgetType implements DashboardWidgetTypeInterface
{
    public function __construct(
        private readonly ReleaseRepository $releases,
        private readonly TrackRepository $tracks,
    ) {
    }

    public static function getName(): string
    {
        return 'music_latest';
    }

    public function getTemplate(): string
    {
        return '@Music/admin/widget/latest.html.twig';
    }

    public function getTemplateVars(MenuItem $widget): array
    {
        return [
            'latest' => $this->releases->findLatest(1)[0] ?? null,
            'upcoming' => $this->releases->findUpcoming()[0] ?? null,
            'silent' => $this->tracks->countSilent(),
        ];
    }
}
