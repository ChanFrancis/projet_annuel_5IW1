import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { accountsApi } from '@/api/resources';
import { ACCOUNT_TYPES, formatMoney, type AccountType } from '@/lib/types';

export function DashboardPage() {
  const qc = useQueryClient();
  const [showForm, setShowForm] = useState(false);
  const [label, setLabel] = useState('');
  const [type, setType] = useState<AccountType>('courant');

  const { data: accounts, isLoading } = useQuery({
    queryKey: ['accounts'],
    queryFn: accountsApi.list,
  });

  const createMutation = useMutation({
    mutationFn: () => accountsApi.create({ label, type }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['accounts'] });
      setLabel('');
      setType('courant');
      setShowForm(false);
    },
  });

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (label.trim()) createMutation.mutate();
  }

  const total = accounts?.reduce((sum, a) => sum + parseFloat(a.balance), 0) ?? 0;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Mes comptes</h1>
          {accounts && accounts.length > 0 && (
            <p className="text-sm text-slate-500">
              Solde total : <span className="font-semibold text-slate-700">{formatMoney(total)}</span>
            </p>
          )}
        </div>
        <button
          onClick={() => setShowForm((v) => !v)}
          className="rounded bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
        >
          {showForm ? 'Annuler' : '+ Nouveau compte'}
        </button>
      </div>

      {showForm && (
        <form onSubmit={onSubmit} className="flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4">
          <label className="flex-1">
            <span className="mb-1 block text-sm font-medium text-slate-700">Libellé</span>
            <input
              value={label}
              onChange={(e) => setLabel(e.target.value)}
              placeholder="Compte courant"
              className="w-full rounded border border-slate-300 px-3 py-2 focus:border-brand-500 focus:outline-none"
            />
          </label>
          <label>
            <span className="mb-1 block text-sm font-medium text-slate-700">Type</span>
            <select
              value={type}
              onChange={(e) => setType(e.target.value as AccountType)}
              className="rounded border border-slate-300 px-3 py-2 focus:border-brand-500 focus:outline-none"
            >
              {ACCOUNT_TYPES.map((t) => (
                <option key={t.value} value={t.value}>
                  {t.label}
                </option>
              ))}
            </select>
          </label>
          <button
            type="submit"
            disabled={createMutation.isPending}
            className="rounded bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
          >
            Créer
          </button>
        </form>
      )}

      {isLoading && <p className="text-slate-500">Chargement…</p>}

      {accounts && accounts.length === 0 && (
        <div className="rounded-xl border border-dashed bg-white p-10 text-center text-slate-500">
          Aucun compte pour l'instant. Créez-en un pour commencer.
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {accounts?.map((a) => (
          <Link
            key={a.id}
            to={`/accounts/${a.id}`}
            className="rounded-xl border bg-white p-5 transition hover:border-brand-400 hover:shadow-sm"
          >
            <div className="mb-1 flex items-center justify-between">
              <span className="rounded bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700">
                {a.typeLabel}
              </span>
            </div>
            <h2 className="font-semibold">{a.label}</h2>
            <p className="mt-2 text-2xl font-bold tabular-nums">{formatMoney(a.balance, a.currency)}</p>
            <p className="mt-2 truncate font-mono text-xs text-slate-400">{a.iban}</p>
          </Link>
        ))}
      </div>
    </div>
  );
}
