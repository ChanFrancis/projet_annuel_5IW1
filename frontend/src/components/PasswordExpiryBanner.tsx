import { Link } from 'react-router-dom';
import { useAuthStore } from '@/store/auth';

/**
 * Prompts the user to renew a password older than the rotation window (60 days,
 * enforced server-side via User::isPasswordExpired). Shown across the app.
 */
export function PasswordExpiryBanner() {
  const expired = useAuthStore((s) => s.user?.passwordExpired);
  if (!expired) return null;

  return (
    <div
      role="alert"
      className="mb-6 flex flex-col gap-2 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 sm:flex-row sm:items-center sm:justify-between"
    >
      <span>
        Votre mot de passe date de plus de 60 jours. Pour votre sécurité, merci de le
        renouveler.
      </span>
      <Link
        to="/forgot-password"
        className="shrink-0 rounded bg-amber-600 px-3 py-1.5 font-medium text-white hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500"
      >
        Renouveler le mot de passe
      </Link>
    </div>
  );
}
