<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    /**
     * All accounts the user is a member of (any role), newest first.
     *
     * @return Account[]
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.members', 'm')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function existsByIban(string $iban): bool
    {
        return (bool) $this->count(['iban' => $iban]);
    }

    public function save(Account $account, bool $flush = true): void
    {
        $this->getEntityManager()->persist($account);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
