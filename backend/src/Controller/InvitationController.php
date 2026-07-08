<?php

namespace App\Controller;

use App\Entity\Account;
use App\Entity\AccountUser;
use App\Entity\Invitation;
use App\Entity\User;
use App\Enum\AccountRole;
use App\Enum\InvitationStatus;
use App\Repository\AccountRepository;
use App\Repository\AccountUserRepository;
use App\Repository\InvitationRepository;
use App\Security\AccountVoter;
use App\Service\AuditLogger;
use App\Service\JsonPresenter;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api')]
class InvitationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountRepository $accounts,
        private readonly InvitationRepository $invitations,
        private readonly AccountUserRepository $memberships,
        private readonly JsonPresenter $presenter,
        private readonly MailService $mail,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('/accounts/{id}/invitations', name: 'invitation_list', methods: ['GET'])]
    public function list(string $id): JsonResponse
    {
        $account = $this->fetchAccount($id);
        $this->denyAccessUnlessGranted(AccountVoter::MANAGE, $account);

        $items = array_map(
            fn (Invitation $i) => $this->presenter->invitation($i),
            $this->invitations->findBy(['account' => $account], ['createdAt' => 'DESC']),
        );

        return $this->json(['invitations' => $items]);
    }

    #[Route('/accounts/{id}/invitations', name: 'invitation_create', methods: ['POST'])]
    public function invite(string $id, Request $request): JsonResponse
    {
        $account = $this->fetchAccount($id);
        $this->denyAccessUnlessGranted(AccountVoter::MANAGE, $account);

        $data = $this->decode($request);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $role = AccountRole::tryFrom((string) ($data['role'] ?? 'viewer')) ?? AccountRole::VIEWER;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Email invalide.'], 422);
        }
        if (AccountRole::OWNER === $role) {
            return $this->json(['error' => 'On ne peut pas inviter en tant que propriétaire.'], 422);
        }

        $raw = bin2hex(random_bytes(32));
        $invitation = new Invitation(
            $account,
            $email,
            $role,
            hash('sha256', $raw),
            $this->uid(),
            new \DateTimeImmutable('+7 days'),
        );
        $this->em->persist($invitation);
        $this->em->flush();

        $this->mail->sendInvitation($email, $raw, $account->getLabel());
        $this->audit->log('invitation.create', $this->uid(), 'Account', $account->getId(), ['email' => $email, 'role' => $role->value]);

        return $this->json($this->presenter->invitation($invitation), 201);
    }

    #[Route('/invitations/{token}', name: 'invitation_show', methods: ['GET'])]
    public function show(string $token): JsonResponse
    {
        $invitation = $this->invitations->findPendingByHash(hash('sha256', $token));
        if (!$invitation) {
            return $this->json(['error' => 'Invitation invalide ou expirée.'], 404);
        }

        return $this->json([
            'accountLabel' => $invitation->getAccount()->getLabel(),
            'email' => $invitation->getEmail(),
            'role' => $invitation->getRole()->value,
        ]);
    }

    #[Route('/invitations/accept', name: 'invitation_accept', methods: ['POST'])]
    public function accept(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $token = (string) ($this->decode($request)['token'] ?? '');
        $invitation = $this->invitations->findPendingByHash(hash('sha256', $token));
        if (!$invitation) {
            return $this->json(['error' => 'Invitation invalide ou expirée.'], 404);
        }
        if ($invitation->getEmail() !== $user->getEmail()) {
            return $this->json(['error' => 'Cette invitation ne correspond pas à votre compte.'], 403);
        }

        $account = $invitation->getAccount();
        if (!$this->memberships->findMembership($account, $user)) {
            $account->addMember(new AccountUser($user, $invitation->getRole()));
        }
        $invitation->setStatus(InvitationStatus::ACCEPTED);
        $this->em->flush();
        $this->audit->log('invitation.accept', $user->getId(), 'Account', $account->getId());

        return $this->json($this->presenter->account($account, withMembers: true));
    }

    #[Route('/invitations/decline', name: 'invitation_decline', methods: ['POST'])]
    public function decline(Request $request): JsonResponse
    {
        $token = (string) ($this->decode($request)['token'] ?? '');
        $invitation = $this->invitations->findPendingByHash(hash('sha256', $token));
        if (!$invitation) {
            return $this->json(['error' => 'Invitation invalide ou expirée.'], 404);
        }
        $invitation->setStatus(InvitationStatus::DECLINED);
        $this->em->flush();

        return $this->json(['message' => 'Invitation refusée.']);
    }

    private function fetchAccount(string $id): Account
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $account = $this->accounts->find(Uuid::fromString($id));
        if (!$account) {
            throw $this->createNotFoundException('Compte introuvable.');
        }

        return $account;
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
