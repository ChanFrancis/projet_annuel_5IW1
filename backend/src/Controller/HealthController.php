<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(Connection $connection): JsonResponse
    {
        $db = 'up';
        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $db = 'down';
        }

        return new JsonResponse([
            'status' => 'ok',
            'service' => 'copot-backend',
            'database' => $db,
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
