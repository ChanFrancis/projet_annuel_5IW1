# Observabilité — CoPot

Ce document décrit la stack d'observabilité (Sprint 5) : **métriques** (Prometheus
+ Grafana), **erreurs** (Sentry), **analytics** (Matomo), et les **tests**.

## 1. Métriques — Prometheus + Grafana

Le backend expose un endpoint `/metrics` au **format Prometheus** (sans dépendance
ajoutée, voir [`MetricsController`](backend/src/Controller/MetricsController.php)) :

```
copot_up 1
copot_users_total 4
copot_accounts_total 3
copot_transactions_total 27
```

### Démarrer la stack de monitoring (profil opt-in)

```bash
docker compose --profile monitoring up -d
```

| Service    | URL (dev)               | URL (prod)                | Notes                                 |
|------------|-------------------------|---------------------------|---------------------------------------|
| Prometheus | http://localhost:9090   | interne (tunnel SSH)      | scrute `backend/metrics` (15 s)       |
| Grafana    | http://localhost:3001   | https://grafana.copot.fr  | dev : admin/admin — prod : `GRAFANA_USER`/`GRAFANA_PASSWORD` (`.env.prod`) |

Grafana est **provisionné automatiquement** : la datasource Prometheus et le
dashboard *« CoPot — Vue d'ensemble »* sont chargés au démarrage
(`monitoring/grafana/provisioning/`).

> En production, `/metrics` n'est pas routé par Traefik (seul `/api` l'est) :
> Prometheus le scrute via le réseau Docker interne. Prometheus lui-même n'est
> jamais exposé publiquement (aucune auth) — accès via tunnel SSH :
> `ssh -L 9090:localhost:9090 copot@<vps>`. Grafana, protégé par login, est
> exposé sur https://grafana.copot.fr via
> [docker-compose.copot-public.prod.yml](docker-compose.copot-public.prod.yml).

## 2. Erreurs — Sentry

Intégration **dépendance-free** via le *Loader Script* officiel de Sentry
(front) — activée uniquement si la variable d'env est renseignée :

```bash
# frontend/.env.local (dev) — en prod : build-arg de l'image (deploy.yml)
VITE_SENTRY_LOADER_URL=https://js-de.sentry-cdn.com/<clé-publique>.min.js
```

L'URL se récupère dans Sentry → **Settings → Client Keys (DSN) → Loader Script**
(⚠️ créer un projet **React/Browser** — un projet PHP n'a pas de Loader Script ;
un projet en région EU utilise le domaine `js-de.sentry-cdn.com`). Sans cette
variable, aucun script n'est chargé (inerte). La clé du loader est **publique**
(elle vit dans le bundle client) : pas un secret.

Pour le **backend** (optionnel) : `composer require sentry/sentry-symfony`, puis
renseigner `SENTRY_DSN` dans `backend/.env.local`.

## 3. Analytics — Matomo (auto-hébergé)

Également **dépendance-free** (snippet `_paq` injecté), activé par env. Le tracker
ne se charge **qu'après consentement cookies** (RGPD, `CookieConsent.tsx`), et un
événement métier custom est suivi : `Account / click_new_account_button`
(helper `trackEvent()` dans `frontend/src/lib/observability.ts`).

**Dev** — Matomo local via [docker-compose.matomo.yml](docker-compose.matomo.yml) :

```bash
docker compose -f docker-compose.matomo.yml up -d    # http://localhost:8090
# frontend/.env.local
VITE_MATOMO_URL=http://localhost:8090
VITE_MATOMO_SITE_ID=1
```

**Prod** — Matomo (+ MariaDB interne) est déployé par
[docker-compose.copot-public.prod.yml](docker-compose.copot-public.prod.yml)
derrière Traefik : **https://matomo.copot.fr** (HTTPS auto). Les variables sont
figées au **build** de l'image frontend (build-args dans
[deploy.yml](.github/workflows/deploy.yml)). Données persistées dans les volumes
`matomo_db` / `matomo_app`.

## 4. Tests

| Type            | Outil               | Emplacement                    | Lancer                                  |
|-----------------|---------------------|--------------------------------|-----------------------------------------|
| Unitaires       | PHPUnit             | `backend/tests/{Entity,Enum,Service,Security}` | `docker compose exec backend vendor/bin/phpunit` |
| Fonctionnels    | PHPUnit WebTestCase | `backend/tests/Functional`     | idem (base de test requise)             |
| E2E UI          | Playwright          | `frontend/e2e`                 | `npm run test:e2e` (stack démarrée)     |
| Analyse statique| PHPStan (niveau 6)  | —                              | `docker compose exec backend vendor/bin/phpstan analyse` |

### Base de test (fonctionnels)

```bash
docker compose exec backend php bin/console doctrine:database:create --env=test --if-not-exists
docker compose exec backend php bin/console doctrine:migrations:migrate --env=test -n
docker compose exec backend vendor/bin/phpunit
```

### E2E Playwright (première fois)

```bash
cd frontend
npx playwright install chromium
npm run test:e2e
```
