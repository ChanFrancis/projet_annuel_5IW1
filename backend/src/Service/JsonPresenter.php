<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\AccountUser;
use App\Entity\Category;
use App\Entity\Invitation;
use App\Entity\Transaction;
use App\Repository\TransactionRepository;

/**
 * Turns domain entities into plain arrays for JSON responses.
 * Kept in one place so the API shape stays consistent across controllers.
 */
class JsonPresenter
{
    public function __construct(private readonly TransactionRepository $transactions)
    {
    }

    /** @return array<string,mixed> */
    public function account(Account $a, bool $withMembers = false): array
    {
        $data = [
            'id' => (string) $a->getId(),
            'label' => $a->getLabel(),
            'type' => $a->getType()->value,
            'typeLabel' => $a->getType()->label(),
            'currency' => $a->getCurrency(),
            'iban' => $a->getIban(),
            'balance' => $this->transactions->getBalance($a),
            'createdAt' => $a->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($withMembers) {
            $data['members'] = array_map(
                fn (AccountUser $m) => $this->member($m),
                $a->getMembers()->toArray(),
            );
        }

        return $data;
    }

    /** @return array<string,mixed> */
    public function member(AccountUser $m): array
    {
        return [
            'id' => (string) $m->getId(),
            'userId' => (string) $m->getUser()->getId(),
            'email' => $m->getUser()->getEmail(),
            'role' => $m->getRole()->value,
        ];
    }

    /** @return array<string,mixed> */
    public function transaction(Transaction $t): array
    {
        return [
            'id' => (string) $t->getId(),
            'accountId' => (string) $t->getAccount()->getId(),
            'date' => $t->getDate()->format('Y-m-d'),
            'amount' => $t->getAmount(),
            'label' => $t->getLabel(),
            'category' => $t->getCategory() ? $this->category($t->getCategory()) : null,
            'attachmentUrl' => $t->getAttachmentUrl(),
            'createdAt' => $t->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string,mixed> */
    public function category(Category $c): array
    {
        return [
            'id' => (string) $c->getId(),
            'name' => $c->getName(),
            'parentId' => $c->getParent() ? (string) $c->getParent()->getId() : null,
        ];
    }

    /** @return array<string,mixed> */
    public function invitation(Invitation $i): array
    {
        return [
            'id' => (string) $i->getId(),
            'accountId' => (string) $i->getAccount()->getId(),
            'email' => $i->getEmail(),
            'role' => $i->getRole()->value,
            'status' => $i->getStatus()->value,
            'expiresAt' => $i->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
