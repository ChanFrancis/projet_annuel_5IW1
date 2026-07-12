import { Link, Outlet, useNavigate } from 'react-router-dom';
import { useAuthStore } from '@/store/auth';
import { Footer } from '@/components/Footer';
import { PasswordExpiryBanner } from '@/components/PasswordExpiryBanner';

export function AppLayout() {
  const user = useAuthStore((s) => s.user);
  const isAdmin = useAuthStore((s) => s.isAdmin());
  const logout = useAuthStore((s) => s.logout);
  const navigate = useNavigate();

  return (
    <div className="flex min-h-screen flex-col">
      <header className="border-b bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
          <Link to="/" className="text-lg font-bold text-brand-600">
            CoPot
          </Link>
          <nav aria-label="Navigation principale" className="flex items-center gap-4 text-sm">
            <Link to="/" className="hover:text-brand-600">
              Comptes
            </Link>
            <Link to="/categories" className="hover:text-brand-600">
              Catégories
            </Link>
            <Link to="/settings/security" className="hover:text-brand-600">
              Sécurité
            </Link>
            {isAdmin && (
              <Link to="/admin" className="hover:text-brand-600">
                Administration
              </Link>
            )}
            <span className="text-slate-400">{user?.email}</span>
            <button
              onClick={() => {
                logout();
                navigate('/login');
              }}
              className="rounded bg-slate-100 px-3 py-1 hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-500"
            >
              Déconnexion
            </button>
          </nav>
        </div>
      </header>
      <main id="main-content" className="mx-auto w-full max-w-6xl flex-1 px-4 py-6">
        <PasswordExpiryBanner />
        <Outlet />
      </main>
      <Footer />
    </div>
  );
}
