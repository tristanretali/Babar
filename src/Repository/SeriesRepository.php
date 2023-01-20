<?php

namespace App\Repository;

use App\Entity\Series;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Series>
 *
 * @method Series|null find($id, $lockMode = null, $lockVersion = null)
 * @method Series|null findOneBy(array $criteria, array $orderBy = null)
 * @method Series[]    findAll()
 * @method Series[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Series::class);
    }

    public function save(Series $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Series $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Using a specific list of series given in parameter, returns the total number of episodes
     * for each seriies given into an associative array, with this structure
     *      Series ID => Episode count
     * @return array An associative array of series IDs with their total episode count
     */
    public function getSeriesTotalEpCount(mixed $series): array
    {
        $series_total_epcount_query = $this
            ->getEntityManager()
            ->createQuery("SELECT ser.id, COUNT('ss.episodes') FROM \App\Entity\Series ser
                           JOIN ser.seasons ss WITH ser.id IN (:displayed_series)
                           JOIN ss.episodes eps
                           GROUP BY ser.id")
            ->setParameter('displayed_series', $series)
        ;
        
        $series_total_epcount_overlayed = $series_total_epcount_query->getResult();

        // Note : Every way possible has been tried to not get a weirdly mapped result
        // so the only final way was to unpack the result into a nice looking associative array,
        // where the key is the series id, and the value is the total episode count
        //  - Wanchai
        $series_total_epcount_map = array();
        foreach ($series_total_epcount_overlayed as $arr) {
            $id = $arr["id"];
            $total_ep_count = $arr[1];
            $series_total_epcount_map[$id] = $total_ep_count;
        }
        
        foreach ($series as $s) {
            $id = $s->getId();
            if (!isset($series_total_epcount_map[$id])) {
                $series_total_epcount_map[$id] = null;
            }
        }
        return $series_total_epcount_map;
    }

}
