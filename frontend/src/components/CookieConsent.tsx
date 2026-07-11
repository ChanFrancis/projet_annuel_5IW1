import { useState } from 'react';
import { Link } from 'react-router-dom';
import { getConsent, setConsent, type ConsentValue } from '@/lib/consent';
import { enableAnalytics } from '@/lib/observability';

/**
 * RGPD cookie-consent banner. Analytics (Matomo) stays off until the user
 * explicitly accepts. Choice is persisted; the banner hides once decided.
 */
export function CookieConsent() {
  const [decided, setDecided] = useState<ConsentValue | null>(getConsent());

  if (decided) return null;

  function choose(value: ConsentValue) {
    setConsent(value);
    if (value === 'granted') enableAnalytics();
    setDecided(value);
  }

  return (
    <div
      role="dialog"
      aria-live="polite"
      aria-label="Consentement aux cookies"
      className="fixed inset-x-0 bottom-0 z-50 border-t border-slate-200 bg-white p-4 shadow-lg"
    >
      <div className="mx-auto flex max-w-4xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-sm text-slate-600">
          Nous utilisons des cookies de mesure d'audience (Matomo) pour améliorer le service.
          Les cookies nécessaires au fonctionnement restent toujours actifs.{' '}
          <Link to="/legal/cookies" className="text-brand-600 underline">
            En savoir plus
          </Link>
          .
        </p>
        <div className="flex shrink-0 gap-2">
          <button
            onClick={() => choose('denied')}
            className="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50"
          >
            Refuser
          </button>
          <button
            onClick={() => choose('granted')}
            className="rounded bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
          >
            Accepter
          </button>
        </div>
      </div>
    </div>
  );
}
