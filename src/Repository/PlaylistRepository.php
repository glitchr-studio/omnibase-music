<?php

namespace Base\Music\Repository;

use Base\Music\Entity\Playlist;
use Base\Music\Enum\PlaylistKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Playlist> */
class PlaylistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Playlist::class);
    }

    /** @return list<Playlist> the visible ones (of one kind), the featured first, then in their order */
    public function findVisible(?PlaylistKind $kind = null): array
    {
        $query = $this->createQueryBuilder('p')
            ->andWhere('p.visible = true')
            ->orderBy('p.featured', 'DESC')->addOrderBy('p.position', 'ASC')->addOrderBy('p.id', 'ASC');
        if ($kind) {
            $query->andWhere('p.kind = :kind')->setParameter('kind', $kind);
        }

        return $query->getQuery()->getResult();
    }

    /** The featured playlist (of one kind), or the first visible one. */
    public function findFeatured(?PlaylistKind $kind = null): ?Playlist
    {
        return $this->findVisible($kind)[0] ?? null;
    }
}
