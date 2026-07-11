import { expect, test } from '@playwright/test';

// Smoke E2E covering the auth entry points. Assumes the dev stack is running
// with the demo fixtures loaded (demo@copot.local / DemoPassw0rd!).

test('unauthenticated visit redirects to the login page', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveURL(/\/login$/);
  await expect(page.getByRole('heading', { name: 'Connexion' })).toBeVisible();
});

test('invalid credentials show an error', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill('demo@copot.local');
  await page.getByLabel('Mot de passe').fill('wrong-password');
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page.getByText('Identifiants invalides.')).toBeVisible();
});

test('demo user can log in and reach the dashboard', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill('demo@copot.local');
  await page.getByLabel('Mot de passe').fill('DemoPassw0rd!');
  await page.getByRole('button', { name: 'Se connecter' }).click();

  await expect(page).toHaveURL(/\/$/);
  await expect(page.getByRole('heading', { name: 'Mes comptes' })).toBeVisible();
});

test('login page exposes password recovery and magic-link entry points', async ({ page }) => {
  await page.goto('/login');
  await expect(page.getByRole('link', { name: 'Mot de passe oublié ?' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Lien de connexion' })).toBeVisible();
});
