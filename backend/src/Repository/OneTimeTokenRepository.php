<?php

namespace App\Repository;

use App\Entity\OneTimeToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OneTimeToken>
 */
class OneTimeTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OneTimeToken::class);
    }

    public function findUsableByHash(string $tokenHash, string $type): ?OneTimeToken
    {
        $token = $this->findOneBy(['tokenHash' => $tokenHash, 'type' => $type]);

        return $token && $token->isUsable() ? $token : null;
    }
}
