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
import { BudgetsPage } from '@/pages/BudgetsPage';
import { StatisticsPage } from '@/pages/StatisticsPage';
import { AdminPage } from '@/pages/AdminPage';
import { AcceptInvitationPage } from '@/pages/AcceptInvitationPage';
import { TermsPage, SalesPage, PrivacyPage, CookiesPage, ContactPage } from '@/pages/legal';
import { AppLayout } from '@/components/AppLayout';
import { AdminRoute } from '@/components/AdminRoute';
import { CookieConsent } from '@/components/CookieConsent';

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const isAuth = useAuthStore((s) => s.isAuthenticated());
  return isAuth ? <>{children}</> : <Navigate to="/login" replace />;
}

export default function App() {
  return (
    <>
      {/* Accessibility: let keyboard users skip the nav straight to the content */}
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-white"
      >
        Aller au contenu
      </a>

      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route path="/forgot-password" element={<ForgotPasswordPage />} />
        <Route path="/reset-password" element={<ResetPasswordPage />} />
        <Route path="/magic-link" element={<MagicLinkPage />} />
        <Route path="/invitations/:token" element={<AcceptInvitationPage />} />

        {/* Public legal pages (RGPD) */}
        <Route path="/legal/cgu" element={<TermsPage />} />
        <Route path="/legal/cgv" element={<SalesPage />} />
        <Route path="/legal/confidentialite" element={<PrivacyPage />} />
        <Route path="/legal/cookies" element={<CookiesPage />} />
        <Route path="/legal/contact" element={<ContactPage />} />

        <Route
          element={
            <ProtectedRoute>
              <AppLayout />
            </ProtectedRoute>
          }
        >
          <Route path="/" element={<DashboardPage />} />
          <Route path="/accounts/:id" element={<AccountPage />} />
          <Route path="/accounts/:id/budgets" element={<BudgetsPage />} />
          <Route path="/accounts/:id/statistics" element={<StatisticsPage />} />
          <Route path="/categories" element={<CategoriesPage />} />
          <Route
            path="/admin"
            element={
              <AdminRoute>
                <AdminPage />
              </AdminRoute>
            }
          />
        </Route>
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>

      <CookieConsent />
    </>
  );
}
