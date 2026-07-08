import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api, ApiError } from '@/lib/api';
import { AuthShell, Field } from './LoginPage';
import { passwordIssues } from '@/lib/password';

export function RegisterPage() {
  const [email, setEmail] = useState('');
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
      await api.post('/api/auth/register', { email, password }, { auth: false });
      setDone(true);
      setTimeout(() => navigate('/login'), 1200);
    } catch (err) {
      if (err instanceof ApiError && err.status === 409) {
        setError('Cet email est déjà utilisé.');
      } else {
        setError('Une erreur est survenue.');
      }
    }
  }

  if (done) {
    return (
      <AuthShell title="Compte créé">
        <p className="text-center text-sm text-green-600">
          Compte créé. Redirection vers la connexion…
        </p>
      </AuthShell>
    );
  }

  return (
    <AuthShell title="Inscription">
      <form onSubmit={onSubmit} className="space-y-4">
        <Field label="Email" type="email" value={email} onChange={setEmail} autoComplete="email" />
        <Field
          label="Mot de passe"
          type="password"
          value={password}
          onChange={setPassword}
          autoComplete="new-password"
        />
        {password.length > 0 && issues.length > 0 && (
          <p className="text-xs text-amber-600">Requis : {issues.join(', ')}.</p>
        )}
        {error && <p className="text-sm text-red-600">{error}</p>}
        <button
          type="submit"
          className="w-full rounded bg-brand-600 py-2 font-medium text-white hover:bg-brand-700"
        >
          Créer mon compte
        </button>
      </form>
      <p className="mt-4 text-center text-sm text-slate-500">
        Déjà inscrit ?{' '}
        <Link to="/login" className="text-brand-600 hover:underline">
          Connexion
        </Link>
      </p>
    </AuthShell>
  );
}
