import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';

interface Health {
  status: string;
  database: string;
  time: string;
}

export function DashboardPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['health'],
    queryFn: () => api.get<Health>('/api/health'),
  });

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Tableau de bord</h1>

      <section className="rounded-xl border bg-white p-6">
        <h2 className="mb-2 font-semibold">État du service</h2>
        {isLoading && <p className="text-slate-500">Vérification…</p>}
        {isError && <p className="text-red-600">Backend injoignable.</p>}
        {data && (
          <ul className="text-sm text-slate-600">
            <li>Statut : <span className="font-medium text-green-600">{data.status}</span></li>
            <li>Base de données : {data.database}</li>
            <li>Horodatage : {new Date(data.time).toLocaleString('fr-FR')}</li>
          </ul>
        )}
      </section>

      <p className="text-sm text-slate-400">
        Les comptes et opérations arrivent au Sprint 2.
      </p>
    </div>
  );
}
