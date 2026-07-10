import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adminApi } from '@/api/resources';
import { useAuthStore } from '@/store/auth';
import type { AdminUser } from '@/lib/types';

const ACTION_LABELS: Record<string, string> = {
  'user.login': 'Connexion',
  'user.register': 'Inscription',
  'user.ban': 'Compte suspendu',
  'user.unban': 'Compte réactivé',
};

export function AdminPage() {
  const qc = useQueryClient();
  const [tab, setTab] = useState<'users' | 'audit'>('users');

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ['admin-users'] });
    qc.invalidateQueries({ queryKey: ['admin-audit'] });
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold">Administration</h1>
        <div className="flex gap-1 rounded-lg bg-slate-100 p-1 text-sm">
          <button
            onClick={() => setTab('users')}
            className={`rounded px-3 py-1 ${tab === 'users' ? 'bg-white shadow-sm' : 'text-slate-500'}`}
          >
            Utilisateurs
          </button>
          <button
            onClick={() => setTab('audit')}
            className={`rounded px-3 py-1 ${tab === 'audit' ? 'bg-white shadow-sm' : 'text-slate-500'}`}
          >
            Journal d'audit
          </button>
        </div>
      </div>

      {tab === 'users' ? <UsersTab onAction={invalidate} /> : <AuditTab />}
    </div>
  );
}

function UsersTab({ onAction }: { onAction: () => void }) {
  const { data: users, isLoading } = useQuery({
    queryKey: ['admin-users'],
    queryFn: adminApi.listUsers,
  });
  const meId = useAuthStore((s) => s.user?.id);

  const updateMutation = useMutation({
    mutationFn: ({ id, body }: { id: string; body: { banned?: boolean; admin?: boolean } }) =>
      adminApi.updateUser(id, body),
    onSuccess: onAction,
  });
  const reset2faMutation = useMutation({
    mutationFn: (id: string) => adminApi.reset2fa(id),
    onSuccess: onAction,
  });

  if (isLoading) return <p className="text-slate-500">Chargement…</p>;

  return (
    <section className="overflow-hidden rounded-xl border bg-white">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b text-left text-xs uppercase text-slate-400">
            <th className="p-3">Email</th>
            <th className="p-3">Rôles</th>
            <th className="p-3">2FA</th>
            <th className="p-3">Statut</th>
            <th className="p-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          {(users ?? []).map((u) => (
            <UserRow
              key={u.id}
              user={u}
              self={u.id === meId}
              onBan={(banned) => updateMutation.mutate({ id: u.id, body: { banned } })}
              onAdmin={(admin) => updateMutation.mutate({ id: u.id, body: { admin } })}
              onReset2fa={() => reset2faMutation.mutate(u.id)}
              busy={updateMutation.isPending || reset2faMutation.isPending}
            />
          ))}
        </tbody>
      </table>
    </section>
  );
}

function UserRow({
  user,
  self,
  onBan,
  onAdmin,
  onReset2fa,
  busy,
}: {
  user: AdminUser;
  self: boolean;
  onBan: (banned: boolean) => void;
  onAdmin: (admin: boolean) => void;
  onReset2fa: () => void;
  busy: boolean;
}) {
  const isAdmin = user.roles.includes('ROLE_ADMIN');
  const confirm = (msg: string, fn: () => void) => {
    if (window.confirm(msg)) fn();
  };

  return (
    <tr className="border-b last:border-0">
      <td className="p-3">{user.email}</td>
      <td className="p-3">
        <span className="rounded bg-slate-100 px-2 py-0.5 text-xs">{isAdmin ? 'Admin' : 'User'}</span>
      </td>
      <td className="p-3">{user.totpEnabled ? '✓' : '—'}</td>
      <td className="p-3">
        {user.banned ? (
          <span className="rounded bg-red-100 px-2 py-0.5 text-xs text-red-700">Suspendu</span>
        ) : (
          <span className="rounded bg-green-100 px-2 py-0.5 text-xs text-green-700">Actif</span>
        )}
      </td>
      <td className="p-3 text-right">
        {self ? (
          <span className="text-xs text-slate-400">(vous)</span>
        ) : (
          <span className="flex justify-end gap-2 text-xs">
            <button
              disabled={busy}
              onClick={() =>
                confirm(
                  user.banned ? 'Réactiver ce compte ?' : 'Suspendre ce compte ?',
                  () => onBan(!user.banned),
                )
              }
              className="text-slate-500 hover:text-brand-600"
            >
              {user.banned ? 'Réactiver' : 'Suspendre'}
            </button>
            <button
              disabled={busy}
              onClick={() =>
                confirm(
                  isAdmin ? "Révoquer le rôle admin ?" : 'Accorder le rôle admin ?',
                  () => onAdmin(!isAdmin),
                )
              }
              className="text-slate-500 hover:text-brand-600"
            >
              {isAdmin ? 'Révoquer admin' : 'Admin'}
            </button>
            {user.totpEnabled && (
              <button
                disabled={busy}
                onClick={() => confirm('Réinitialiser le 2FA de cet utilisateur ?', onReset2fa)}
                className="text-slate-500 hover:text-red-600"
              >
                Reset 2FA
              </button>
            )}
          </span>
        )}
      </td>
    </tr>
  );
}

function AuditTab() {
  const [action, setAction] = useState('');
  const [userId, setUserId] = useState('');

  const { data: logs, isLoading } = useQuery({
    queryKey: ['admin-audit', action, userId],
    queryFn: () => adminApi.auditLogs({ action: action || undefined, userId: userId || undefined }),
  });

  return (
    <section className="rounded-xl border bg-white p-5">
      <div className="mb-4 flex flex-wrap gap-2 text-sm">
        <input
          placeholder="Filtrer par action (ex: account.create)"
          value={action}
          onChange={(e) => setAction(e.target.value)}
          className="flex-1 rounded border border-slate-300 px-2 py-1.5"
        />
        <input
          placeholder="User ID"
          value={userId}
          onChange={(e) => setUserId(e.target.value)}
          className="w-56 rounded border border-slate-300 px-2 py-1.5"
        />
      </div>

      {isLoading ? (
        <p className="text-slate-500">Chargement…</p>
      ) : (
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b text-left text-xs uppercase text-slate-400">
              <th className="p-2">Date</th>
              <th className="p-2">Action</th>
              <th className="p-2">Entité</th>
              <th className="p-2">IP</th>
            </tr>
          </thead>
          <tbody>
            {(logs ?? []).map((l) => (
              <tr key={l.id} className="border-b last:border-0">
                <td className="p-2 text-slate-500">
                  {new Date(l.createdAt).toLocaleString('fr-FR')}
                </td>
                <td className="p-2 font-mono text-xs">
                  {ACTION_LABELS[l.action] ?? l.action}
                </td>
                <td className="p-2 text-slate-500">
                  {l.entity ? `${l.entity}` : '—'}
                </td>
                <td className="p-2 text-slate-400">{l.ip ?? '—'}</td>
              </tr>
            ))}
            {(logs ?? []).length === 0 && (
              <tr>
                <td colSpan={4} className="py-6 text-center text-slate-400">
                  Aucune entrée.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      )}
    </section>
  );
}
