<?php

namespace App\Tests\Entity;

use App\Entity\Account;
use App\Entity\Budget;
use App\Entity\Category;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class BudgetTest extends TestCase
{
    public function testStoresAccountCategoryAndMonth(): void
    {
        $owner = new User();
        $owner->setEmail('owner@copot.local');

        $account = new Account();
        $category = new Category($owner, 'Courses');

        $budget = new Budget($account, $category, '2026-07');
        $budget->setAmount('300.50');

        self::assertSame('2026-07', $budget->getMonth());
        self::assertSame('300.50', $budget->getAmount());
        self::assertSame($account, $budget->getAccount());
        self::assertSame($category, $budget->getCategory());
        self::assertNotEmpty((string) $budget->getId());
    }

    public function testAmountCanBeUpdated(): void
    {
        $owner = new User();
        $account = new Account();
        $category = new Category($owner, 'Loisirs');

        $budget = new Budget($account, $category, '2026-07');
        $budget->setAmount('100.00');
        $budget->setAmount('150.00');

        self::assertSame('150.00', $budget->getAmount());
    }
}
