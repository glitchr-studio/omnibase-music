<?php

namespace Base\Music\Repository;

use Base\Music\Entity\Track;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Track> */
class TrackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Track::class);
    }

    /** The tracks the player has nothing to play for: no excerpt of the site's, no preview. */
    public function countSilent(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.sample IS NULL')->andWhere('t.previewUrl IS NULL')
            ->getQuery()->getSingleScalarResult();
    }

    /** @return list<Track> those with an excerpt of the site's - all of them, or only the ones whose waveform is missing */
    public function findWithSample(bool $onlyWithoutPeaks = false): array
    {
        $query = $this->createQueryBuilder('t')->andWhere('t.sample IS NOT NULL')->orderBy('t.id', 'ASC');
        if ($onlyWithoutPeaks) {
            $query->andWhere('t.peaks IS NULL');
        }

        return $query->getQuery()->getResult();
    }
}
