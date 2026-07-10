<?php

namespace App\Controller;

use App\Entity\Account;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use App\Security\AccountVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/accounts/{id}/stats')]
class StatisticController extends AbstractController
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly TransactionRepository $transactions,
    ) {
    }

    #[Route('/summary', name: 'stats_summary', methods: ['GET'])]
    public function summary(string $id, Request $request): JsonResponse
    {
        $account = $this->fetch($id);
        $this->denyAccessUnlessGranted(AccountVoter::VIEW, $account);

        [$from, $to] = $this->range($request);

        return $this->json($this->transactions->totalsForRange($account, $from, $to));
    }

    #[Route('/monthly', name: 'stats_monthly', methods: ['GET'])]
    public function monthly(string $id, Request $request): JsonResponse
    {
        $account = $this->fetch($id);
        $this->denyAccessUnlessGranted(AccountVoter::VIEW, $account);

        [$from, $to] = $this->range($request);

        return $this->json(['points' => $this->transactions->monthlyTotals($account, $from, $to)]);
    }

    #[Route('/by-category', name: 'stats_by_category', methods: ['GET'])]
    public function byCategory(string $id, Request $request): JsonResponse
    {
        $account = $this->fetch($id);
        $this->denyAccessUnlessGranted(AccountVoter::VIEW, $account);

        [$from, $to] = $this->range($request);

        return $this->json(['categories' => $this->transactions->sumByCategory($account, $from, $to)]);
    }

    // ---- helpers ----

    private function fetch(string $id): Account
    {
        $account = $this->accounts->find($this->asUuid($id));
        if (!$account) {
            throw $this->createNotFoundException('Compte introuvable.');
        }

        return $account;
    }

    /**
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private function range(Request $request): array
    {
        $to = $this->date($request, 'to') ?? new \DateTimeImmutable('last day of this month');
        $from = $this->date($request, 'from') ?? $to->modify('first day of -5 months');

        if ($from > $to) {
            throw new BadRequestHttpException('La date de début doit précéder la date de fin.');
        }

        return [$from, $to];
    }

    private function date(Request $request, string $key): ?\DateTimeImmutable
    {
        $value = $request->query->get($key);
        if (!$value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
        if (!$date || $date->format('Y-m-d') !== (string) $value) {
            throw new BadRequestHttpException(sprintf('Date %s invalide (YYYY-MM-DD attendu).', $key));
        }

        return $date->setTime(0, 0);
    }

    private function asUuid(string $id): Uuid
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Identifiant invalide.');
        }

        return Uuid::fromString($id);
    }
}
