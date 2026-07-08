import { FormEvent, useEffect, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { api } from '@/lib/api';
import { AuthShell, Field } from './LoginPage';
import { useAuthStore, AuthUser } from '@/store/auth';

interface SessionResponse {
  token: string;
  refresh_token: string;
  user: AuthUser;
}

// Two roles: request a magic link (no token) or consume one (?token=...).
export function MagicLinkPage() {
  const [params] = useSearchParams();
  const token = params.get('token');
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const setSession = useAuthStore((s) => s.setSession);
  const navigate = useNavigate();

  useEffect(() => {
    if (!token) return;
    api
      .post<SessionResponse>('/api/auth/magic-link/consume', { token }, { auth: false })
      .then((data) => {
        setSession(data.token, data.refresh_token, data.user);
        navigate('/');
      })
      .catch(() => setError('Lien invalide ou expiré.'));
  }, [token, setSession, navigate]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    await api.post('/api/auth/magic-link/request', { email }, { auth: false }).catch(() => {});
    setSent(true);
  }

  if (token) {
    return (
      <AuthShell title="Connexion par lien">
        {error ? (
          <p className="text-center text-sm text-red-600">{error}</p>
        ) : (
          <p className="text-center text-sm text-slate-600">Connexion en cours…</p>
        )}
      </AuthShell>
    );
  }

  return (
    <AuthShell title="Lien de connexion">
      {sent ? (
        <p className="text-center text-sm text-slate-600">
          Si un compte existe, un lien de connexion a été envoyé à{' '}
          <span className="font-medium">{email}</span>.
        </p>
      ) : (
        <form onSubmit={onSubmit} className="space-y-4">
          <Field label="Email" type="email" value={email} onChange={setEmail} autoComplete="email" />
          <button className="w-full rounded bg-brand-600 py-2 font-medium text-white hover:bg-brand-700">
            Recevoir un lien
          </button>
        </form>
      )}
      <p className="mt-4 text-center text-sm text-slate-500">
        <Link to="/login" className="text-brand-600 hover:underline">
          Retour à la connexion
        </Link>
      </p>
    </AuthShell>
  );
}
