<?php

namespace App\Repository;

use App\Entity\Application;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Application>
 */
class ApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Application::class);
    }

    public function findAppropriate(Application $application)
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.stock_id = :stock_id')
            ->andWhere('a.quantity = :quantity')
            ->andWhere('a.price = :price')
            ->andWhere('a.action = :action')
            ->andWhere('a.user_id != :user_id')
            // ->andWhere('a.portfolio_id NOT IN (:portfolios')
            ->setParameters(
                new ArrayCollection([
                    'stock_id' => $application->getStock(),
                    'quantity' => $application->getQuantity(),
                    'price' => $application->getPrice(),
                    'action' => $application->getAction()->getOpposite()->value,
                    'user_id' => $application->getUser()->getId(),
                    // 'portfolios' => $application->getPortfolio()->getUser()->getPortfolios()
                ])
            )
            ->getQuery()
            ->getOneOrNullResult();

    }

    public function saveApplication(Application $application)
    {
        $this->getEntityManager()->persist($application);
        $this->getEntityManager()->flush();
    }

    public function removeApplication(Application $application)
    {
        $this->getEntityManager()->remove($application);
        $this->getEntityManager()->flush();
    }

    public function remove(Application $application, bool $flush = false): void
    {
        $this->getEntityManager()->remove($application);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    //    /**
//     * @return Application[] Returns an array of Application objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

    //    public function findOneBySomeField($value): ?Application
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
