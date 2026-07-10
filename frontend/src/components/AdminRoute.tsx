import { Navigate } from 'react-router-dom';
import { useAuthStore } from '@/store/auth';

/** Gate admin-only routes: redirect non-admins back to the dashboard. */
export function AdminRoute({ children }: { children: React.ReactNode }) {
  const isAdmin = useAuthStore((s) => s.isAdmin());
  return isAdmin ? <>{children}</> : <Navigate to="/" replace />;
}
