<?php

namespace App\DataFixtures;

use App\Entity\Account;
use App\Entity\AccountUser;
use App\Entity\Budget;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\AccountRole;
use App\Enum\AccountType;
use App\Service\IbanGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly IbanGenerator $ibanGenerator,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Demo user — credentials printed in the README.
        $demo = new User();
        $demo->setEmail('demo@copot.local');
        $demo->setIsVerified(true);
        $demo->setPassword($this->hasher->hashPassword($demo, 'DemoPassw0rd!'));
        $manager->persist($demo);

        // Admin.
        $admin = new User();
        $admin->setEmail('admin@copot.local');
        $admin->setIsVerified(true);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'AdminPassw0rd!'));
        $manager->persist($admin);

        // Extra users for the admin panel demo.
        $jane = new User();
        $jane->setEmail('jane@copot.local');
        $jane->setIsVerified(true);
        $jane->setPassword($this->hasher->hashPassword($jane, 'DemoPassw0rd!'));
        $manager->persist($jane);

        $bannedUser = new User();
        $bannedUser->setEmail('banned@copot.local');
        $bannedUser->setIsVerified(true);
        $bannedUser->setBanned(true);
        $bannedUser->setPassword($this->hasher->hashPassword($bannedUser, 'DemoPassw0rd!'));
        $manager->persist($bannedUser);

        // Categories for the demo user.
        $courses = new Category($demo, 'Courses');
        $salaire = new Category($demo, 'Salaire');
        $loisirs = new Category($demo, 'Loisirs');
        foreach ([$courses, $salaire, $loisirs] as $c) {
            $manager->persist($c);
        }

        // A current account owned by the demo user, with a few transactions.
        $account = new Account();
        $account->setLabel('Compte courant');
        $account->setType(AccountType::COURANT);
        $account->setCreatedBy($demo);
        $account->setIban($this->ibanGenerator->generate());
        $account->addMember(new AccountUser($demo, AccountRole::OWNER));
        $manager->persist($account);

        // Transactions spread across the last 3 months so charts are non-flat.
        $seed = [
            ['-15 days', 'Salaire', '2500.00', $salaire],
            ['-10 days', 'Courses Carrefour', '-84.32', $courses],
            ['-7 days', 'Cinéma', '-24.00', $loisirs],
            ['-3 days', 'Courses Bio', '-42.10', $courses],
            ['-1 day', 'Remboursement ami', '30.00', null],
            ['-1 month -12 days', 'Salaire', '2500.00', $salaire],
            ['-1 month -8 days', 'Courses Lidl', '-92.50', $courses],
            ['-1 month -4 days', 'Concert', '-68.00', $loisirs],
            ['-2 months -14 days', 'Salaire', '2500.00', $salaire],
            ['-2 months -9 days', 'Courses Carrefour', '-110.20', $courses],
            ['-2 months -2 days', 'Steam', '-29.99', $loisirs],
        ];
        foreach ($seed as [$when, $label, $amount, $category]) {
            $tx = new Transaction($account, $demo->getId());
            $tx->setDate(new \DateTimeImmutable($when));
            $tx->setLabel($label);
            $tx->setAmount($amount);
            $tx->setCategory($category);
            $manager->persist($tx);
        }

        // A budget for the current month on the Courses category.
        $budget = new Budget($account, $courses, (new \DateTimeImmutable())->format('Y-m'));
        $budget->setAmount('300.00');
        $manager->persist($budget);

        $manager->flush();
    }
}
