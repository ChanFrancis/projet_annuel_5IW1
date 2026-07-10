<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * @return AuditLog[]
     */
    public function findRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Filterable journal query for the admin panel.
     *
     * @param array{userId?: ?string, action?: ?string, from?: ?\DateTimeImmutable, to?: ?\DateTimeImmutable} $filters
     *
     * @return AuditLog[]
     */
    public function search(array $filters, int $limit = 200): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(min($limit, 500));

        if (!empty($filters['action'])) {
            $qb->andWhere('a.action = :action')->setParameter('action', $filters['action']);
        }
        if (!empty($filters['userId'])) {
            $qb->andWhere('a.userId = :userId')->setParameter('userId', $filters['userId']);
        }
        if (!empty($filters['from'])) {
            $qb->andWhere('a.createdAt >= :from')->setParameter('from', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $qb->andWhere('a.createdAt <= :to')->setParameter('to', $filters['to']);
        }

        return $qb->getQuery()->getResult();
    }
}
