<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => strtolower(trim($email))]);
    }

    public function save(User $user, bool $flush = true): void
    {
        $this->getEntityManager()->persist($user);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return User[]
     */
    public function findAllOrdered(int $limit = 100, int $offset = 0): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /** Number of users who still hold ROLE_ADMIN (used to guard the last admin). */
    public function countAdmins(): int
    {
        $count = $this->getEntityManager()->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM users WHERE roles::jsonb @> '[\"ROLE_ADMIN\"]'::jsonb"
        );

        return (int) $count;
    }
}
