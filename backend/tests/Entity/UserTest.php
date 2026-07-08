<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testRolesAlwaysContainRoleUser(): void
    {
        $user = new User();
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testAdminRoleIsPreservedAndDeduplicated(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        $roles = $user->getRoles();
        self::assertContains('ROLE_ADMIN', $roles);
        self::assertContains('ROLE_USER', $roles);
        self::assertSame(array_values(array_unique($roles)), $roles, 'roles must be unique');
    }

    public function testEmailIsNormalisedToLowercase(): void
    {
        $user = new User();
        $user->setEmail('  Demo@CoPot.Local  ');
        self::assertSame('demo@copot.local', $user->getEmail());
    }

    public function testFreshPasswordIsNotExpired(): void
    {
        $user = new User();
        $user->setPassword('hash');
        self::assertFalse($user->isPasswordExpired(60));
    }

    public function testTotpDisabledByDefault(): void
    {
        $user = new User();
        self::assertFalse($user->isTotpAuthenticationEnabled());
    }
}
