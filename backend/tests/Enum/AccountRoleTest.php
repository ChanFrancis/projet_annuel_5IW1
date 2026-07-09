<?php

namespace App\Tests\Enum;

use App\Enum\AccountRole;
use PHPUnit\Framework\TestCase;

class AccountRoleTest extends TestCase
{
    public function testOwnerCanWriteAndManage(): void
    {
        self::assertTrue(AccountRole::OWNER->canWrite());
        self::assertTrue(AccountRole::OWNER->canManage());
    }

    public function testCoOwnerCanWritebutNotManage(): void
    {
        self::assertTrue(AccountRole::CO_OWNER->canWrite());
        self::assertFalse(AccountRole::CO_OWNER->canManage());
    }

    public function testViewerCanNeither(): void
    {
        self::assertFalse(AccountRole::VIEWER->canWrite());
        self::assertFalse(AccountRole::VIEWER->canManage());
    }
}
