import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore, type AuthUser } from '@/store/auth';
import { AuthShell } from './LoginPage';

const baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

/**
 * Lands here after Google OAuth. The backend redirects with the session tokens
 * in the URL fragment (#token=...&refresh_token=...) or #error=...
 */
export function OAuthCallbackPage() {
  const navigate = useNavigate();
  const setSession = useAuthStore((s) => s.setSession);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const params = new URLSearchParams(window.location.hash.slice(1));
    const err = params.get('error');
    const token = params.get('token');
    const refreshToken = params.get('refresh_token');

    if (err || !token || !refreshToken) {
      setError('La connexion avec Google a échoué.');
      return;
    }

    // Fetch the profile with the fresh token, then open the session.
    fetch(`${baseURL}/api/auth/me`, { headers: { Authorization: `Bearer ${token}` } })
      .then((res) => {
        if (!res.ok) throw new Error('me failed');
        return res.json();
      })
      .then((user: AuthUser) => {
        setSession(token, refreshToken, user);
        navigate('/', { replace: true });
      })
      .catch(() => setError('La connexion avec Google a échoué.'));
  }, [navigate, setSession]);

  return (
    <AuthShell title="Connexion Google">
      {error ? (
        <div className="text-center">
          <p className="text-sm text-red-600">{error}</p>
          <button
            onClick={() => navigate('/login')}
            className="mt-4 w-full rounded bg-brand-600 py-2 font-medium text-white hover:bg-brand-700"
          >
            Retour à la connexion
          </button>
        </div>
      ) : (
        <p className="text-center text-sm text-slate-600">Connexion en cours…</p>
      )}
    </AuthShell>
  );
}
