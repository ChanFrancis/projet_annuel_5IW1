<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
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

        $manager->flush();
    }
}
