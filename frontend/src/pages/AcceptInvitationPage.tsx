import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { invitationsApi } from '@/api/resources';
import { ROLE_LABELS } from '@/lib/types';
import { useAuthStore } from '@/store/auth';
import { AuthShell } from './LoginPage';

export function AcceptInvitationPage() {
  const { token = '' } = useParams();
  const navigate = useNavigate();
  const isAuth = useAuthStore((s) => s.isAuthenticated());

  const { data, isLoading, isError } = useQuery({
    queryKey: ['invitation', token],
    queryFn: () => invitationsApi.show(token),
    retry: false,
  });

  const acceptMutation = useMutation({
    mutationFn: () => invitationsApi.accept(token),
    onSuccess: (account) => navigate(`/accounts/${account.id}`),
  });
  const declineMutation = useMutation({
    mutationFn: () => invitationsApi.decline(token),
    onSuccess: () => navigate('/'),
  });

  if (isLoading) return <AuthShell title="Invitation">Chargement…</AuthShell>;
  if (isError || !data)
    return (
      <AuthShell title="Invitation">
        <p className="text-center text-sm text-red-600">Invitation invalide ou expirée.</p>
      </AuthShell>
    );

  if (!isAuth) {
    return (
      <AuthShell title="Invitation">
        <p className="text-center text-sm text-slate-600">
          Vous êtes invité à rejoindre le compte « <b>{data.accountLabel}</b> » en tant que{' '}
          {ROLE_LABELS[data.role]}.
        </p>
        <p className="mt-4 text-center text-sm text-slate-500">
          Connectez-vous avec l'adresse <b>{data.email}</b> pour accepter.
        </p>
        <button
          onClick={() => navigate('/login')}
          className="mt-4 w-full rounded bg-brand-600 py-2 font-medium text-white hover:bg-brand-700"
        >
          Se connecter
        </button>
      </AuthShell>
    );
  }

  return (
    <AuthShell title="Invitation">
      <p className="text-center text-sm text-slate-600">
        Rejoindre le compte « <b>{data.accountLabel}</b> » en tant que {ROLE_LABELS[data.role]} ?
      </p>
      <div className="mt-6 flex gap-3">
        <button
          onClick={() => declineMutation.mutate()}
          className="flex-1 rounded bg-slate-100 py-2 text-sm hover:bg-slate-200"
        >
          Refuser
        </button>
        <button
          onClick={() => acceptMutation.mutate()}
          disabled={acceptMutation.isPending}
          className="flex-1 rounded bg-brand-600 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
        >
          Accepter
        </button>
      </div>
    </AuthShell>
  );
}
