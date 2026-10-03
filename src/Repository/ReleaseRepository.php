<?php

namespace Base\Music\Repository;

use Base\Enum\ThreadState;
use Base\Music\Entity\Release;
use Base\Music\Enum\ReleaseType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Release> */
class ReleaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Release::class);
    }

    /**
     * The discography: every published release, the newest first - the
     * announced ones included, they say so themselves.
     *
     * @return list<Release>
     */
    public function findPublished(): array
    {
        return $this->newestFirst($this->published())->getQuery()->getResult();
    }

    /**
     * The last ones out (the announced ones left aside); of one type when
     * asked - a home page shows the albums, not the singles between them.
     *
     * @return list<Release>
     */
    public function findLatest(int $limit = 3, ?ReleaseType $type = null): array
    {
        $query = $this->newestFirst($this->out());
        if (null !== $type) {
            $query->andWhere('r.type = :type')->setParameter('type', $type);
        }

        return $query
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /** The featured release, or the newest one out. */
    public function findFeatured(): ?Release
    {
        return $this->out()
            ->orderBy('r.featured', 'DESC')->addOrderBy('r.releasedAt', 'DESC')->addOrderBy('r.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    public function findOnePublished(string $slug): ?Release
    {
        return $this->published()
            ->andWhere('r.slug = :slug')->setParameter('slug', $slug)
            ->getQuery()->getOneOrNullResult();
    }

    /** @return list<Release> announced or dated in the future, the nearest first */
    public function findUpcoming(): array
    {
        return $this->published()
            ->andWhere('r.upcoming = true OR r.releasedAt > :today')->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('r.releasedAt', 'ASC')->addOrderBy('r.id', 'ASC')
            ->getQuery()->getResult();
    }

    private function newestFirst(QueryBuilder $query): QueryBuilder
    {
        return $query->orderBy('r.releasedAt', 'DESC')->addOrderBy('r.publishedAt', 'DESC')->addOrderBy('r.id', 'DESC');
    }

    private function out(): QueryBuilder
    {
        return $this->published()
            ->andWhere('r.upcoming = false')
            ->andWhere('r.releasedAt IS NULL OR r.releasedAt <= :today')->setParameter('today', new \DateTimeImmutable('today'));
    }

    private function published(): QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.state = :published')->setParameter('published', ThreadState::PUBLISH)
            ->andWhere('r.publishedAt IS NULL OR r.publishedAt <= CURRENT_TIMESTAMP()');
    }
}
