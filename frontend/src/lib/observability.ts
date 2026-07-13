// Front-end observability, all opt-in via env vars (inert when unset).
// Both are dependency-free (injected <script>), in line with the project's
// minimal-dependencies policy.
//   - Sentry : error tracking via the official Loader Script (VITE_SENTRY_LOADER_URL)
//   - Matomo : privacy-friendly analytics (VITE_MATOMO_URL + VITE_MATOMO_SITE_ID)

import { hasAnalyticsConsent } from '@/lib/consent';

export function initObservability(): void {
  initSentry();
  // Matomo (analytics) only loads once the user has consented (RGPD).
  if (hasAnalyticsConsent()) {
    initMatomo();
  }
}

/** Called by the consent banner when the user accepts analytics. */
export function enableAnalytics(): void {
  initMatomo();
}

// Sentry — official "Loader Script" (Sentry project → Settings → Client Keys →
// Loader Script). Paste that URL into VITE_SENTRY_LOADER_URL to enable.
function initSentry(): void {
  const loaderUrl = import.meta.env.VITE_SENTRY_LOADER_URL;
  if (!loaderUrl) return;

  const script = document.createElement('script');
  script.src = loaderUrl;
  script.crossOrigin = 'anonymous';
  script.async = true;
  document.head.appendChild(script);
}

// Matomo — dependency-free tracker snippet, injected only when configured.
function initMatomo(): void {
  const url = import.meta.env.VITE_MATOMO_URL;
  const siteId = import.meta.env.VITE_MATOMO_SITE_ID;
  if (!url || !siteId) return;

  const w = window as unknown as { _paq?: unknown[] };
  const _paq = (w._paq = w._paq ?? []);
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  const base = url.endsWith('/') ? url : `${url}/`;
  _paq.push(['setTrackerUrl', `${base}matomo.php`]);
  _paq.push(['setSiteId', siteId]);

  const script = document.createElement('script');
  script.async = true;
  script.src = `${base}matomo.js`;
  document.head.appendChild(script);
}

/**
 * Track a custom Matomo event (e.g. a button click).
 * No-op if Matomo isn't active yet (not configured, or consent not given):
 * `_paq` only exists once initMatomo() has run post-consent, so this respects RGPD.
 */
export function trackEvent(category: string, action: string, name?: string): void {
  const w = window as unknown as { _paq?: unknown[] };
  if (!w._paq) return;
  w._paq.push(name ? ['trackEvent', category, action, name] : ['trackEvent', category, action]);
}
