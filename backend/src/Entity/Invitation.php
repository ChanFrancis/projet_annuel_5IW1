<?php

namespace App\Entity;

use App\Enum\AccountRole;
use App\Enum\InvitationStatus;
use App\Repository\InvitationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Email invitation to join an account with a given role.
 * Only the SHA-256 hash of the token is stored; the raw token is emailed once.
 */
#[ORM\Entity(repositoryClass: InvitationRepository::class)]
#[ORM\Table(name: 'invitations')]
#[ORM\Index(name: 'idx_invitation_hash', columns: ['token_hash'])]
class Invitation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Account $account;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 20, enumType: AccountRole::class)]
    private AccountRole $role;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column(length: 20, enumType: InvitationStatus::class)]
    private InvitationStatus $status = InvitationStatus::PENDING;

    #[ORM\Column(type: 'uuid')]
    private Uuid $invitedBy;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Account $account, string $email, AccountRole $role, string $tokenHash, Uuid $invitedBy, \DateTimeImmutable $expiresAt)
    {
        $this->id = Uuid::v7();
        $this->account = $account;
        $this->email = strtolower(trim($email));
        $this->role = $role;
        $this->tokenHash = $tokenHash;
        $this->invitedBy = $invitedBy;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRole(): AccountRole
    {
        return $this->role;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getStatus(): InvitationStatus
    {
        return $this->status;
    }

    public function setStatus(InvitationStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getInvitedBy(): Uuid
    {
        return $this->invitedBy;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isPending(): bool
    {
        return InvitationStatus::PENDING === $this->status && !$this->isExpired();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
