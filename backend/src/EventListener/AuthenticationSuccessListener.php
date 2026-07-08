<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\AuditLogger;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Enriches the JWT login response with a minimal user payload and audits the login.
 * (gesdinet adds `refresh_token` to the same response via its own listener.)
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success')]
class AuthenticationSuccessListener
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $data = $event->getData();
        $data['user'] = [
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'passwordExpired' => $user->isPasswordExpired(),
        ];
        $event->setData($data);

        $this->auditLogger->log('user.login', $user->getId());
    }
}
