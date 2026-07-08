<?php

namespace App\Service;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * Centralised, append-only audit trail. Every security- and money-sensitive
 * action goes through here so the admin journal stays complete.
 */
class AuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string,mixed>|null $context
     */
    public function log(
        string $action,
        ?Uuid $userId = null,
        ?string $entity = null,
        ?Uuid $entityId = null,
        ?array $context = null,
        bool $flush = true,
    ): void {
        $log = new AuditLog($action);
        $log->setUserId($userId)
            ->setEntity($entity, $entityId)
            ->setContext($context)
            ->setIp($this->requestStack->getCurrentRequest()?->getClientIp());

        $this->em->persist($log);
        if ($flush) {
            $this->em->flush();
        }
    }
}
