<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Budget;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Budget>
 */
class BudgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Budget::class);
    }

    /**
     * @return Budget[]
     */
    public function findForAccountAndMonth(Account $account, string $month): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.account = :account')
            ->andWhere('b.month = :month')
            ->setParameter('account', $account)
            ->setParameter('month', $month)
            ->orderBy('b.amount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOne(Account $account, Category $category, string $month): ?Budget
    {
        return $this->createQueryBuilder('b')
            ->where('b.account = :account')
            ->andWhere('b.category = :category')
            ->andWhere('b.month = :month')
            ->setParameter('account', $account)
            ->setParameter('category', $category)
            ->setParameter('month', $month)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(Budget $budget, bool $flush = true): void
    {
        $this->getEntityManager()->persist($budget);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
