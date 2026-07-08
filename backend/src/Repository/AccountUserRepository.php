<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\AccountUser;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountUser>
 */
class AccountUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountUser::class);
    }

    public function findMembership(Account $account, User $user): ?AccountUser
    {
        return $this->findOneBy(['account' => $account, 'user' => $user]);
    }
}
