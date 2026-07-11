<?php

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Prometheus metrics exposition (text format), dependency-free.
 * Scraped by Prometheus at http://backend:8000/metrics.
 *
 * Kept outside /api so it is not behind the JWT firewall; in production it
 * should only be reachable from the internal monitoring network.
 */
class MetricsController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/metrics', name: 'metrics', methods: ['GET'])]
    public function metrics(): Response
    {
        $metrics = [
            ['copot_up', 'Application liveness (1 = up)', 'gauge', 1],
            ['copot_users_total', 'Total number of registered users', 'gauge', $this->count(User::class)],
            ['copot_accounts_total', 'Total number of accounts', 'gauge', $this->count(Account::class)],
            ['copot_transactions_total', 'Total number of transactions', 'gauge', $this->count(Transaction::class)],
        ];

        $lines = [];
        foreach ($metrics as [$name, $help, $type, $value]) {
            $lines[] = "# HELP $name $help";
            $lines[] = "# TYPE $name $type";
            $lines[] = "$name $value";
        }

        return new Response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    /** @param class-string $entity */
    private function count(string $entity): int
    {
        return (int) $this->em->createQuery("SELECT COUNT(e.id) FROM $entity e")->getSingleScalarResult();
    }
}
