import { FormEvent, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { accountsApi, budgetsApi, categoriesApi, statsApi } from '@/api/resources';
import { formatMoney, ROLE_LABELS, type AccountRole } from '@/lib/types';
import { useAuthStore } from '@/store/auth';

/** Last calendar day (YYYY-MM-DD) of a "YYYY-MM" month. */
function monthRange(month: string): { from: string; to: string } {
  const [year, mon] = month.split('-').map(Number);
  const to = new Date(Date.UTC(year, mon, 0)).getUTCDate(); // last day
  return { from: `${month}-01`, to: `${month}-${String(to).padStart(2, '0')}` };
}

function shiftMonth(month: string, delta: number): string {
  const [year, mon] = month.split('-').map(Number);
  const d = new Date(Date.UTC(year, mon - 1 + delta, 1));
  return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}`;
}

export function BudgetsPage() {
  const { id = '' } = useParams();
  const qc = useQueryClient();
  const currentUserId = useAuthStore((s) => s.user?.id);
  const [month, setMonth] = useState(() => new Date().toISOString().slice(0, 7));

  const { data: account } = useQuery({ queryKey: ['account', id], queryFn: () => accountsApi.get(id) });
  const { data: categories } = useQuery({ queryKey: ['categories'], queryFn: categoriesApi.list });
  const { data: budgets, isLoading } = useQuery({
    queryKey: ['budgets', id, month],
    queryFn: () => budgetsApi.listForAccount(id, month),
  });

  const myRole: AccountRole | undefined = account?.members?.find((m) => m.userId === currentUserId)?.role;
  const canEdit = myRole === 'owner' || myRole === 'co_owner';

  // Actual spending per category for the selected month (for the progress bars).
  const range = useMemo(() => monthRange(month), [month]);
  const { data: breakdown } = useQuery({
    queryKey: ['stats', id, 'by-category', range.from, range.to],
    queryFn: () => statsApi.byCategory(id, range.from, range.to),
  });
  const spentByCategory = useMemo(() => {
    const map = new Map<string, number>();
    for (const row of breakdown ?? []) {
      if (row.categoryId) map.set(row.categoryId, parseFloat(row.spent));
    }
    return map;
  }, [breakdown]);

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ['budgets', id, month] });
    qc.invalidateQueries({ queryKey: ['stats', id, 'by-category', range.from, range.to] });
  };

  return (
    <div className="space-y-6">
      <Link to={`/accounts/${id}`} className="text-sm text-brand-600 hover:underline">
        ← Retour au compte
      </Link>

      <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-white p-5">
        <div>
          <h1 className="text-xl font-bold">Budgets — {account?.label}</h1>
          {myRole && <p className="mt-1 text-xs text-slate-500">Votre rôle : {ROLE_LABELS[myRole]}</p>}
        </div>
        <div className="flex items-center gap-2 text-sm">
          <button
            onClick={() => setMonth((m) => shiftMonth(m, -1))}
            className="rounded bg-slate-100 px-3 py-1 hover:bg-slate-200"
          >
            ←
          </button>
          <span className="min-w-[7rem] text-center font-medium tabular-nums">{month}</span>
          <button
            onClick={() => setMonth((m) => shiftMonth(m, 1))}
            className="rounded bg-slate-100 px-3 py-1 hover:bg-slate-200"
          >
            →
          </button>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <section className="rounded-xl border bg-white p-5">
            <h2 className="mb-4 font-semibold">Budgets de {month}</h2>
            {isLoading ? (
              <p className="text-slate-500">Chargement…</p>
            ) : budgets && budgets.length > 0 ? (
              <ul className="space-y-4">
                {budgets.map((b) => {
                  const limit = parseFloat(b.amount);
                  const spent = spentByCategory.get(b.category.id) ?? 0;
                  const pct = limit > 0 ? Math.min(100, (spent / limit) * 100) : 0;
                  const over = spent > limit;
                  return (
                    <li key={b.id} className="space-y-1">
                      <div className="flex items-center justify-between text-sm">
                        <span className="font-medium">{b.category.name}</span>
                        {canEdit && (
                          <span className="flex items-center gap-2">
                            <BudgetEditor
                              accountId={id}
                              budgetId={b.id}
                              amount={b.amount}
                              onSaved={invalidate}
                            />
                            <button
                              onClick={() => budgetsApi.remove(id, b.id).then(invalidate)}
                              className="text-xs text-slate-400 hover:text-red-600"
                              aria-label="Supprimer"
                            >
                              ✕
                            </button>
                          </span>
                        )}
                      </div>
                      <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                        <div
                          className={`h-full ${over ? 'bg-red-500' : 'bg-brand-500'}`}
                          style={{ width: `${pct}%` }}
                        />
                      </div>
                      <div className="flex justify-between text-xs tabular-nums text-slate-500">
                        <span className={over ? 'text-red-600' : ''}>
                          {formatMoney(spent.toFixed(2))} dépensés
                        </span>
                        <span>sur {formatMoney(b.amount, account?.currency)}</span>
                      </div>
                    </li>
                  );
                })}
              </ul>
            ) : (
              <p className="py-6 text-center text-slate-400">Aucun budget pour ce mois.</p>
            )}
          </section>
        </div>

        {canEdit && (
          <BudgetForm
            accountId={id}
            categories={categories ?? []}
            month={month}
            existing={budgets ?? []}
            onSaved={invalidate}
          />
        )}
      </div>
    </div>
  );
}

function BudgetForm({
  accountId,
  categories,
  month,
  existing,
  onSaved,
}: {
  accountId: string;
  categories: import('@/lib/types').Category[];
  month: string;
  existing: import('@/lib/types').Budget[];
  onSaved: () => void;
}) {
  const available = categories.filter((c) => !existing.some((b) => b.category.id === c.id));
  const [categoryId, setCategoryId] = useState('');
  const [amount, setAmount] = useState('');

  const mutation = useMutation({
    mutationFn: () =>
      budgetsApi.create(accountId, {
        categoryId: categoryId || available[0]?.id || '',
        month,
        amount,
      }),
    onSuccess: () => {
      setAmount('');
      onSaved();
    },
  });

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (amount && (categoryId || available[0])) mutation.mutate();
  }

  if (available.length === 0) {
    return (
      <section className="rounded-xl border bg-white p-5 text-sm text-slate-400">
        Toutes les catégories ont déjà un budget pour ce mois.
      </section>
    );
  }

  return (
    <section className="rounded-xl border bg-white p-5">
      <h2 className="mb-3 font-semibold">Nouveau budget</h2>
      <form onSubmit={onSubmit} className="space-y-2">
        <select
          value={categoryId}
          onChange={(e) => setCategoryId(e.target.value)}
          className="w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
        >
          {available.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
        <input
          placeholder="Montant (€)"
          inputMode="decimal"
          value={amount}
          onChange={(e) => setAmount(e.target.value)}
          className="w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
        />
        <button
          type="submit"
          disabled={mutation.isPending}
          className="w-full rounded bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
        >
          Définir le budget
        </button>
        {mutation.isError && <p className="text-xs text-red-600">Montant invalide.</p>}
      </form>
    </section>
  );
}

function BudgetEditor({
  accountId,
  budgetId,
  amount,
  onSaved,
}: {
  accountId: string;
  budgetId: string;
  amount: string;
  onSaved: () => void;
}) {
  const [editing, setEditing] = useState(false);
  const [value, setValue] = useState(amount);

  const mutation = useMutation({
    mutationFn: () => budgetsApi.update(accountId, budgetId, { amount: value }),
    onSuccess: () => {
      setEditing(false);
      onSaved();
    },
  });

  if (!editing) {
    return (
      <button onClick={() => setEditing(true)} className="text-xs text-brand-600 hover:underline">
        ✎ Modifier
      </button>
    );
  }

  return (
    <span className="flex items-center gap-1">
      <input
        autoFocus
        inputMode="decimal"
        value={value}
        onChange={(e) => setValue(e.target.value)}
        className="w-20 rounded border border-slate-300 px-1 py-0.5 text-xs"
      />
      <button
        onClick={() => mutation.mutate()}
        className="text-xs text-green-600 hover:underline"
        aria-label="Enregistrer"
      >
        ✓
      </button>
      <button
        onClick={() => {
          setValue(amount);
          setEditing(false);
        }}
        className="text-xs text-slate-400 hover:underline"
        aria-label="Annuler"
      >
        ✕
      </button>
    </span>
  );
}
