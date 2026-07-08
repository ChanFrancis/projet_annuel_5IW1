import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api, ApiError } from '@/lib/api';
import { useAuthStore, AuthUser } from '@/store/auth';

const baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

interface LoginResponse {
  token: string;
  refresh_token: string;
  user: AuthUser;
}

export function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const setSession = useAuthStore((s) => s.setSession);
  const navigate = useNavigate();

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const data = await api.post<LoginResponse>('/api/auth/login', { email, password }, { auth: false });
      setSession(data.token, data.refresh_token, data.user);
      navigate('/');
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) {
        setError('Identifiants invalides.');
      } else {
        setError('Une erreur est survenue.');
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthShell title="Connexion">
      <form onSubmit={onSubmit} className="space-y-4">
        <Field label="Email" type="email" value={email} onChange={setEmail} autoComplete="email" />
        <Field
          label="Mot de passe"
          type="password"
          value={password}
          onChange={setPassword}
          autoComplete="current-password"
        />
        {error && <p className="text-sm text-red-600">{error}</p>}
        <button
          type="submit"
          disabled={loading}
          className="w-full rounded bg-brand-600 py-2 font-medium text-white hover:bg-brand-700 disabled:opacity-50"
        >
          {loading ? '…' : 'Se connecter'}
        </button>
      </form>
      <div className="mt-4 space-y-2 text-center text-sm">
        <a href={`${baseURL}/api/auth/google/connect`} className="block text-slate-500 hover:text-brand-600">
          Se connecter avec Google
        </a>
        <div className="flex justify-between text-slate-500">
          <Link to="/forgot-password" className="hover:text-brand-600">
            Mot de passe oublié ?
          </Link>
          <Link to="/magic-link" className="hover:text-brand-600">
            Lien de connexion
          </Link>
        </div>
        <p className="text-slate-500">
          Pas de compte ?{' '}
          <Link to="/register" className="text-brand-600 hover:underline">
            Inscription
          </Link>
        </p>
      </div>
    </AuthShell>
  );
}

export function AuthShell({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <div className="w-full max-w-sm rounded-xl border bg-white p-8 shadow-sm">
        <h1 className="mb-6 text-center text-2xl font-bold text-brand-600">CoPot</h1>
        <h2 className="mb-6 text-center text-lg font-semibold">{title}</h2>
        {children}
      </div>
    </div>
  );
}

export function Field({
  label,
  type,
  value,
  onChange,
  autoComplete,
}: {
  label: string;
  type: string;
  value: string;
  onChange: (v: string) => void;
  autoComplete?: string;
}) {
  return (
    <label className="block">
      <span className="mb-1 block text-sm font-medium text-slate-700">{label}</span>
      <input
        type={type}
        value={value}
        autoComplete={autoComplete}
        onChange={(e) => onChange(e.target.value)}
        required
        className="w-full rounded border border-slate-300 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
      />
    </label>
  );
}
