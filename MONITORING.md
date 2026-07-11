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

| Service    | URL                     | Notes                                  |
|------------|-------------------------|----------------------------------------|
| Prometheus | http://localhost:9090   | scrute `backend:8000/metrics` (15 s)   |
| Grafana    | http://localhost:3001   | admin / admin (par défaut, à changer)  |

Grafana est **provisionné automatiquement** : la datasource Prometheus et le
dashboard *« CoPot — Vue d'ensemble »* sont chargés au démarrage
(`monitoring/grafana/provisioning/`).

> En production, `/metrics` ne doit être accessible que depuis le réseau interne
> de monitoring (ne pas l'exposer via Traefik).

## 2. Erreurs — Sentry

Intégration **dépendance-free** via le *Loader Script* officiel de Sentry
(front) — activée uniquement si la variable d'env est renseignée :

```bash
# frontend/.env.local
VITE_SENTRY_LOADER_URL=https://js.sentry-cdn.com/<clé-publique>.min.js
```

L'URL se récupère dans Sentry → **Settings → Client Keys (DSN) → Loader Script**.
Sans cette variable, aucun script n'est chargé (inerte).

Pour le **backend** (optionnel) : `composer require sentry/sentry-symfony`, puis
renseigner `SENTRY_DSN` dans `backend/.env.local`.

## 3. Analytics — Matomo

Également **dépendance-free** (snippet `_paq` injecté), activé par env :

```bash
# frontend/.env.local
VITE_MATOMO_URL=https://matomo.example.com
VITE_MATOMO_SITE_ID=1
```

Héberger Matomo : image `matomo` + une base MariaDB (hors périmètre de ce repo,
à déployer à côté). Voir la [doc Matomo](https://matomo.org/docs/installation/).

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
