<?php

namespace App\Entity;

use App\Repository\OneTimeTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One-time tokens for password reset and magic-link login.
 * Only the SHA-256 hash of the token is stored; the raw value is emailed once.
 */
#[ORM\Entity(repositoryClass: OneTimeTokenRepository::class)]
#[ORM\Table(name: 'one_time_tokens')]
#[ORM\Index(name: 'idx_ott_hash', columns: ['token_hash'])]
class OneTimeToken
{
    public const TYPE_PASSWORD_RESET = 'password_reset';
    public const TYPE_MAGIC_LINK = 'magic_link';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'uuid')]
    private Uuid $userId;

    #[ORM\Column(length: 30)]
    private string $type;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Uuid $userId, string $type, string $tokenHash, \DateTimeImmutable $expiresAt)
    {
        $this->id = Uuid::v7();
        $this->userId = $userId;
        $this->type = $type;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function markUsed(): self
    {
        $this->usedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isUsable(): bool
    {
        return null === $this->usedAt && !$this->isExpired();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
