<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * End-to-end HTTP tests of the authentication flow, hitting the real
 * controllers + security layer + database (test schema).
 */
class AuthFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        // Clean slate — children before parents for FK safety.
        foreach (['Transaction', 'AccountUser', 'Account', 'User'] as $entity) {
            $this->em->createQuery("DELETE FROM App\\Entity\\$entity")->execute();
        }
    }

    public function testRegisterRejectsWeakPassword(): void
    {
        $this->json('POST', '/api/auth/register', ['email' => 'weak@copot.test', 'password' => 'short']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testRegisterThenLoginThenMe(): void
    {
        // Register
        $this->json('POST', '/api/auth/register', [
            'email' => 'flow@copot.test',
            'password' => 'Str0ng!Passw0rd',
        ]);
        self::assertResponseStatusCodeSame(201);

        // Duplicate registration is rejected
        $this->json('POST', '/api/auth/register', [
            'email' => 'flow@copot.test',
            'password' => 'Str0ng!Passw0rd',
        ]);
        self::assertResponseStatusCodeSame(409);

        // Login
        $this->json('POST', '/api/auth/login', [
            'email' => 'flow@copot.test',
            'password' => 'Str0ng!Passw0rd',
        ]);
        self::assertResponseIsSuccessful();
        $login = $this->decode();
        self::assertArrayHasKey('token', $login);
        self::assertArrayHasKey('refresh_token', $login);

        // Authenticated /me
        $this->client->request('GET', '/api/auth/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$login['token'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('flow@copot.test', $this->decode()['email']);
    }

    public function testMeRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testBannedUserCannotLogin(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setEmail('banned@copot.test');
        $user->setPassword($hasher->hashPassword($user, 'Str0ng!Passw0rd'));
        $user->setBanned(true);
        $this->em->persist($user);
        $this->em->flush();

        $this->json('POST', '/api/auth/login', [
            'email' => 'banned@copot.test',
            'password' => 'Str0ng!Passw0rd',
        ]);
        self::assertResponseStatusCodeSame(401);
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
