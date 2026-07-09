import { Navigate, Route, Routes } from 'react-router-dom';
import { useAuthStore } from '@/store/auth';
import { LoginPage } from '@/pages/LoginPage';
import { RegisterPage } from '@/pages/RegisterPage';
import { ForgotPasswordPage } from '@/pages/ForgotPasswordPage';
import { ResetPasswordPage } from '@/pages/ResetPasswordPage';
import { MagicLinkPage } from '@/pages/MagicLinkPage';
import { DashboardPage } from '@/pages/DashboardPage';
import { AccountPage } from '@/pages/AccountPage';
import { CategoriesPage } from '@/pages/CategoriesPage';
import { AcceptInvitationPage } from '@/pages/AcceptInvitationPage';
import { AppLayout } from '@/components/AppLayout';

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const isAuth = useAuthStore((s) => s.isAuthenticated());
  return isAuth ? <>{children}</> : <Navigate to="/login" replace />;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route path="/magic-link" element={<MagicLinkPage />} />
      <Route path="/invitations/:token" element={<AcceptInvitationPage />} />
      <Route
        element={
          <ProtectedRoute>
            <AppLayout />
          </ProtectedRoute>
        }
      >
        <Route path="/" element={<DashboardPage />} />
        <Route path="/accounts/:id" element={<AccountPage />} />
        <Route path="/categories" element={<CategoriesPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
