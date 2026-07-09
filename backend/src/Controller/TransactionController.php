<?php

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use App\Repository\TransactionRepository;
use App\Security\AccountVoter;
use App\Service\AuditLogger;
use App\Service\JsonPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api')]
class TransactionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountRepository $accounts,
        private readonly TransactionRepository $transactions,
        private readonly CategoryRepository $categories,
        private readonly JsonPresenter $presenter,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('/accounts/{id}/transactions', name: 'tx_list', methods: ['GET'])]
    public function list(string $id): JsonResponse
    {
        $account = $this->fetchAccount($id);
        $this->denyAccessUnlessGranted(AccountVoter::VIEW, $account);

        $txs = array_map(
            fn (Transaction $t) => $this->presenter->transaction($t),
            $this->transactions->findForAccount($account),
        );

        return $this->json([
            'account' => $this->presenter->account($account),
            'transactions' => $txs,
        ]);
    }

    #[Route('/accounts/{id}/transactions', name: 'tx_create', methods: ['POST'])]
    public function create(string $id, Request $request): JsonResponse
    {
        $account = $this->fetchAccount($id);
        $this->denyAccessUnlessGranted(AccountVoter::EDIT, $account);

        $data = $this->decode($request);
        // A new transaction requires at least a label and an amount.
        if ('' === trim((string) ($data['label'] ?? '')) || !isset($data['amount'])) {
            return $this->json(['error' => 'Libellé et montant requis.'], 422);
        }
        $tx = $this->hydrate(new Transaction($account, $this->uid()), $data);
        if ($tx instanceof JsonResponse) {
            return $tx; // validation error
        }

        $this->transactions->save($tx);
        $this->audit->log('transaction.create', $this->uid(), 'Transaction', $tx->getId(), ['account' => $id]);

        return $this->json($this->presenter->transaction($tx), 201);
    }

    #[Route('/transactions/{txId}', name: 'tx_update', methods: ['PUT', 'PATCH'])]
    public function update(string $txId, Request $request): JsonResponse
    {
        $tx = $this->fetchTx($txId);
        $this->denyAccessUnlessGranted(AccountVoter::EDIT, $tx->getAccount());

        $result = $this->hydrate($tx, $this->decode($request));
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $this->em->flush();
        $this->audit->log('transaction.update', $this->uid(), 'Transaction', $tx->getId());

        return $this->json($this->presenter->transaction($tx));
    }

    #[Route('/transactions/{txId}', name: 'tx_delete', methods: ['DELETE'])]
    public function delete(string $txId): JsonResponse
    {
        $tx = $this->fetchTx($txId);
        $this->denyAccessUnlessGranted(AccountVoter::EDIT, $tx->getAccount());

        $this->audit->log('transaction.delete', $this->uid(), 'Transaction', $tx->getId());
        $this->em->remove($tx);
        $this->em->flush();

        return $this->json(null, 204);
    }

    // ---- CSV ----

    #[Route('/accounts/{id}/transactions/export', name: 'tx_export', methods: ['GET'])]
    public function export(string $id): StreamedResponse
    {
        $account = $this->fetchAccount($id);
        $this->denyAccessUnlessGranted(AccountVoter::VIEW, $account);
        $rows = $this->transactions->findForAccount($account, 100000);

        $response = new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'label', 'amount', 'category'], ';');
            foreach ($rows as $t) {
                fputcsv($out, [
                    $t->getDate()->format('Y-m-d'),
                    $t->getLabel(),
                    $t->getAmount(),
                    $t->getCategory()?->getName() ?? '',
                ], ';');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="compte-%s.csv"', $account->getId()));

        return $response;
    }

    #[Route('/accounts/{id}/transactions/import', name: 'tx_import', methods: ['POST'])]
    public function import(string $id, Request $request): JsonResponse
    {
        $account = $this->fetchAccount($id);
        $this->denyAccessUnlessGranted(AccountVoter::EDIT, $account);

        $file = $request->files->get('file');
        $csv = $file ? (string) file_get_contents($file->getPathname()) : (string) $request->getContent();
        if ('' === trim($csv)) {
            return $this->json(['error' => 'Fichier CSV vide.'], 422);
        }

        $imported = 0;
        $errors = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        foreach ($lines as $n => $line) {
            if ('' === trim($line)) {
                continue;
            }
            $cols = str_getcsv($line, ';');
            // Skip an optional header row.
            if (0 === $n && isset($cols[0]) && 'date' === strtolower(trim($cols[0]))) {
                continue;
            }
            [$date, $label, $amount] = [$cols[0] ?? '', $cols[1] ?? '', $cols[2] ?? ''];
            $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($date));
            if (!$parsedDate || '' === trim($label) || !is_numeric(str_replace(',', '.', $amount))) {
                $errors[] = ['line' => $n + 1, 'reason' => 'format invalide'];
                continue;
            }
            $tx = new Transaction($account, $this->uid());
            $tx->setDate($parsedDate);
            $tx->setLabel(trim($label));
            $tx->setAmount(number_format((float) str_replace(',', '.', $amount), 2, '.', ''));
            $this->em->persist($tx);
            ++$imported;
        }
        $this->em->flush();
        $this->audit->log('transaction.import', $this->uid(), 'Account', $account->getId(), ['imported' => $imported]);

        return $this->json(['imported' => $imported, 'errors' => $errors]);
    }

    // ---- helpers ----

    /**
     * @param array<string,mixed> $data
     *
     * @return Transaction|JsonResponse the hydrated transaction, or a 422 response
     */
    private function hydrate(Transaction $tx, array $data): Transaction|JsonResponse
    {
        if (isset($data['label'])) {
            $tx->setLabel(trim((string) $data['label']));
        }
        if (isset($data['amount'])) {
            if (!is_numeric(str_replace(',', '.', (string) $data['amount']))) {
                return $this->json(['error' => 'Montant invalide.'], 422);
            }
            $tx->setAmount(number_format((float) str_replace(',', '.', (string) $data['amount']), 2, '.', ''));
        }
        if (isset($data['date'])) {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $data['date']);
            if (!$d) {
                return $this->json(['error' => 'Date invalide (attendu Y-m-d).'], 422);
            }
            $tx->setDate($d);
        }
        if (\array_key_exists('categoryId', $data)) {
            $tx->setCategory($this->resolveCategory($data['categoryId']));
        }
        if (\array_key_exists('attachmentUrl', $data)) {
            $tx->setAttachmentUrl($data['attachmentUrl'] ? (string) $data['attachmentUrl'] : null);
        }

        return $tx;
    }

    private function resolveCategory(mixed $categoryId): ?Category
    {
        if (!$categoryId || !Uuid::isValid((string) $categoryId)) {
            return null;
        }
        /** @var User $user */
        $user = $this->getUser();
        $category = $this->categories->find(Uuid::fromString((string) $categoryId));

        return $category && $category->getOwner()->getId() == $user->getId() ? $category : null;
    }

    private function fetchAccount(string $id): Account
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $account = $this->accounts->find(Uuid::fromString($id));
        if (!$account) {
            throw $this->createNotFoundException('Compte introuvable.');
        }

        return $account;
    }

    private function fetchTx(string $txId): Transaction
    {
        if (!Uuid::isValid($txId)) {
            throw $this->createNotFoundException();
        }
        $tx = $this->transactions->find(Uuid::fromString($txId));
        if (!$tx) {
            throw $this->createNotFoundException('Opération introuvable.');
        }

        return $tx;
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
