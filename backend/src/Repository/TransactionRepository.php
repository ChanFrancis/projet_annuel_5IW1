<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /** Current balance of an account = sum of its signed transaction amounts. */
    public function getBalance(Account $account): string
    {
        $sum = $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.amount), 0)')
            ->where('t.account = :account')
            ->setParameter('account', $account)
            ->getQuery()
            ->getSingleScalarResult();

        return (string) $sum;
    }

    /**
     * @return Transaction[]
     */
    public function findForAccount(Account $account, int $limit = 200): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.account = :account')
            ->setParameter('account', $account)
            ->orderBy('t.date', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(Transaction $transaction, bool $flush = true): void
    {
        $this->getEntityManager()->persist($transaction);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Per-category income and spending for an account over a date range.
     * Spending is returned as a positive number (absolute sum of debits).
     *
     * @return list<array{categoryId: ?string, name: string, income: string, spent: string}>
     */
    public function sumByCategory(Account $account, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT c.id AS category_id,
                       c.name AS name,
                       COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0) AS income,
                       COALESCE(-SUM(CASE WHEN t.amount < 0 THEN t.amount ELSE 0 END), 0) AS spent
                FROM transactions t
                LEFT JOIN categories c ON c.id = t.category_id
                WHERE t.account_id = :account
                  AND t.date >= :from
                  AND t.date <= :to
                GROUP BY c.id, c.name
                ORDER BY spent DESC
                SQL,
            [
                'account' => $account->getId()->toRfc4122(),
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ],
        );

        return array_map(
            static fn (array $r) => [
                'categoryId' => isset($r['category_id']) && is_string($r['category_id']) ? $r['category_id'] : null,
                'name' => null === $r['name'] ? 'Non catégorisé' : (string) $r['name'],
                'income' => self::castDecimal($r['income']),
                'spent' => self::castDecimal($r['spent']),
            ],
            $rows,
        );
    }

    /**
     * Monthly income and expenses series for an account.
     *
     * @return list<array{month: string, income: string, expenses: string}>
     */
    public function monthlyTotals(Account $account, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT to_char(t.date, 'YYYY-MM') AS month,
                       COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0) AS income,
                       COALESCE(-SUM(CASE WHEN t.amount < 0 THEN t.amount ELSE 0 END), 0) AS expenses
                FROM transactions t
                WHERE t.account_id = :account
                  AND t.date >= :from
                  AND t.date <= :to
                GROUP BY month
                ORDER BY month ASC
                SQL,
            [
                'account' => $account->getId()->toRfc4122(),
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ],
        );

        return array_map(
            static fn (array $r) => [
                'month' => (string) $r['month'],
                'income' => self::castDecimal($r['income']),
                'expenses' => self::castDecimal($r['expenses']),
            ],
            $rows,
        );
    }

    /**
     * @return array{income: string, expenses: string, net: string}
     */
    public function totalsForRange(Account $account, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            <<<'SQL'
                SELECT COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0) AS income,
                       COALESCE(-SUM(CASE WHEN t.amount < 0 THEN t.amount ELSE 0 END), 0) AS expenses,
                       COALESCE(SUM(t.amount), 0) AS net
                FROM transactions t
                WHERE t.account_id = :account
                  AND t.date >= :from
                  AND t.date <= :to
                SQL,
            [
                'account' => $account->getId()->toRfc4122(),
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ],
        );

        if (!\is_array($row)) {
            return ['income' => '0.00', 'expenses' => '0.00', 'net' => '0.00'];
        }

        return [
            'income' => self::castDecimal($row['income'] ?? 0),
            'expenses' => self::castDecimal($row['expenses'] ?? 0),
            'net' => self::castDecimal($row['net'] ?? 0),
        ];
    }

    private static function castDecimal(mixed $value): string
    {
        // SQL SUM() returns a numeric string already; normalise to two decimals.
        return number_format((float) $value, 2, '.', '');
    }
}
