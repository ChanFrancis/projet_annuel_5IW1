<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'idx_audit_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_audit_action', columns: ['action'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $userId = null;

    #[ORM\Column(length: 100)]
    private string $action;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $entity = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $entityId = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    /** @var array<string,mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $context = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $action)
    {
        $this->id = Uuid::v7();
        $this->action = $action;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUserId(): ?Uuid
    {
        return $this->userId;
    }

    public function setUserId(?Uuid $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(?string $entity, ?Uuid $entityId = null): self
    {
        $this->entity = $entity;
        $this->entityId = $entityId;

        return $this;
    }

    public function getEntityId(): ?Uuid
    {
        return $this->entityId;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    /** @param array<string,mixed>|null $context */
    public function setContext(?array $context): self
    {
        $this->context = $context;

        return $this;
    }

    /** @return array<string,mixed>|null */
    public function getContext(): ?array
    {
        return $this->context;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
