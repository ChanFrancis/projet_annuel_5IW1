// Cookie-consent state for analytics (Matomo). Persisted in localStorage.
const KEY = 'copot-cookie-consent';

export type ConsentValue = 'granted' | 'denied';

export function getConsent(): ConsentValue | null {
  const v = localStorage.getItem(KEY);
  return v === 'granted' || v === 'denied' ? v : null;
}

export function setConsent(value: ConsentValue): void {
  localStorage.setItem(KEY, value);
}

export function hasAnalyticsConsent(): boolean {
  return getConsent() === 'granted';
}
