<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Google OAuth2 / OIDC login — SCAFFOLD.
 *
 * The redirect flow is wired but disabled until GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET
 * are provided in .env.local. Without credentials, the endpoints return 501 so the rest
 * of the app stays bootable. Enabling it requires: filling the two env vars, registering
 * the redirect URI in the Google Cloud console, then implementing the token exchange +
 * ID-token verification in callback() and provisioning the user (see docs/oauth-google.md).
 */
#[Route('/api/auth/google')]
class GoogleOAuthController extends AbstractController
{
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    public function __construct(
        private readonly string $googleClientId,
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
    public function callback(): JsonResponse
    {
        // TODO: token exchange + ID-token verification, then find-or-provision the user
        // and issue a JWT session (see AuthController::issueSession pattern).
        return $this->json(['error' => 'Échange de jeton non implémenté (scaffold).'], 501);
    }
}
