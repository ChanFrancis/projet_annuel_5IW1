import { FormEvent, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { api, ApiError } from '@/lib/api';
import { AuthShell, Field } from './LoginPage';
import { passwordIssues } from '@/lib/password';

export function ResetPasswordPage() {
  const [params] = useSearchParams();
  const token = params.get('token') ?? '';
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);
  const navigate = useNavigate();

  const issues = passwordIssues(password);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    if (issues.length > 0) {
      setError('Mot de passe trop faible.');
      return;
    }
    try {
      await api.post('/api/auth/password/reset', { token, password }, { auth: false });
      setDone(true);
      setTimeout(() => navigate('/login'), 1200);
    } catch (err) {
      setError(err instanceof ApiError ? 'Lien invalide ou expiré.' : 'Une erreur est survenue.');
    }
  }

  if (!token) {
    return (
      <AuthShell title="Lien invalide">
        <p className="text-center text-sm text-red-600">Jeton manquant.</p>
      </AuthShell>
    );
  }

  return (
    <AuthShell title="Nouveau mot de passe">
      {done ? (
        <p className="text-center text-sm text-green-600">Mot de passe mis à jour. Redirection…</p>
      ) : (
        <form onSubmit={onSubmit} className="space-y-4">
          <Field
            label="Nouveau mot de passe"
            type="password"
            value={password}
            onChange={setPassword}
            autoComplete="new-password"
          />
          {password.length > 0 && issues.length > 0 && (
            <p className="text-xs text-amber-600">Requis : {issues.join(', ')}.</p>
          )}
          {error && <p className="text-sm text-red-600">{error}</p>}
          <button className="w-full rounded bg-brand-600 py-2 font-medium text-white hover:bg-brand-700">
            Réinitialiser
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
