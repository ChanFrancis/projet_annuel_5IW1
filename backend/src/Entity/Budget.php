<?php

namespace App\Entity;

use App\Repository\BudgetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Monthly spending ceiling for a (account, category, month) triplet.
 * Compared against actual transaction sums by the statistics layer.
 */
#[ORM\Entity(repositoryClass: BudgetRepository::class)]
#[ORM\Table(name: 'budgets')]
#[ORM\UniqueConstraint(name: 'budget_unique', columns: ['account_id', 'category_id', 'month'])]
#[ORM\Index(name: 'idx_budget_account_month', columns: ['account_id', 'month'])]
class Budget
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'budgets')]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Category $category;

    /** Spending month as "YYYY-MM". */
    #[ORM\Column(length: 7)]
    #[Assert\NotBlank]
    private string $month;

    /** Ceiling amount as a positive decimal string. */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    #[Assert\NotBlank]
    private string $amount;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Account $account, Category $category, string $month)
    {
        $this->id = Uuid::v7();
        $this->account = $account;
        $this->category = $category;
        $this->month = $month;
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

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function getMonth(): string
    {
        return $this->month;
    }

    public function setMonth(string $month): self
    {
        $this->month = $month;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
