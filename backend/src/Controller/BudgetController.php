<?php

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Budget;
use App\Entity\Category;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\BudgetRepository;
use App\Repository\CategoryRepository;
use App\Security\AccountVoter;
use App\Service\AuditLogger;
use App\Service\JsonPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/accounts/{id}/budgets')]
class BudgetController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountRepository $accounts,
        private readonly BudgetRepository $budgets,
        private readonly CategoryRepository $categories,
        private readonly JsonPresenter $presenter,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('', name: 'budget_list', methods: ['GET'])]
    public function list(string $id, Request $request): JsonResponse
    {
        $account = $this->fetch($id);
        $this->denyAccessUnlessGranted(AccountVoter::VIEW, $account);

        $month = (string) ($request->query->get('month') ?? (new \DateTimeImmutable())->format('Y-m'));
        if (!self::isValidMonth($month)) {
            return $this->json(['error' => 'Format de mois invalide (YYYY-MM attendu).'], 422);
        }

        $items = array_map(
            fn (Budget $b) => $this->presenter->budget($b),
            $this->budgets->findForAccountAndMonth($account, $month),
        );

        return $this->json(['budgets' => $items, 'month' => $month]);
    }

    #[Route('', name: 'budget_create', methods: ['POST'])]
    public function create(string $id, Request $request): JsonResponse
    {
        $account = $this->fetch($id);
        $this->denyAccessUnlessGranted(AccountVoter::EDIT, $account);

        $data = $this->decode($request);
        $month = trim((string) ($data['month'] ?? (new \DateTimeImmutable())->format('Y-m')));
        if (!self::isValidMonth($month)) {
            return $this->json(['error' => 'Format de mois invalide (YYYY-MM attendu).'], 422);
        }

        $category = $this->resolveMemberCategory($data['categoryId'] ?? null, $account);
        if (null === $category) {
            return $this->json(['error' => 'Catégorie invalide ou inexistante.'], 422);
        }

        $amount = (string) ($data['amount'] ?? '');
        if (!is_numeric($amount) || (float) $amount <= 0) {
            return $this->json(['error' => 'Le montant du budget doit être positif.'], 422);
        }

        // Upsert: one budget per (account, category, month).
        $budget = $this->budgets->findOne($account, $category, $month);
        if (null === $budget) {
            $budget = new Budget($account, $category, $month);
            $budget->setAmount(self::normaliseAmount($amount));
            $this->budgets->save($budget);
            $action = 'budget.create';
        } else {
            $budget->setAmount(self::normaliseAmount($amount));
            $this->em->flush();
            $action = 'budget.update';
        }

        $this->audit->log($action, $this->uid(), 'Budget', $budget->getId(), ['month' => $month, 'amount' => $budget->getAmount()]);

        return $this->json($this->presenter->budget($budget), 201);
    }

    #[Route('/{budgetId}', name: 'budget_update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, string $budgetId, Request $request): JsonResponse
    {
        $account = $this->fetch($id);
        $this->denyAccessUnlessGranted(AccountVoter::EDIT, $account);

        $budget = $this->fetchBudget($account, $budgetId);
        $data = $this->decode($request);

        if (\array_key_exists('amount', $data)) {
            $amount = (string) $data['amount'];
            if (!is_numeric($amount) || (float) $amount <= 0) {
                return $this->json(['error' => 'Le montant du budget doit être positif.'], 422);
            }
            $budget->setAmount(self::normaliseAmount($amount));
        }
        $this->em->flush();
        $this->audit->log('budget.update', $this->uid(), 'Budget', $budget->getId(), ['amount' => $budget->getAmount()]);

        return $this->json($this->presenter->budget($budget));
    }

    #[Route('/{budgetId}', name: 'budget_delete', methods: ['DELETE'])]
    public function delete(string $id, string $budgetId): JsonResponse
    {
        $account = $this->fetch($id);
        $this->denyAccessUnlessGranted(AccountVoter::EDIT, $account);

        $budget = $this->fetchBudget($account, $budgetId);
        $this->audit->log('budget.delete', $this->uid(), 'Budget', $budget->getId(), ['month' => $budget->getMonth()]);
        $this->em->remove($budget);
        $this->em->flush();

        return $this->json(null, 204);
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

    private function fetchBudget(Account $account, string $budgetId): Budget
    {
        $budget = $this->budgets->find($this->asUuid($budgetId));
        if (!$budget || $budget->getAccount()->getId() != $account->getId()) {
            throw $this->createNotFoundException('Budget introuvable.');
        }

        return $budget;
    }

    /**
     * A category is usable for a budget when its owner is a member of the account.
     */
    private function resolveMemberCategory(mixed $categoryId, Account $account): ?Category
    {
        if (!$categoryId || !Uuid::isValid((string) $categoryId)) {
            return null;
        }
        $category = $this->categories->find(Uuid::fromString((string) $categoryId));
        if (!$category) {
            return null;
        }
        foreach ($account->getMembers() as $member) {
            if ($member->getUser()->getId() == $category->getOwner()->getId()) {
                return $category;
            }
        }

        return null;
    }

    private static function isValidMonth(string $month): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}$/', $month);
    }

    private static function normaliseAmount(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function asUuid(string $id): Uuid
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Identifiant invalide.');
        }

        return Uuid::fromString($id);
    }

    private function uid(): Uuid
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user->getId();
    }

    /** @return array<string,mixed> */
    private function decode(Request $request): array
    {
        return json_decode($request->getContent() ?: '{}', true) ?? [];
    }
}
