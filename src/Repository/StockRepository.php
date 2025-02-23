<?php

namespace App\Repository;

use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    //    /**
    //     * @return Stock[] Returns an array of Stock objects
    //     */
    public function findById(int $stockId): ?Stock
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.id = :stockId')
            ->setParameter('stockId', $stockId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
