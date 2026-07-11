import { Link } from 'react-router-dom';

const LEGAL_LINKS = [
  { to: '/legal/cgu', label: "Conditions d'utilisation" },
  { to: '/legal/cgv', label: 'Conditions de vente' },
  { to: '/legal/confidentialite', label: 'Confidentialité' },
  { to: '/legal/cookies', label: 'Cookies' },
  { to: '/legal/contact', label: 'Contact' },
];

export function Footer() {
  return (
    <footer className="border-t bg-white">
      <div className="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-6 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
        <p>© {new Date().getFullYear()} CoPot — Projet académique ESGI</p>
        <nav aria-label="Liens légaux">
          <ul className="flex flex-wrap gap-x-4 gap-y-1">
            {LEGAL_LINKS.map((l) => (
              <li key={l.to}>
                <Link to={l.to} className="hover:text-brand-600 hover:underline">
                  {l.label}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
      </div>
    </footer>
  );
}
