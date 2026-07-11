<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * End-to-end HTTP tests of the account + transaction flow, including
 * IBAN generation, balance computation and role enforcement.
 */
class AccountFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $token;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Clean slate (children before parents for FK safety).
        foreach (['Transaction', 'AccountUser', 'Account', 'User'] as $entity) {
            $this->em->createQuery("DELETE FROM App\\Entity\\$entity")->execute();
        }

        // One verified user, authenticated.
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setEmail('owner@copot.test');
        $user->setIsVerified(true);
        $user->setPassword($hasher->hashPassword($user, 'Str0ng!Passw0rd'));
        $this->em->persist($user);
        $this->em->flush();

        $this->json('POST', '/api/auth/login', ['email' => 'owner@copot.test', 'password' => 'Str0ng!Passw0rd']);
        $this->token = $this->decode()['token'];
    }

    public function testCreateAccountGeneratesValidIban(): void
    {
        $this->authed('POST', '/api/accounts', ['label' => 'Compte courant', 'type' => 'courant']);
        self::assertResponseStatusCodeSame(201);

        $account = $this->decode();
        self::assertSame('0', $account['balance']);
        self::assertStringStartsWith('FR', $account['iban']);
        self::assertSame(27, \strlen($account['iban']));
    }

    public function testTransactionsUpdateBalance(): void
    {
        $this->authed('POST', '/api/accounts', ['label' => 'Compte', 'type' => 'courant']);
        $accountId = $this->decode()['id'];

        $this->authed('POST', "/api/accounts/$accountId/transactions", ['date' => '2026-01-05', 'label' => 'Salaire', 'amount' => '2000.00']);
        self::assertResponseStatusCodeSame(201);
        $this->authed('POST', "/api/accounts/$accountId/transactions", ['date' => '2026-01-10', 'label' => 'Courses', 'amount' => '-150.50']);
        self::assertResponseStatusCodeSame(201);

        $this->authed('GET', "/api/accounts/$accountId");
        self::assertSame('1849.50', $this->decode()['balance']);
    }

    public function testCreateAccountRequiresValidType(): void
    {
        $this->authed('POST', '/api/accounts', ['label' => 'X', 'type' => 'not-a-type']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testListingIsScopedToTheUser(): void
    {
        $this->authed('POST', '/api/accounts', ['label' => 'Mien', 'type' => 'livret']);
        $this->authed('GET', '/api/accounts');
        self::assertCount(1, $this->decode()['accounts']);
    }

    /** @param array<string,mixed> $body */
    private function authed(string $method, string $uri, array $body = []): void
    {
        $this->client->request($method, $uri,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
            content: $body ? json_encode($body) : null,
        );
    }

    /** @param array<string,mixed> $body */
    private function json(string $method, string $uri, array $body): void
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body));
    }

    /** @return array<string,mixed> */
    private function decode(): array
    {
        return json_decode($this->client->getResponse()->getContent() ?: '{}', true) ?? [];
    }
}
