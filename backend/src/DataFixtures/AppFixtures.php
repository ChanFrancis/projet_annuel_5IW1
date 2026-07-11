<?php

namespace App\DataFixtures;

use App\Entity\Account;
use App\Entity\AccountUser;
use App\Entity\Budget;
use App\Entity\Category;
use App\Entity\Invitation;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\AccountRole;
use App\Enum\AccountType;
use App\Service\IbanGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Demo dataset: several account types, a shared account showcasing roles,
 * nested categories, monthly budgets, a pending invitation, and transactions
 * spread over four months so charts and statistics are meaningful.
 */
class AppFixtures extends Fixture
{
    private ObjectManager $manager;

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly IbanGenerator $ibanGenerator,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;

        // ---- Users (credentials in the README) ----
        $demo = $this->makeUser('demo@copot.local', 'DemoPassw0rd!');
        $admin = $this->makeUser('admin@copot.local', 'AdminPassw0rd!', ['ROLE_ADMIN']);
        $jane = $this->makeUser('jane@copot.local', 'DemoPassw0rd!');
        $paul = $this->makeUser('paul@copot.local', 'DemoPassw0rd!');
        $this->makeUser('banned@copot.local', 'DemoPassw0rd!', banned: true);
        unset($admin);

        // ---- Categories for the demo user (with sub-categories) ----
        $salaire = $this->makeCategory($demo, 'Salaire');
        $logement = $this->makeCategory($demo, 'Logement');
        $transport = $this->makeCategory($demo, 'Transport');
        $sante = $this->makeCategory($demo, 'Santé');

        $alimentation = $this->makeCategory($demo, 'Alimentation');
        $courses = $this->makeCategory($demo, 'Courses', $alimentation);
        $resto = $this->makeCategory($demo, 'Restaurant', $alimentation);

        $loisirs = $this->makeCategory($demo, 'Loisirs');
        $cinema = $this->makeCategory($demo, 'Cinéma', $loisirs);
        $jeux = $this->makeCategory($demo, 'Jeux vidéo', $loisirs);

        // ---- Accounts ----
        // 1) Personal current account (demo owner).
        $courant = $this->makeAccount('Compte courant', AccountType::COURANT, $demo, [[$demo, AccountRole::OWNER]]);

        // 2) Shared household account: demo owner, jane co-owner, paul viewer.
        $commun = $this->makeAccount('Compte commun', AccountType::COMMUN, $demo, [
            [$demo, AccountRole::OWNER],
            [$jane, AccountRole::CO_OWNER],
            [$paul, AccountRole::VIEWER],
        ]);

        // 3) Savings passbook + 4) custom savings (demo owner).
        $livret = $this->makeAccount('Livret A', AccountType::LIVRET, $demo, [[$demo, AccountRole::OWNER]]);
        $epargne = $this->makeAccount('Épargne vacances', AccountType::EPARGNE, $demo, [[$demo, AccountRole::OWNER]]);

        // ---- Transactions over the last 4 months ----
        for ($m = 3; $m >= 0; --$m) {
            $base = "-$m months";

            // Current account: salary in, living expenses out.
            $this->tx($courant, $demo, "$base -27 days", 'Salaire', '2500.00', $salaire);
            $this->tx($courant, $demo, "$base -25 days", 'Loyer', '-820.00', $logement);
            $this->tx($courant, $demo, "$base -20 days", 'Courses Carrefour', $this->rand(-120, -60), $courses);
            $this->tx($courant, $demo, "$base -14 days", 'Essence', $this->rand(-70, -45), $transport);
            $this->tx($courant, $demo, "$base -10 days", 'Restaurant', $this->rand(-55, -25), $resto);
            $this->tx($courant, $demo, "$base -6 days", 'Cinéma', '-24.00', $cinema);
            $this->tx($courant, $demo, "$base -3 days", 'Pharmacie', $this->rand(-40, -12), $sante);
            $this->tx($courant, $demo, "$base -2 days", 'Virement épargne', '-200.00', null);

            // Shared account: household expenses split with jane.
            $this->tx($commun, $demo, "$base -22 days", 'Courses communes', $this->rand(-160, -90), $courses);
            $this->tx($commun, $jane, "$base -12 days", 'Électricité', $this->rand(-90, -55), $logement);
            $this->tx($commun, $jane, "$base -5 days", 'Restaurant à deux', $this->rand(-70, -40), $resto);
            $this->tx($commun, $demo, "$base -26 days", 'Dépôt commun', '500.00', null);

            // Savings: monthly deposits.
            $this->tx($livret, $demo, "$base -1 days", 'Virement mensuel', '200.00', null);
            if (0 === $m % 2) {
                $this->tx($epargne, $demo, "$base -8 days", 'Économies vacances', '150.00', null);
            }
        }
        // A couple of leisure purchases on the current account.
        $this->tx($courant, $demo, '-4 days', 'Steam', '-29.99', $jeux);
        $this->tx($courant, $demo, '-9 days', 'Concert', '-68.00', $loisirs);

        // ---- Monthly budgets (current month) ----
        // Set on leaf categories that actually carry spending, so the progress
        // bars (spent vs budget) are meaningful in the demo.
        $month = (new \DateTimeImmutable())->format('Y-m');
        $this->budget($courant, $courses, $month, '350.00');
        $this->budget($courant, $transport, $month, '150.00');
        $this->budget($courant, $cinema, $month, '40.00');
        $this->budget($commun, $logement, $month, '200.00');

        // ---- A pending invitation on the shared account (demo panel) ----
        $invitation = new Invitation(
            $commun,
            'invite@example.com',
            AccountRole::VIEWER,
            hash('sha256', bin2hex(random_bytes(16))),
            $demo->getId(),
            new \DateTimeImmutable('+7 days'),
        );
        $manager->persist($invitation);

        $manager->flush();
    }

    /** @param list<string> $roles */
    private function makeUser(string $email, string $password, array $roles = [], bool $banned = false): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setIsVerified(true);
        if ($roles) {
            $user->setRoles($roles);
        }
        if ($banned) {
            $user->setBanned(true);
        }
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->manager->persist($user);

        return $user;
    }

    private function makeCategory(User $owner, string $name, ?Category $parent = null): Category
    {
        $category = new Category($owner, $name);
        if ($parent) {
            $category->setParent($parent);
        }
        $this->manager->persist($category);

        return $category;
    }

    /**
     * @param list<array{0: User, 1: AccountRole}> $members
     */
    private function makeAccount(string $label, AccountType $type, User $creator, array $members): Account
    {
        $account = new Account();
        $account->setLabel($label);
        $account->setType($type);
        $account->setCreatedBy($creator);
        $account->setIban($this->ibanGenerator->generate());
        foreach ($members as [$user, $role]) {
            $account->addMember(new AccountUser($user, $role));
        }
        $this->manager->persist($account);

        return $account;
    }

    private function tx(Account $account, User $author, string $when, string $label, string $amount, ?Category $category): void
    {
        $tx = new Transaction($account, $author->getId());
        $tx->setDate(new \DateTimeImmutable($when));
        $tx->setLabel($label);
        $tx->setAmount($amount);
        $tx->setCategory($category);
        $this->manager->persist($tx);
    }

    private function budget(Account $account, Category $category, string $month, string $amount): void
    {
        $budget = new Budget($account, $category, $month);
        $budget->setAmount($amount);
        $this->manager->persist($budget);
    }

    /** Random amount as a 2-decimal string, for lifelike figures. */
    private function rand(int $minCents, int $maxCents): string
    {
        return number_format(random_int($minCents * 100, $maxCents * 100) / 100, 2, '.', '');
    }
}
