<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class UserCheckerTest extends TestCase
{
    public function testBannedUserIsRejected(): void
    {
        $user = new User();
        $user->setBanned(true);

        $this->expectException(CustomUserMessageAccountStatusException::class);

        (new UserChecker())->checkPostAuth($user);
    }

    public function testActiveUserPasses(): void
    {
        $user = new User();
        $user->setBanned(false);

        (new UserChecker())->checkPostAuth($user);

        $this->addToAssertionCount(1); // no exception thrown
    }

    public function testFreshUserIsNotBannedByDefault(): void
    {
        $user = new User();
        self::assertFalse($user->isBanned());
    }
}
