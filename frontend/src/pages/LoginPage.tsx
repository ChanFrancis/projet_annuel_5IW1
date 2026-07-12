import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api, ApiError } from '@/lib/api';
import { useAuthStore, AuthUser } from '@/store/auth';

const baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

interface SessionResponse {
  token: string;
  refresh_token: string;
  user: AuthUser;
}

type LoginResponse = SessionResponse | { twoFactorRequired: true; mfaToken: string };

export function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  // When the account has 2FA, we hold the intermediate token and ask for a code.
  const [mfaToken, setMfaToken] = useState<string | null>(null);
  const [code, setCode] = useState('');
  const setSession = useAuthStore((s) => s.setSession);
  const navigate = useNavigate();

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const data = await api.post<LoginResponse>('/api/auth/login', { email, password }, { auth: false });
      if ('twoFactorRequired' in data) {
        setMfaToken(data.mfaToken);
      } else {
        setSession(data.token, data.refresh_token, data.user);
        navigate('/');
      }
    } catch (err) {
      setError(err instanceof ApiError && err.status === 401 ? 'Identifiants invalides.' : 'Une erreur est survenue.');
    } finally {
      setLoading(false);
    }
  }

  async function onVerify(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const data = await api.post<SessionResponse>(
        '/api/auth/2fa/verify',
        { mfaToken, code },
        { auth: false },
      );
      setSession(data.token, data.refresh_token, data.user);
      navigate('/');
    } catch (err) {
      setError(err instanceof ApiError && err.status === 401 ? 'Code invalide ou expiré.' : 'Une erreur est survenue.');
    } finally {
      setLoading(false);
    }
  }

  // Step 2: two-factor code.
  if (mfaToken) {
    return (
      <AuthShell title="Vérification en deux étapes">
        <form onSubmit={onVerify} className="space-y-4">
          <p className="text-center text-sm text-slate-600">
            Saisissez le code à 6 chiffres de votre application d'authentification.
          </p>
          <Field
            label="Code de vérification"
            type="text"
            value={code}
            onChange={setCode}
            autoComplete="one-time-code"
            inputMode="numeric"
          />
          {error && <p className="text-sm text-red-600">{error}</p>}
          <button
            type="submit"
            disabled={loading || code.length < 6}
            className="w-full rounded bg-brand-600 py-2 font-medium text-white hover:bg-brand-700 disabled:opacity-50"
          >
            {loading ? '…' : 'Vérifier'}
          </button>
        </form>
        <button
          onClick={() => {
            setMfaToken(null);
            setCode('');
            setError(null);
          }}
          className="mt-4 w-full text-center text-sm text-slate-500 hover:text-brand-600"
        >
          ← Retour
        </button>
      </AuthShell>
    );
  }

  // Step 1: email + password.
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
    <main id="main-content" className="flex min-h-screen items-center justify-center px-4">
      <div className="w-full max-w-sm rounded-xl border bg-white p-8 shadow-sm">
        <h1 className="mb-6 text-center text-2xl font-bold text-brand-600">CoPot</h1>
        <h2 className="mb-6 text-center text-lg font-semibold">{title}</h2>
        {children}
      </div>
    </main>
  );
}

export function Field({
  label,
  type,
  value,
  onChange,
  autoComplete,
  inputMode,
}: {
  label: string;
  type: string;
  value: string;
  onChange: (v: string) => void;
  autoComplete?: string;
  inputMode?: 'numeric' | 'text' | 'email';
}) {
  return (
    <label className="block">
      <span className="mb-1 block text-sm font-medium text-slate-700">{label}</span>
      <input
        type={type}
        value={value}
        autoComplete={autoComplete}
        inputMode={inputMode}
        onChange={(e) => onChange(e.target.value)}
        required
        className="w-full rounded border border-slate-300 bg-white px-3 py-2 text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
      />
    </label>
  );
}
