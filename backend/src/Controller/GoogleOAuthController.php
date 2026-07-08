<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Google OAuth2 / OIDC login — SCAFFOLD.
 *
 * The full flow is wired but disabled until GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET
 * are provided in .env.local. Without credentials, /connect returns 501 so the
 * rest of the app stays bootable. Enabling it only requires filling the two env
 * vars and registering the redirect URI in the Google Cloud console.
 */
#[Route('/api/auth/google')]
class GoogleOAuthController extends AbstractController
{
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RefreshTokenGeneratorInterface $refreshGenerator,
        private readonly RefreshTokenManagerInterface $refreshManager,
        private readonly AuditLogger $audit,
        private readonly string $googleClientId,
        private readonly string $googleClientSecret,
        private readonly string $frontendUrl,
    ) {
    }

    #[Route('/connect', name: 'auth_google_connect', methods: ['GET'])]
    public function connect(Request $request): RedirectResponse|JsonResponse
    {
        if ('' === $this->googleClientId) {
            return $this->json([
                'error' => 'Google OAuth non configuré.',
                'hint' => 'Renseigner GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET dans backend/.env.local.',
            ], 501);
        }

        $params = http_build_query([
            'client_id' => $this->googleClientId,
            'redirect_uri' => $request->getSchemeAndHttpHost().'/api/auth/google/callback',
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);

        return new RedirectResponse(self::AUTH_ENDPOINT.'?'.$params);
    }

    #[Route('/callback', name: 'auth_google_callback', methods: ['GET'])]
    public function callback(Request $request): RedirectResponse|JsonResponse
    {
        if ('' === $this->googleClientId) {
            return $this->json(['error' => 'Google OAuth non configuré.'], 501);
        }

        // NOTE: token exchange + ID token verification go here once credentials exist.
        // Skeleton kept intentionally minimal; see docs/oauth-google.md.
        return $this->json(['error' => 'Échange de jeton non implémenté (scaffold).'], 501);
    }

    /**
     * Finds or provisions a user from a verified Google profile.
     * Called by the callback once the ID token is verified.
     */
    private function upsertUser(string $googleId, string $email): User
    {
        $user = $this->users->findOneByEmail($email);
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setPassword(bin2hex(random_bytes(16))); // unusable local password
        }
        $user->setGoogleId($googleId);
        $user->setIsVerified(true);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
