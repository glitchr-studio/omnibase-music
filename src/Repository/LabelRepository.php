<?php

namespace Base\Music\Repository;

use Base\Music\Entity\Label;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Label> */
class LabelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Label::class);
    }

    /** The label a catalogue names, whatever the case it writes it in ("Es-Dur", "ES-DUR"). */
    public function findOneByName(string $name): ?Label
    {
        return $this->createQueryBuilder('l')
            ->andWhere('LOWER(l.name) = :name')->setParameter('name', mb_strtolower(trim($name)))
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}
