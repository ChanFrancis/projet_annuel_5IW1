import { Link, Outlet, useNavigate } from 'react-router-dom';
import { useAuthStore } from '@/store/auth';

export function AppLayout() {
  const user = useAuthStore((s) => s.user);
  const logout = useAuthStore((s) => s.logout);
  const navigate = useNavigate();

  return (
    <div className="min-h-screen">
      <header className="border-b bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
          <Link to="/" className="text-lg font-bold text-brand-600">
            CoPot
          </Link>
          <nav className="flex items-center gap-4 text-sm">
            <Link to="/" className="hover:text-brand-600">
              Tableau de bord
            </Link>
            <span className="text-slate-400">{user?.email}</span>
            <button
              onClick={() => {
                logout();
                navigate('/login');
              }}
              className="rounded bg-slate-100 px-3 py-1 hover:bg-slate-200"
            >
              Déconnexion
            </button>
          </nav>
        </div>
      </header>
      <main className="mx-auto max-w-6xl px-4 py-6">
        <Outlet />
      </main>
    </div>
  );
}
