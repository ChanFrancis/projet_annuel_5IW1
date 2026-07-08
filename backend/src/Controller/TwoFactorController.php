<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * TOTP two-factor setup. Flow:
 *  1. POST /setup  -> generates a secret, returns provisioning URI (for a QR code).
 *  2. POST /enable -> user submits a 6-digit code; if valid, 2FA is turned on.
 *  3. POST /disable-> turns 2FA off (requires a valid code).
 */
#[Route('/api/auth/2fa')]
class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TotpAuthenticatorInterface $totp,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('/setup', name: 'auth_2fa_setup', methods: ['POST'])]
    public function setup(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $user->setTotpSecret($this->totp->generateSecret());
        $user->setTotpEnabled(false);
        $this->em->flush();

        return $this->json([
            'secret' => $user->getTotpSecret(),
            'provisioningUri' => $this->totp->getQRContent($user),
        ]);
    }

    #[Route('/enable', name: 'auth_2fa_enable', methods: ['POST'])]
    public function enable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $code = (string) (json_decode($request->getContent() ?: '{}', true)['code'] ?? '');

        if (null === $user->getTotpSecret() || !$this->totp->checkCode($user, $code)) {
            return $this->json(['error' => 'Code invalide.'], 400);
        }

        $user->setTotpEnabled(true);
        $this->em->flush();
        $this->audit->log('user.2fa_enabled', $user->getId());

        return $this->json(['message' => '2FA activée.']);
    }

    #[Route('/disable', name: 'auth_2fa_disable', methods: ['POST'])]
    public function disable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $code = (string) (json_decode($request->getContent() ?: '{}', true)['code'] ?? '');

        if (!$user->isTotpEnabled() || !$this->totp->checkCode($user, $code)) {
            return $this->json(['error' => 'Code invalide.'], 400);
        }

        $user->setTotpEnabled(false);
        $user->setTotpSecret(null);
        $this->em->flush();
        $this->audit->log('user.2fa_disabled', $user->getId());

        return $this->json(['message' => '2FA désactivée.']);
    }
}
