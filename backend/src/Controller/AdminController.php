<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use App\Service\JsonPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly AuditLogRepository $auditLogs,
        private readonly JsonPresenter $presenter,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('/users', name: 'admin_user_list', methods: ['GET'])]
    public function listUsers(): JsonResponse
    {
        $users = array_map(
            fn (User $u) => $this->presenter->adminUser($u),
            $this->users->findAllOrdered(),
        );

        return $this->json(['users' => $users]);
    }

    #[Route('/users/{id}', name: 'admin_user_update', methods: ['PATCH'])]
    public function updateUser(string $id, Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();
        $target = $this->fetchUser($id);

        if ($target->getId()->equals($admin->getId())) {
            return $this->json(['error' => 'Vous ne pouvez pas modifier votre propre compte.'], 422);
        }

        $data = $this->decode($request);

        if (\array_key_exists('banned', $data)) {
            $banned = (bool) $data['banned'];
            $target->setBanned($banned);
            $this->audit->log($banned ? 'admin.user_ban' : 'admin.user_unban', $admin->getId(), 'User', $target->getId());
        }

        if (\array_key_exists('admin', $data)) {
            $wantAdmin = (bool) $data['admin'];
            $isAdmin = \in_array('ROLE_ADMIN', $target->getRoles(), true);

            if (!$wantAdmin && $isAdmin && $this->users->countAdmins() <= 1) {
                return $this->json(['error' => 'Impossible de rétrograder le dernier administrateur.'], 409);
            }

            $roles = array_values(array_filter(
                $target->getRoles(),
                static fn (string $r) => 'ROLE_ADMIN' !== $r,
            ));
            if ($wantAdmin) {
                $roles[] = 'ROLE_ADMIN';
            }
            // getRoles() always appends ROLE_USER, so the array always keeps it.
            $target->setRoles(array_values(array_unique($roles)));
            $this->audit->log('admin.user_role_change', $admin->getId(), 'User', $target->getId(), ['admin' => $wantAdmin]);
        }

        $this->em->flush();

        return $this->json($this->presenter->adminUser($target));
    }

    #[Route('/users/{id}/2fa-reset', name: 'admin_user_2fa_reset', methods: ['POST'])]
    public function reset2fa(string $id): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();
        $target = $this->fetchUser($id);

        $target->setTotpSecret(null);
        $target->setTotpEnabled(false);
        $this->em->flush();
        $this->audit->log('admin.user_2fa_reset', $admin->getId(), 'User', $target->getId());

        return $this->json($this->presenter->adminUser($target));
    }

    #[Route('/audit-logs', name: 'admin_audit_logs', methods: ['GET'])]
    public function auditLogs(Request $request): JsonResponse
    {
        $limit = (int) ($request->query->get('limit') ?? 200);
        $filters = [
            'action' => $request->query->get('action'),
            'userId' => $request->query->get('userId'),
            'from' => $this->dateTime($request, 'from'),
            'to' => $this->dateTime($request, 'to'),
        ];

        $logs = array_map(
            fn (\App\Entity\AuditLog $a) => $this->presenter->auditLog($a),
            $this->auditLogs->search($filters, $limit),
        );

        return $this->json(['logs' => $logs]);
    }

    // ---- helpers ----

    private function fetchUser(string $id): User
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }
        $user = $this->users->find(Uuid::fromString($id));
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        return $user;
    }

    private function dateTime(Request $request, string $key): ?\DateTimeImmutable
    {
        $value = $request->query->get($key);
        if (!$value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
        if (!$date || $date->format('Y-m-d') !== (string) $value) {
            throw new BadRequestHttpException(sprintf('Date %s invalide (YYYY-MM-DD attendu).', $key));
        }

        return $date->setTime(23, 59, 59);
    }

    /** @return array<string,mixed> */
    private function decode(Request $request): array
    {
        return json_decode($request->getContent() ?: '{}', true) ?? [];
    }
}
