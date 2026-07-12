<?php

namespace App\Controller;

use App\Entity\OneTimeToken;
use App\Entity\User;
use App\Repository\OneTimeTokenRepository;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use App\Service\MailService;
use App\Service\MfaChallengeStore;
use App\Validator\StrongPassword;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly OneTimeTokenRepository $tokens,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
        private readonly AuditLogger $audit,
        private readonly MailService $mail,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RefreshTokenGeneratorInterface $refreshGenerator,
        private readonly RefreshTokenManagerInterface $refreshManager,
    ) {
    }

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = $this->decode($request);
        $email = (string) ($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');

        $violations = $this->validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);
        $violations->addAll($this->validator->validate($password, [new StrongPassword()]));
        if (\count($violations) > 0) {
            return $this->json(['errors' => $this->formatViolations($violations)], 422);
        }

        if ($this->users->findOneByEmail($email)) {
            return $this->json(['error' => 'Cet email est déjà utilisé.'], 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->users->save($user);

        $this->audit->log('user.register', $user->getId());

        return $this->json(['id' => (string) $user->getId(), 'email' => $user->getEmail()], 201);
    }

    #[Route('/me', name: 'auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'totpEnabled' => $user->isTotpEnabled(),
            'passwordExpired' => $user->isPasswordExpired(),
        ]);
    }

    /**
     * Second step of a password login when 2FA is enabled: exchange the
     * short-lived mfaToken + TOTP code for a real JWT session.
     */
    #[Route('/2fa/verify', name: 'auth_2fa_verify', methods: ['POST'])]
    public function verifyTwoFactor(Request $request, MfaChallengeStore $mfa, TotpAuthenticatorInterface $totp): JsonResponse
    {
        $data = $this->decode($request);
        $mfaToken = (string) ($data['mfaToken'] ?? '');
        $code = (string) ($data['code'] ?? '');

        $userId = $mfa->resolve($mfaToken);
        if (!$userId) {
            return $this->json(['error' => 'Session 2FA expirée, reconnectez-vous.'], 401);
        }
        $user = $this->users->find($userId);
        if (!$user || !$totp->checkCode($user, $code)) {
            return $this->json(['error' => 'Code invalide.'], 401);
        }

        $mfa->invalidate($mfaToken);
        $this->audit->log('user.login_2fa_success', $user->getId());

        return $this->json($this->issueSession($user));
    }

    // ---- Password reset ----

    #[Route('/password/forgot', name: 'auth_password_forgot', methods: ['POST'])]
    public function forgotPassword(Request $request, RateLimiterFactory $passwordForgotLimiter): JsonResponse
    {
        $limiter = $passwordForgotLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Trop de demandes. Réessayez plus tard.'], 429);
        }

        $email = (string) ($this->decode($request)['email'] ?? '');
        $user = $this->users->findOneByEmail($email);

        // Always return 200 to avoid leaking which emails exist.
        if ($user) {
            $raw = $this->issueToken($user, OneTimeToken::TYPE_PASSWORD_RESET, '+1 hour');
            $this->mail->sendPasswordReset($user->getEmail(), $raw);
            $this->audit->log('user.password_forgot', $user->getId());
        }

        return $this->json(['message' => 'Si un compte existe, un email a été envoyé.']);
    }

    #[Route('/password/reset', name: 'auth_password_reset', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $this->decode($request);
        $raw = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');

        $violations = $this->validator->validate($password, [new StrongPassword()]);
        if (\count($violations) > 0) {
            return $this->json(['errors' => $this->formatViolations($violations)], 422);
        }

        $token = $this->tokens->findUsableByHash(hash('sha256', $raw), OneTimeToken::TYPE_PASSWORD_RESET);
        if (!$token) {
            return $this->json(['error' => 'Lien invalide ou expiré.'], 400);
        }

        $user = $this->users->find($token->getUserId());
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable.'], 400);
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $token->markUsed();
        $this->em->flush();
        $this->audit->log('user.password_reset', $user->getId());

        return $this->json(['message' => 'Mot de passe mis à jour.']);
    }

    // ---- Magic link ----

    #[Route('/magic-link/request', name: 'auth_magic_request', methods: ['POST'])]
    public function requestMagicLink(Request $request, RateLimiterFactory $passwordForgotLimiter): JsonResponse
    {
        $limiter = $passwordForgotLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Trop de demandes.'], 429);
        }

        $email = (string) ($this->decode($request)['email'] ?? '');
        $user = $this->users->findOneByEmail($email);
        if ($user) {
            $raw = $this->issueToken($user, OneTimeToken::TYPE_MAGIC_LINK, '+15 minutes');
            $this->mail->sendMagicLink($user->getEmail(), $raw);
            $this->audit->log('user.magic_link_request', $user->getId());
        }

        return $this->json(['message' => 'Si un compte existe, un lien a été envoyé.']);
    }

    #[Route('/magic-link/consume', name: 'auth_magic_consume', methods: ['POST'])]
    public function consumeMagicLink(Request $request): JsonResponse
    {
        $raw = (string) ($this->decode($request)['token'] ?? '');
        $token = $this->tokens->findUsableByHash(hash('sha256', $raw), OneTimeToken::TYPE_MAGIC_LINK);
        if (!$token) {
            return $this->json(['error' => 'Lien invalide ou expiré.'], 400);
        }

        $user = $this->users->find($token->getUserId());
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable.'], 400);
        }

        $token->markUsed();
        $this->em->flush();
        $this->audit->log('user.magic_link_login', $user->getId());

        return $this->json($this->issueSession($user));
    }

    private function issueToken(User $user, string $type, string $ttl): string
    {
        $raw = bin2hex(random_bytes(32));
        $token = new OneTimeToken(
            $user->getId(),
            $type,
            hash('sha256', $raw),
            new \DateTimeImmutable($ttl),
        );
        $this->em->persist($token);
        $this->em->flush();

        return $raw;
    }

    /**
     * @return array{token: string, refresh_token: string, user: array<string,mixed>}
     */
    private function issueSession(User $user): array
    {
        $jwt = $this->jwtManager->create($user);
        $refresh = $this->refreshGenerator->createForUserWithTtl($user, 2592000);
        $this->refreshManager->save($refresh);

        return [
            'token' => $jwt,
            'refresh_token' => $refresh->getRefreshToken(),
            'user' => [
                'id' => (string) $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function decode(Request $request): array
    {
        return json_decode($request->getContent() ?: '{}', true) ?? [];
    }

    /**
     * @param iterable<\Symfony\Component\Validator\ConstraintViolationInterface> $violations
     *
     * @return array<int,array{field:string,message:string}>
     */
    private function formatViolations(iterable $violations): array
    {
        $out = [];
        foreach ($violations as $v) {
            $out[] = ['field' => $v->getPropertyPath() ?: 'password', 'message' => $v->getMessage()];
        }

        return $out;
    }
}
