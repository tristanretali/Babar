<?php

namespace App\Repository;

use App\Entity\Rating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rating>
 *
 * @method Rating|null find($id, $lockMode = null, $lockVersion = null)
 * @method Rating|null findOneBy(array $criteria, array $orderBy = null)
 * @method Rating[]    findAll()
 * @method Rating[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rating::class);
    }

    public function save(Rating $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Rating $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getApprovedAndNonLogicallyRatingsOfSeriesFilteredByRange(int $min, int $max, int $series_id): array
    {
        $ratings = $this
            ->getEntityManager()
            ->createQuery("
                    SELECT r FROM \App\Entity\Rating r
                    WHERE r.value <= (:max)
                    AND r.value >= (:min)
                    AND r.series = (:series_id)
                    AND r.approved = 1
                    AND r.deleted = 0")
            ->setParameters([
                'min' => $min,
                'max' => $max,
                'series_id' => $series_id,
            ])
            ->getResult()
        ;

        return $ratings;
    }
}
