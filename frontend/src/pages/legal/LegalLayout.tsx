import { ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { useDocumentTitle } from '@/lib/useDocumentTitle';
import { Footer } from '@/components/Footer';

/**
 * Shared layout for public legal pages (CGU, CGV, privacy, cookies, contact).
 * Semantic landmarks + readable prose for accessibility.
 */
export function LegalLayout({
  title,
  updatedAt = '2026-07-11',
  children,
}: {
  title: string;
  updatedAt?: string;
  children: ReactNode;
}) {
  useDocumentTitle(`${title} — CoPot`);

  return (
    <div className="flex min-h-screen flex-col bg-slate-50">
      <header className="border-b bg-white">
        <div className="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
          <Link to="/" className="text-lg font-bold text-brand-600">
            CoPot
          </Link>
          <Link to="/login" className="text-sm text-slate-500 hover:text-brand-600">
            Se connecter
          </Link>
        </div>
      </header>

      <main id="main-content" className="mx-auto w-full max-w-3xl flex-1 px-4 py-10">
        <h1 className="mb-1 text-3xl font-bold text-slate-900">{title}</h1>
        <p className="mb-8 text-sm text-slate-500">
          Dernière mise à jour : <time dateTime={updatedAt}>{formatDate(updatedAt)}</time>
        </p>
        <div className="space-y-6 leading-relaxed text-slate-700 [&_h2]:mt-8 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-slate-900 [&_a]:text-brand-600 [&_a]:underline [&_ul]:list-disc [&_ul]:pl-6">
          {children}
        </div>
      </main>

      <Footer />
    </div>
  );
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}
