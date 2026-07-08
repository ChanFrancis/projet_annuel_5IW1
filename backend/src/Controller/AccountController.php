<?php

namespace App\Controller;

use App\Entity\Account;
use App\Entity\AccountUser;
use App\Entity\User;
use App\Enum\AccountRole;
use App\Enum\AccountType;
use App\Repository\AccountRepository;
use App\Repository\AccountUserRepository;
use App\Security\AccountVoter;
use App\Service\AuditLogger;
use App\Service\IbanGenerator;
use App\Service\JsonPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/accounts')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountRepository $accounts,
        private readonly AccountUserRepository $memberships,
        private readonly IbanGenerator $ibanGenerator,
        private readonly JsonPresenter $presenter,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('', name: 'account_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $accounts = array_map(
            fn (Account $a) => $this->presenter->account($a),
            $this->accounts->findForUser($user),
        );

        return $this->json(['accounts' => $accounts]);
    }

    #[Route('', name: 'account_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $this->decode($request);

        $type = AccountType::tryFrom((string) ($data['type'] ?? ''));
        $label = trim((string) ($data['label'] ?? ''));
        if ('' === $label || null === $type) {
            return $this->json(['error' => 'Libellé et type valides requis.'], 422);
        }

        $account = new Account();
        $account->setLabel($label);
        $account->setType($type);
        $account->setCurrency((string) ($data['currency'] ?? 'EUR'));
        $account->setCreatedBy($user);
        $account->setIban($this->uniqueIban());

        // The creator becomes the owner.
        $account->addMember(new AccountUser($user, AccountRole::OWNER));

        $this->accounts->save($account);
        $this->audit->log('account.create', $user->getId(), 'Account', $account->getId());

        return $this->json($this->presenter->account($account, withMembers: true), 201);
    }

    #[Route('/{id}', name: 'account_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $account = $this->fetch($id);
        $this->denyAccessUnlessGranted(AccountVoter::VIEW, $account);

        return $this->json($this->presenter->account($account, withMembers: true));
    }

    #[Route('/{id}', name: 'account_update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $account = $this->fetch($id);
        $this->denyAccessUnlessGranted(AccountVoter::MANAGE, $account);

        $data = $this->decode($request);
        if (isset($data['label']) && '' !== trim((string) $data['label'])) {
            $account->setLabel(trim((string) $data['label']));
        }
        if (isset($data['type']) && $type = AccountType::tryFrom((string) $data['type'])) {
            $account->setType($type);
        }
        $this->em->flush();
        $this->audit->log('account.update', $this->uid(), 'Account', $account->getId());

        return $this->json($this->presenter->account($account, withMembers: true));
    }

    #[Route('/{id}', name: 'account_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $account = $this->fetch($id);
        $this->denyAccessUnlessGranted(AccountVoter::MANAGE, $account);

        $this->audit->log('account.delete', $this->uid(), 'Account', $account->getId());
        $this->em->remove($account);
        $this->em->flush();

        return $this->json(null, 204);
    }

    // ---- Members ----

    #[Route('/{id}/members/{memberId}', name: 'account_member_update', methods: ['PATCH'])]
    public function updateMember(string $id, string $memberId, Request $request): JsonResponse
    {
        $account = $this->fetch($id);
        $this->denyAccessUnlessGranted(AccountVoter::MANAGE, $account);

        $member = $this->memberships->find($this->asUuid($memberId));
        if (!$member || $member->getAccount()->getId() != $account->getId()) {
            return $this->json(['error' => 'Membre introuvable.'], 404);
        }
        $role = AccountRole::tryFrom((string) ($this->decode($request)['role'] ?? ''));
        if (null === $role) {
            return $this->json(['error' => 'Rôle invalide.'], 422);
        }
        if (AccountRole::OWNER === $member->getRole() && AccountRole::OWNER !== $role && $this->countOwners($account) <= 1) {
            return $this->json(['error' => 'Le compte doit garder au moins un propriétaire.'], 409);
        }

        $member->setRole($role);
        $this->em->flush();
        $this->audit->log('account.member_role', $this->uid(), 'Account', $account->getId(), ['member' => $memberId, 'role' => $role->value]);

        return $this->json($this->presenter->member($member));
    }

    #[Route('/{id}/members/{memberId}', name: 'account_member_remove', methods: ['DELETE'])]
    public function removeMember(string $id, string $memberId): JsonResponse
    {
        $account = $this->fetch($id);
        $this->denyAccessUnlessGranted(AccountVoter::MANAGE, $account);

        $member = $this->memberships->find($this->asUuid($memberId));
        if (!$member || $member->getAccount()->getId() != $account->getId()) {
            return $this->json(['error' => 'Membre introuvable.'], 404);
        }
        if (AccountRole::OWNER === $member->getRole() && $this->countOwners($account) <= 1) {
            return $this->json(['error' => 'Impossible de retirer le dernier propriétaire.'], 409);
        }

        $this->em->remove($member);
        $this->em->flush();
        $this->audit->log('account.member_remove', $this->uid(), 'Account', $account->getId(), ['member' => $memberId]);

        return $this->json(null, 204);
    }

    // ---- helpers ----

    private function fetch(string $id): Account
    {
        $account = $this->accounts->find($this->asUuid($id));
        if (!$account) {
            throw $this->createNotFoundException('Compte introuvable.');
        }

        return $account;
    }

    private function uniqueIban(): string
    {
        do {
            $iban = $this->ibanGenerator->generate();
        } while ($this->accounts->existsByIban($iban));

        return $iban;
    }

    private function countOwners(Account $account): int
    {
        $n = 0;
        foreach ($account->getMembers() as $m) {
            if (AccountRole::OWNER === $m->getRole()) {
                ++$n;
            }
        }

        return $n;
    }

    private function asUuid(string $id): Uuid
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Identifiant invalide.');
        }

        return Uuid::fromString($id);
    }

    private function uid(): Uuid
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user->getId();
    }

    /** @return array<string,mixed> */
    private function decode(Request $request): array
    {
        return json_decode($request->getContent() ?: '{}', true) ?? [];
    }
}
