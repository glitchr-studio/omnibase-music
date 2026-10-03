<?php

namespace Base\Music\Repository;

use Base\Music\Entity\Instrument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Instrument> */
class InstrumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Instrument::class);
    }

    /** @return list<Instrument> the visible ones, the featured first */
    public function findVisible(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.visible = true')
            ->orderBy('i.featured', 'DESC')->addOrderBy('i.year', 'ASC')->addOrderBy('i.id', 'ASC')
            ->getQuery()->getResult();
    }

    /** The featured instrument, or the first visible one. */
    public function findFeatured(): ?Instrument
    {
        return $this->findVisible()[0] ?? null;
    }
}
