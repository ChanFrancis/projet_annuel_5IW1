import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  Bar,
  BarChart,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { accountsApi, statsApi } from '@/api/resources';
import { formatMoney } from '@/lib/types';

const PIE_COLORS = ['#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4', '#a855f7', '#ec4899', '#64748b'];

function isoDate(d: Date): string {
  return d.toISOString().slice(0, 10);
}

export function StatisticsPage() {
  const { id = '' } = useParams();

  // Default window: last 6 full months.
  const [from, setFrom] = useState(() => {
    const d = new Date();
    return isoDate(new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() - 5, 1)));
  });
  const [to, setTo] = useState(() => isoDate(new Date()));

  const { data: account } = useQuery({ queryKey: ['account', id], queryFn: () => accountsApi.get(id) });
  const { data: summary } = useQuery({
    queryKey: ['stats', id, 'summary', from, to],
    queryFn: () => statsApi.summary(id, from, to),
  });
  const { data: monthly } = useQuery({
    queryKey: ['stats', id, 'monthly', from, to],
    queryFn: () => statsApi.monthly(id, from, to),
  });
  const { data: byCategory } = useQuery({
    queryKey: ['stats', id, 'by-category', from, to],
    queryFn: () => statsApi.byCategory(id, from, to),
  });

  const monthlyData = (monthly ?? []).map((p) => ({
    month: p.month,
    Revenus: parseFloat(p.income),
    Dépenses: parseFloat(p.expenses),
  }));
  const pieData = (byCategory ?? [])
    .filter((c) => parseFloat(c.spent) > 0)
    .map((c) => ({ name: c.name, value: parseFloat(c.spent) }));

  return (
    <div className="space-y-6">
      <Link to={`/accounts/${id}`} className="text-sm text-brand-600 hover:underline">
        ← Retour au compte
      </Link>

      <div className="rounded-xl border bg-white p-5">
        <h1 className="text-xl font-bold">Statistiques — {account?.label}</h1>
        <div className="mt-3 flex flex-wrap items-center gap-3 text-sm">
          <label className="flex items-center gap-1">
            Du
            <input
              type="date"
              value={from}
              onChange={(e) => setFrom(e.target.value)}
              className="rounded border border-slate-300 px-2 py-1"
            />
          </label>
          <label className="flex items-center gap-1">
            au
            <input
              type="date"
              value={to}
              onChange={(e) => setTo(e.target.value)}
              className="rounded border border-slate-300 px-2 py-1"
            />
          </label>
        </div>
      </div>

      {/* Summary tiles */}
      <div className="grid gap-4 sm:grid-cols-3">
        <Tile label="Revenus" value={summary ? formatMoney(summary.income, account?.currency) : '—'} className="text-green-600" />
        <Tile label="Dépenses" value={summary ? formatMoney(summary.expenses, account?.currency) : '—'} className="text-red-600" />
        <Tile label="Solde net" value={summary ? formatMoney(summary.net, account?.currency) : '—'} className="text-brand-600" />
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        {/* Monthly bar chart */}
        <section className="rounded-xl border bg-white p-5">
          <h2 className="mb-4 font-semibold">Revenus vs Dépenses par mois</h2>
          {monthlyData.length > 0 ? (
            <ResponsiveContainer width="100%" height={280}>
              <BarChart data={monthlyData}>
                <XAxis dataKey="month" tick={{ fontSize: 12 }} />
                <YAxis tick={{ fontSize: 12 }} />
                <Tooltip formatter={(v: number) => formatMoney(v)} />
                <Legend />
                <Bar dataKey="Revenus" fill="#22c55e" radius={[4, 4, 0, 0]} />
                <Bar dataKey="Dépenses" fill="#ef4444" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <p className="py-10 text-center text-sm text-slate-400">Aucune donnée sur la période.</p>
          )}
        </section>

        {/* Category pie chart */}
        <section className="rounded-xl border bg-white p-5">
          <h2 className="mb-4 font-semibold">Répartition des dépenses</h2>
          {pieData.length > 0 ? (
            <ResponsiveContainer width="100%" height={280}>
              <PieChart>
                <Pie data={pieData} dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius={90}>
                  {pieData.map((_, i) => (
                    <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip formatter={(v: number) => formatMoney(v)} />
                <Legend />
              </PieChart>
            </ResponsiveContainer>
          ) : (
            <p className="py-10 text-center text-sm text-slate-400">Aucune dépense sur la période.</p>
          )}
        </section>
      </div>
    </div>
  );
}

function Tile({ label, value, className = '' }: { label: string; value: string; className?: string }) {
  return (
    <div className="rounded-xl border bg-white p-5">
      <p className="text-xs uppercase tracking-wide text-slate-400">{label}</p>
      <p className={`mt-1 text-2xl font-bold tabular-nums ${className}`}>{value}</p>
    </div>
  );
}
