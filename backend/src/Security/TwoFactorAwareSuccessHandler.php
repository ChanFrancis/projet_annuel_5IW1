<?php

namespace App\Security;

use App\Entity\User;
use App\Service\AuditLogger;
use App\Service\MfaChallengeStore;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Login success handler for the password (json_login) firewall.
 *
 * If the user has TOTP enabled, we DO NOT hand out the JWT yet: we return a
 * short-lived `mfaToken` and require a second step (POST /api/auth/2fa/verify).
 * Otherwise we delegate to the default Lexik handler (which issues the JWT and
 * triggers the refresh-token + user-payload enrichment via events).
 *
 * OAuth (Google) issues the JWT directly and never goes through here.
 */
class TwoFactorAwareSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly AuthenticationSuccessHandler $lexikHandler,
        private readonly MfaChallengeStore $mfa,
        private readonly AuditLogger $audit,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        if ($user instanceof User && $user->isTotpAuthenticationEnabled()) {
            $mfaToken = $this->mfa->create($user->getId());
            $this->audit->log('user.login_2fa_challenge', $user->getId());

            return new JsonResponse(['twoFactorRequired' => true, 'mfaToken' => $mfaToken]);
        }

        return $this->lexikHandler->onAuthenticationSuccess($request, $token);
    }
}
