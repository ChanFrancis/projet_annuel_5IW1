import { FormEvent, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  accountsApi,
  categoriesApi,
  invitationsApi,
  transactionsApi,
  type TransactionInput,
} from '@/api/resources';
import { formatMoney, ROLE_LABELS, type AccountRole } from '@/lib/types';
import { useAuthStore } from '@/store/auth';

export function AccountPage() {
  const { id = '' } = useParams();
  const qc = useQueryClient();
  const currentUserId = useAuthStore((s) => s.user?.id);

  const { data, isLoading } = useQuery({
    queryKey: ['transactions', id],
    queryFn: () => transactionsApi.listForAccount(id),
  });
  const { data: account } = useQuery({ queryKey: ['account', id], queryFn: () => accountsApi.get(id) });
  const { data: categories } = useQuery({ queryKey: ['categories'], queryFn: categoriesApi.list });

  const myRole: AccountRole | undefined = account?.members?.find((m) => m.userId === currentUserId)?.role;
  const canEdit = myRole === 'owner' || myRole === 'co_owner';
  const canManage = myRole === 'owner';

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ['transactions', id] });
    qc.invalidateQueries({ queryKey: ['account', id] });
    qc.invalidateQueries({ queryKey: ['accounts'] });
  };

  if (isLoading) return <p className="text-slate-500">Chargement…</p>;

  return (
    <div className="space-y-6">
      <Link to="/" className="text-sm text-brand-600 hover:underline">
        ← Retour aux comptes
      </Link>

      <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-white p-5">
        <div>
          <h1 className="text-xl font-bold">{data?.account.label}</h1>
          <p className="font-mono text-xs text-slate-400">{data?.account.iban}</p>
          {myRole && <p className="mt-1 text-xs text-slate-500">Votre rôle : {ROLE_LABELS[myRole]}</p>}
        </div>
        <div className="text-right">
          <p className="text-3xl font-bold tabular-nums">
            {data && formatMoney(data.account.balance, data.account.currency)}
          </p>
          <div className="mt-2 flex justify-end gap-2 text-xs">
            <Link
              to={`/accounts/${id}/budgets`}
              className="rounded bg-slate-100 px-3 py-1 hover:bg-slate-200"
            >
              Budgets
            </Link>
            <Link
              to={`/accounts/${id}/statistics`}
              className="rounded bg-slate-100 px-3 py-1 hover:bg-slate-200"
            >
              Statistiques
            </Link>
          </div>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <TransactionsSection
            accountId={id}
            canEdit={canEdit}
            transactions={data?.transactions ?? []}
            categories={categories ?? []}
            onChange={invalidate}
          />
        </div>
        <div className="space-y-6">
          {canManage && account?.members && (
            <MembersSection accountId={id} account={account} onChange={invalidate} />
          )}
          <CsvSection accountId={id} canEdit={canEdit} onChange={invalidate} />
        </div>
      </div>
    </div>
  );
}

// ---------- Transactions ----------

function TransactionsSection({
  accountId,
  canEdit,
  transactions,
  categories,
  onChange,
}: {
  accountId: string;
  canEdit: boolean;
  transactions: import('@/lib/types').Transaction[];
  categories: import('@/lib/types').Category[];
  onChange: () => void;
}) {
  const [form, setForm] = useState<TransactionInput>({
    date: new Date().toISOString().slice(0, 10),
    label: '',
    amount: '',
    categoryId: '',
  });

  const createMutation = useMutation({
    mutationFn: () =>
      transactionsApi.create(accountId, { ...form, categoryId: form.categoryId || null }),
    onSuccess: () => {
      onChange();
      setForm({ date: new Date().toISOString().slice(0, 10), label: '', amount: '', categoryId: '' });
    },
  });
  const deleteMutation = useMutation({
    mutationFn: (txId: string) => transactionsApi.remove(txId),
    onSuccess: onChange,
  });

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (form.label.trim() && form.amount) createMutation.mutate();
  }

  return (
    <section className="rounded-xl border bg-white p-5">
      <h2 className="mb-4 font-semibold">Opérations</h2>

      {canEdit && (
        <form onSubmit={onSubmit} className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-5">
          <input
            type="date"
            value={form.date}
            onChange={(e) => setForm({ ...form, date: e.target.value })}
            className="rounded border border-slate-300 px-2 py-1.5 text-sm"
          />
          <input
            placeholder="Libellé"
            value={form.label}
            onChange={(e) => setForm({ ...form, label: e.target.value })}
            className="col-span-2 rounded border border-slate-300 px-2 py-1.5 text-sm"
          />
          <input
            placeholder="Montant"
            inputMode="decimal"
            value={form.amount}
            onChange={(e) => setForm({ ...form, amount: e.target.value })}
            className="rounded border border-slate-300 px-2 py-1.5 text-sm"
          />
          <select
            value={form.categoryId ?? ''}
            onChange={(e) => setForm({ ...form, categoryId: e.target.value })}
            className="rounded border border-slate-300 px-2 py-1.5 text-sm"
          >
            <option value="">Sans catégorie</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
          <button
            type="submit"
            className="col-span-2 rounded bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700 sm:col-span-5"
          >
            Ajouter l'opération
          </button>
        </form>
      )}

      <table className="w-full text-sm">
        <thead>
          <tr className="border-b text-left text-xs uppercase text-slate-400">
            <th className="py-2">Date</th>
            <th>Libellé</th>
            <th>Catégorie</th>
            <th className="text-right">Montant</th>
            {canEdit && <th></th>}
          </tr>
        </thead>
        <tbody>
          {transactions.map((t) => {
            const amount = parseFloat(t.amount);
            return (
              <tr key={t.id} className="border-b last:border-0">
                <td className="py-2 text-slate-500">{t.date}</td>
                <td>{t.label}</td>
                <td className="text-slate-500">{t.category?.name ?? '—'}</td>
                <td className={`text-right tabular-nums ${amount < 0 ? 'text-red-600' : 'text-green-600'}`}>
                  {formatMoney(t.amount)}
                </td>
                {canEdit && (
                  <td className="text-right">
                    <button
                      onClick={() => deleteMutation.mutate(t.id)}
                      className="text-xs text-slate-400 hover:text-red-600"
                      aria-label="Supprimer"
                    >
                      ✕
                    </button>
                  </td>
                )}
              </tr>
            );
          })}
          {transactions.length === 0 && (
            <tr>
              <td colSpan={5} className="py-6 text-center text-slate-400">
                Aucune opération.
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </section>
  );
}

// ---------- Members & invitations ----------

function MembersSection({
  accountId,
  account,
  onChange,
}: {
  accountId: string;
  account: import('@/lib/types').Account;
  onChange: () => void;
}) {
  const qc = useQueryClient();
  const [email, setEmail] = useState('');
  const [role, setRole] = useState<AccountRole>('viewer');
  const [sent, setSent] = useState(false);

  const inviteMutation = useMutation({
    mutationFn: () => invitationsApi.create(accountId, { email, role }),
    onSuccess: () => {
      setSent(true);
      setEmail('');
      qc.invalidateQueries({ queryKey: ['invitations', accountId] });
    },
  });
  const removeMember = useMutation({
    mutationFn: (memberId: string) => accountsApi.removeMember(accountId, memberId),
    onSuccess: onChange,
  });

  return (
    <section className="rounded-xl border bg-white p-5">
      <h2 className="mb-3 font-semibold">Membres</h2>
      <ul className="mb-4 space-y-2 text-sm">
        {account.members?.map((m) => (
          <li key={m.id} className="flex items-center justify-between">
            <span className="truncate">{m.email}</span>
            <span className="flex items-center gap-2">
              <span className="rounded bg-slate-100 px-2 py-0.5 text-xs">{ROLE_LABELS[m.role]}</span>
              {m.role !== 'owner' && (
                <button
                  onClick={() => removeMember.mutate(m.id)}
                  className="text-xs text-slate-400 hover:text-red-600"
                  aria-label="Retirer"
                >
                  ✕
                </button>
              )}
            </span>
          </li>
        ))}
      </ul>

      <form
        onSubmit={(e) => {
          e.preventDefault();
          if (email.trim()) inviteMutation.mutate();
        }}
        className="space-y-2 border-t pt-3"
      >
        <p className="text-xs font-medium uppercase text-slate-400">Inviter</p>
        <input
          type="email"
          placeholder="email@exemple.fr"
          value={email}
          onChange={(e) => {
            setEmail(e.target.value);
            setSent(false);
          }}
          className="w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
        />
        <div className="flex gap-2">
          <select
            value={role}
            onChange={(e) => setRole(e.target.value as AccountRole)}
            className="flex-1 rounded border border-slate-300 px-2 py-1.5 text-sm"
          >
            <option value="viewer">Lecteur</option>
            <option value="co_owner">Co-titulaire</option>
          </select>
          <button className="rounded bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
            Inviter
          </button>
        </div>
        {sent && <p className="text-xs text-green-600">Invitation envoyée ✓</p>}
      </form>
    </section>
  );
}

// ---------- CSV ----------

function CsvSection({
  accountId,
  canEdit,
  onChange,
}: {
  accountId: string;
  canEdit: boolean;
  onChange: () => void;
}) {
  const [importing, setImporting] = useState(false);
  const [result, setResult] = useState<string | null>(null);
  const token = useAuthStore((s) => s.token);

  async function onImport(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    setImporting(true);
    setResult(null);
    const fd = new FormData();
    fd.append('file', file);
    const base = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';
    const res = await fetch(`${base}/api/accounts/${accountId}/transactions/import`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
      body: fd,
    });
    const data = await res.json();
    setResult(res.ok ? `${data.imported} opération(s) importée(s).` : 'Échec de l\'import.');
    setImporting(false);
    onChange();
  }

  return (
    <section className="rounded-xl border bg-white p-5">
      <h2 className="mb-3 font-semibold">Import / Export CSV</h2>
      <a
        href={transactionsApi.exportUrl(accountId)}
        className="mb-3 block rounded bg-slate-100 px-3 py-2 text-center text-sm hover:bg-slate-200"
      >
        ⬇ Exporter en CSV
      </a>
      {canEdit && (
        <label className="block cursor-pointer rounded border border-dashed px-3 py-2 text-center text-sm text-slate-500 hover:border-brand-400">
          {importing ? 'Import…' : '⬆ Importer un CSV'}
          <input type="file" accept=".csv" onChange={onImport} className="hidden" />
        </label>
      )}
      {result && <p className="mt-2 text-xs text-green-600">{result}</p>}
      <p className="mt-2 text-xs text-slate-400">Format : date;libellé;montant;catégorie (séparateur « ; »)</p>
    </section>
  );
}
