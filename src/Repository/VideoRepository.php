<?php

namespace Base\Music\Repository;

use Base\Enum\ThreadState;
use Base\Music\Entity\Release;
use Base\Music\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Video> */
class VideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Video::class);
    }

    /** @return list<Video> the published ones, the featured first, then the newest */
    public function findPublished(?int $limit = null): array
    {
        return $this->published()
            ->orderBy('v.featured', 'DESC')->addOrderBy('v.recordedAt', 'DESC')->addOrderBy('v.publishedAt', 'DESC')->addOrderBy('v.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /** The featured video, or the newest one. */
    public function findFeatured(): ?Video
    {
        return $this->findPublished(1)[0] ?? null;
    }

    public function findOnePublished(string $slug): ?Video
    {
        return $this->published()
            ->andWhere('v.slug = :slug')->setParameter('slug', $slug)
            ->getQuery()->getOneOrNullResult();
    }

    /** @return list<Video> the films of one release */
    public function findByRelease(Release $release): array
    {
        return $this->published()
            ->andWhere('v.release = :release')->setParameter('release', $release)
            ->orderBy('v.recordedAt', 'DESC')->addOrderBy('v.id', 'DESC')
            ->getQuery()->getResult();
    }

    private function published(): QueryBuilder
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.state = :published')->setParameter('published', ThreadState::PUBLISH)
            ->andWhere('v.publishedAt IS NULL OR v.publishedAt <= CURRENT_TIMESTAMP()');
    }
}
