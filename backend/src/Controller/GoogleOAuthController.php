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
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Google OAuth2 / OIDC login (server-side "authorization code" flow).
 *
 *  /connect  -> redirects the user to Google's consent screen (with a CSRF state)
 *  /callback -> exchanges the code for tokens, fetches the profile, provisions or
 *               finds the user, issues a JWT session, and redirects to the SPA.
 *
 * Enabled once GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET are set (backend/.env.local).
 */
#[Route('/api/auth/google')]
class GoogleOAuthController extends AbstractController
{
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const USERINFO_ENDPOINT = 'https://openidconnect.googleapis.com/v1/userinfo';
    private const STATE_COOKIE = 'g_oauth_state';

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

        // CSRF protection: random state echoed back by Google, kept in a cookie.
        $state = bin2hex(random_bytes(16));
        $params = http_build_query([
            'client_id' => $this->googleClientId,
            'redirect_uri' => $this->redirectUri($request),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
            'state' => $state,
        ]);

        $response = new RedirectResponse(self::AUTH_ENDPOINT.'?'.$params);
        $response->headers->setCookie(
            Cookie::create(self::STATE_COOKIE, $state)
                ->withHttpOnly(true)
                ->withSecure($request->isSecure())
                ->withSameSite('lax')
                ->withExpires(time() + 600)
        );

        return $response;
    }

    #[Route('/callback', name: 'auth_google_callback', methods: ['GET'])]
    public function callback(Request $request): RedirectResponse|JsonResponse
    {
        if ('' === $this->googleClientId) {
            return $this->json(['error' => 'Google OAuth non configuré.'], 501);
        }

        // Validate CSRF state.
        $state = (string) $request->query->get('state');
        $expected = (string) $request->cookies->get(self::STATE_COOKIE);
        if ('' === $state || '' === $expected || !hash_equals($expected, $state)) {
            return $this->redirectToFrontend(['error' => 'state']);
        }

        $code = (string) $request->query->get('code');
        if ('' === $code) {
            return $this->redirectToFrontend(['error' => $request->query->get('error') ? 'denied' : 'missing_code']);
        }

        // Exchange the authorization code for an access token.
        $token = $this->postForm(self::TOKEN_ENDPOINT, [
            'code' => $code,
            'client_id' => $this->googleClientId,
            'client_secret' => $this->googleClientSecret,
            'redirect_uri' => $this->redirectUri($request),
            'grant_type' => 'authorization_code',
        ]);
        $accessToken = $token['access_token'] ?? null;
        if (!$accessToken) {
            return $this->redirectToFrontend(['error' => 'token_exchange']);
        }

        // Fetch the verified profile.
        $profile = $this->getJson(self::USERINFO_ENDPOINT, (string) $accessToken);
        $email = isset($profile['email']) ? strtolower((string) $profile['email']) : '';
        $googleId = (string) ($profile['sub'] ?? '');
        if ('' === $email || '' === $googleId || false === ($profile['email_verified'] ?? false)) {
            return $this->redirectToFrontend(['error' => 'profile']);
        }

        $user = $this->upsertUser($googleId, $email);
        $session = $this->issueSession($user);
        $this->audit->log('user.login_google', $user->getId());

        $response = $this->redirectToFrontend($session);
        $response->headers->clearCookie(self::STATE_COOKIE);

        return $response;
    }

    private function upsertUser(string $googleId, string $email): User
    {
        $user = $this->users->findOneByEmail($email);
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            // Unusable local password: Google is the sole credential for this account.
            $user->setPassword(bin2hex(random_bytes(32)));
        }
        $user->setGoogleId($googleId);
        $user->setIsVerified(true);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @return array{token: string, refresh_token: string}
     */
    private function issueSession(User $user): array
    {
        $jwt = $this->jwtManager->create($user);
        $refresh = $this->refreshGenerator->createForUserWithTtl($user, 2592000);
        $this->refreshManager->save($refresh);

        return ['token' => $jwt, 'refresh_token' => $refresh->getRefreshToken()];
    }

    private function redirectUri(Request $request): string
    {
        return $request->getSchemeAndHttpHost().'/api/auth/google/callback';
    }

    /**
     * Redirects to the SPA callback, passing data in the URL fragment
     * (fragments are not sent to servers, keeping tokens out of access logs).
     *
     * @param array<string,mixed> $params
     */
    private function redirectToFrontend(array $params): RedirectResponse
    {
        return new RedirectResponse($this->frontendUrl.'/oauth/callback#'.http_build_query($params));
    }

    /**
     * @param array<string,string> $fields
     *
     * @return array<string,mixed>
     */
    private function postForm(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        return \is_string($body) ? (json_decode($body, true) ?: []) : [];
    }

    /**
     * @return array<string,mixed>
     */
    private function getJson(string $url, string $bearer): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$bearer, 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        return \is_string($body) ? (json_decode($body, true) ?: []) : [];
    }
}
