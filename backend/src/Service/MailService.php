<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Thin mail helper. In dev, all mail is caught by Mailhog (http://localhost:8025).
 */
class MailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $frontendUrl,
    ) {
    }

    public function sendPasswordReset(string $to, string $rawToken): void
    {
        $link = sprintf('%s/reset-password?token=%s', $this->frontendUrl, $rawToken);
        $this->send($to, 'Réinitialisation de votre mot de passe',
            "Pour réinitialiser votre mot de passe, cliquez sur ce lien (valable 1h) :\n$link");
    }

    public function sendMagicLink(string $to, string $rawToken): void
    {
        $link = sprintf('%s/magic-link?token=%s', $this->frontendUrl, $rawToken);
        $this->send($to, 'Votre lien de connexion CoPot',
            "Connectez-vous en un clic (lien valable 15 min, usage unique) :\n$link");
    }

    public function sendInvitation(string $to, string $rawToken, string $accountLabel): void
    {
        $link = sprintf('%s/invitations/%s', $this->frontendUrl, $rawToken);
        $this->send($to, "Invitation à rejoindre le compte « $accountLabel »",
            "Vous êtes invité à rejoindre le compte « $accountLabel » sur CoPot :\n$link");
    }

    private function send(string $to, string $subject, string $text): void
    {
        $email = (new Email())
            ->from('no-reply@copot.local')
            ->to($to)
            ->subject($subject)
            ->text($text);

        $this->mailer->send($email);
    }
}
