<?php

namespace Base\Music\Tests\Repository;

use Base\Music\Entity\Work;
use Base\Music\Enum\Formation;
use Base\Music\Enum\Period;
use Base\Music\Repository\WorkRepository;
use PHPUnit\Framework\TestCase;

/** The repertoire's grouping (WorkRepository::group(), what findVisibleGrouped() returns), with no database. */
final class WorkGroupTest extends TestCase
{
    /** @return list<Work> */
    private static function works(): array
    {
        return [
            (new Work())->setComposer('César Franck')->setTitle('Sonata in A major')->setOpus('FWV 8')->setFormation(Formation::CHAMBER)->setPeriod(Period::ROMANTIC),
            (new Work())->setComposer('Reinhold Glière')->setTitle('Harp Concerto in E-flat major')->setOpus('op. 74')->setFormation(Formation::CONCERTO)->setPeriod(Period::MODERN),
            (new Work())->setComposer('Richard Strauss')->setTitle('Sonata in E-flat major')->setOpus('op. 18')->setFormation(Formation::CHAMBER)->setPeriod(Period::ROMANTIC),
            (new Work())->setComposer('Carl Philipp Emanuel Bach')->setTitle('Sonata in G major')->setFormation(Formation::SOLO),
        ];
    }

    public function testByFormationInTheFormationsOrderAndComposersBySurname(): void
    {
        $groups = WorkRepository::group(self::works());

        self::assertSame(['solo', 'concerto', 'chamber'], array_keys($groups));
        self::assertSame(['César Franck', 'Richard Strauss'], array_keys($groups['chamber']));
    }

    public function testByComposerBySurname(): void
    {
        self::assertSame(['Carl Philipp Emanuel Bach', 'César Franck', 'Reinhold Glière', 'Richard Strauss'], array_keys(WorkRepository::group(self::works(), 'composer')));
    }

    public function testByPeriodOldestFirstTheUndatedLast(): void
    {
        self::assertSame(['romantic', 'modern', ''], array_keys(WorkRepository::group(self::works(), 'period')));
    }

    public function testAnUnknownGroupingIsByFormation(): void
    {
        self::assertSame(array_keys(WorkRepository::group(self::works())), array_keys(WorkRepository::group(self::works(), 'colour')));
    }
}
