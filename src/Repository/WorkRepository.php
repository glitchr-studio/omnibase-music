<?php

namespace Base\Music\Repository;

use Base\Music\Entity\Work;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Work> */
class WorkRepository extends ServiceEntityRepository
{
    public const BY_FORMATION = 'formation';
    public const BY_COMPOSER = 'composer';
    public const BY_PERIOD = 'period';
    public const GROUPS = [self::BY_FORMATION, self::BY_COMPOSER, self::BY_PERIOD];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Work::class);
    }

    /** @return list<Work> */
    public function findVisible(): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.visible = true')
            ->orderBy('w.position', 'ASC')->addOrderBy('w.year', 'ASC')->addOrderBy('w.title', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * The repertoire as its page reads it: groups, and in each the
     * composers (by surname) with their works. By formation the groups are
     * the Formation values in their own order (solo, concerto, chamber...);
     * by period the Period values, oldest first, the undated last under '';
     * by composer each composer is a group.
     *
     * @return array<string, array<string, list<Work>>> group => composer => works
     */
    public function findVisibleGrouped(string $by = self::BY_FORMATION): array
    {
        return self::group($this->findVisible(), $by);
    }

    /**
     * @param iterable<Work> $works
     *
     * @return array<string, array<string, list<Work>>>
     */
    public static function group(iterable $works, string $by = self::BY_FORMATION): array
    {
        $by = \in_array($by, self::GROUPS, true) ? $by : self::BY_FORMATION;
        $groups = [];
        $ranks = [];
        $sortKeys = [];
        foreach ($works as $work) {
            $composer = (string) $work->getComposer();
            $sortKeys[$composer] = $work->getComposerSortKey();
            [$key, $rank] = match ($by) {
                self::BY_COMPOSER => [$composer, 0],
                self::BY_PERIOD => [$work->getPeriod()?->value ?? '', $work->getPeriod()?->rank() ?? 99],
                default => [$work->getFormation()->value, $work->getFormation()->rank()],
            };
            $ranks[$key] = $rank;
            $groups[$key][$composer][] = $work;
        }

        $byComposer = static fn (string $a, string $b): int => strcoll($sortKeys[$a] ?? $a, $sortKeys[$b] ?? $b);
        foreach ($groups as &$composers) {
            uksort($composers, $byComposer);
        }
        unset($composers);
        uksort($groups, static fn (string $a, string $b): int => self::BY_COMPOSER === $by
            ? $byComposer($a, $b)
            : [$ranks[$a], $a] <=> [$ranks[$b], $b]);

        return $groups;
    }
}
