import { expect, test } from '@playwright/test';

// Public legal pages + RGPD cookie consent (Sprint 6).

test('legal pages are publicly reachable', async ({ page }) => {
  for (const [path, heading] of [
    ['/legal/cgu', "Conditions Générales d'Utilisation"],
    ['/legal/cgv', 'Conditions Générales de Vente'],
    ['/legal/confidentialite', 'Politique de confidentialité'],
    ['/legal/cookies', 'Politique de cookies'],
    ['/legal/contact', 'Contact'],
  ] as const) {
    await page.goto(path);
    await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible();
  }
});

test('footer links to the legal pages', async ({ page }) => {
  await page.goto('/legal/cgu');
  const footer = page.getByRole('contentinfo');
  await expect(footer.getByRole('link', { name: 'Confidentialité' })).toBeVisible();
});

test('cookie consent banner can be accepted and stays dismissed', async ({ page }) => {
  await page.goto('/login');
  const banner = page.getByRole('dialog', { name: 'Consentement aux cookies' });
  await expect(banner).toBeVisible();
  await banner.getByRole('button', { name: 'Accepter' }).click();
  await expect(banner).toBeHidden();

  // Persisted: no banner after reload.
  await page.reload();
  await expect(page.getByRole('dialog', { name: 'Consentement aux cookies' })).toHaveCount(0);
});

test('skip-to-content link is present for keyboard users', async ({ page }) => {
  await page.goto('/login');
  await expect(page.getByRole('link', { name: 'Aller au contenu' })).toBeAttached();
});
