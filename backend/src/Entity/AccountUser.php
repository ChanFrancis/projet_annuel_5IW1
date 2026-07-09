<?php

namespace App\Entity;

use App\Enum\AccountRole;
use App\Repository\AccountUserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Membership link between a User and an Account, carrying the user's role
 * (owner / co_owner / viewer) on that account.
 */
#[ORM\Entity(repositoryClass: AccountUserRepository::class)]
#[ORM\Table(name: 'account_users')]
#[ORM\UniqueConstraint(name: 'uniq_account_user', columns: ['account_id', 'user_id'])]
class AccountUser
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 20, enumType: AccountRole::class)]
    private AccountRole $role;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, AccountRole $role)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->role = $role;
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

    public function setAccount(Account $account): self
    {
        $this->account = $account;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRole(): AccountRole
    {
        return $this->role;
    }

    public function setRole(AccountRole $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
