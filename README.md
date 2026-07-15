# CoPot — Application de gestion de comptes bancaires

Projet de master — Gestion multi-comptes avec partage entre utilisateurs.

## Prérequis

- **Docker** & **Docker Compose**
- **Node.js 20** + **pnpm**
- **PHP 8.3** + **Composer**
- **Make**

## Démarrage rapide (Docker uniquement, sans `make`)

Aucun PHP/Composer local requis : tout tourne dans Docker.

```bash
cd CoPot
docker compose up -d --build
```

Au premier lancement, le backend installe ses dépendances, génère les clés JWT,
synchronise le schéma de base et charge les fixtures **automatiquement**
(voir `docker/backend/entrypoint-dev.sh`).

| Service        | URL                              |
|----------------|----------------------------------|
| Frontend       | http://localhost:5173            |
| API backend    | http://localhost:8000/api/health |
| Emails (Mailhog)| http://localhost:8025           |
| Adminer (DB)   | http://localhost:8080            |

### Comptes de démonstration (fixtures dev)

| Rôle  | Email               | Mot de passe     |
|-------|---------------------|------------------|
| User  | demo@copot.local    | `DemoPassw0rd!`  |
| Admin | admin@copot.local   | `AdminPassw0rd!` |

> Les emails (reset mot de passe, magic link, invitations) ne partent pas vers
> l'extérieur en dev : ils sont capturés par **Mailhog** (http://localhost:8025).

### Variante avec `make` (si disponible)

```bash
make docker-up   # démarre les services
make setup       # installe + migre + fixtures
make dev         # lance tout
```

## Structure du projet

```
CoPot/
├── Makefile               # Commandes courantes
├── docker-compose.yml     # Services Docker (dev)
├── frontend/              # Application React + Vite
│   ├── package.json
│   └── src/
├── backend/               # API Symfony
│   ├── composer.json
│   └── src/
├── docker/                # Dockerfiles
│   ├── frontend/
│   └── backend/
└── infra/                 # Infrastructure as Code
    └── ansible/
```

## Commandes disponibles

```bash
make setup        # Installation complète du projet
make dev          # Lancer l'environnement de développement
make build        # Construire les images Docker
make test         # Lancer tous les tests
make lint         # Linter le code (frontend + backend)
make docker-up    # Démarrer les services Docker
make docker-down  # Arrêter les services Docker
make docker-logs  # Voir les logs
make db-reset     # Réinitialiser la base de données
make clean        # Nettoyer les fichiers temporaires
```

## Commandes détaillées

### Frontend (React + Vite)

```bash
cd frontend
pnpm install      # Installation des dépendances
pnpm dev          # Serveur de développement (http://localhost:5173)
pnpm build        # Build de production
pnpm test         # Tests
pnpm lint         # Linter
```

### Backend (Symfony)

```bash
cd backend
composer install                              # Installation des dépendances
symfony serve                                 # Serveur de développement (http://localhost:8000)
php bin/console doctrine:migrations:migrate   # Migrations
php bin/console doctrine:fixtures:load        # Charger les fixtures
vendor/bin/phpunit                            # Tests
vendor/bin/phpstan analyse                    # Analyse statique
```

## Variables d'environnement

Copier `.env.example` en `.env.local` et adapter les valeurs :

```bash
# backend/.env.local
DATABASE_URL=postgresql://dev_user:dev_password@localhost:5432/copot_dev
APP_ENV=dev
APP_SECRET=<générer-avec-openssl-rand-hex-32>
REDIS_URL=redis://localhost:6379
MAILER_DSN=smtp://localhost:1025
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

## Services Docker (dev)

```yaml
services:
  postgres:   PostgreSQL 16    → port 5432
  redis:      Redis 7          → port 6379
```

## Développement mobile (Capacitor)

```bash
cd frontend

# Installation Capacitor
pnpm add @capacitor/core @capacitor/cli @capacitor/android @capacitor/ios

# Initialisation
npx cap init

# Ajouter les plateformes
npx cap add android
npx cap add ios

# Build et sync
pnpm build && npx cap sync

# Ouvrir dans l'IDE natif
npx cap open android
npx cap open ios
```

## Tests

```bash
make test

# Frontend uniquement
cd frontend && pnpm test

# Backend uniquement
cd backend && vendor/bin/phpunit

# Avec couverture
cd backend && vendor/bin/phpunit --coverage-html coverage/
```

## Déploiement (production)

Le déploiement cible un **VPS Ubuntu 24.04** via **Ansible** (provisioning) +
**Docker Compose** (runtime) + **GitHub Actions** (CI/CD).

**URLs de production** :

| Service  | URL                        |
|----------|----------------------------|
| App      | https://copot.fr (+ https://www.copot.fr) |
| Matomo   | https://matomo.copot.fr    |
| Grafana  | https://grafana.copot.fr   |
| Prometheus | interne uniquement (pas d'auth → jamais exposé ; tunnel SSH : `ssh -L 9090:localhost:9090 copot@<vps>`) |

**Stack production** : Traefik (reverse proxy + Let's Encrypt) → frontend (nginx
SPA) et backend (FrankenPHP). PostgreSQL + Redis en réseau interne (non publiés).
Le SPA est buildé avec `VITE_API_URL=` **vide** (same-origin) : les chemins
d'API commencent déjà par `/api` dans le code, et Traefik route `PathPrefix(/api)`
vers le backend. Les variables d'observabilité (`VITE_MATOMO_URL`,
`VITE_MATOMO_SITE_ID`, `VITE_SENTRY_LOADER_URL`) sont **figées au build** de
l'image frontend (build-args, voir [deploy.yml](.github/workflows/deploy.yml)).

La stack prod se compose de **deux fichiers** : [docker-compose.prod.yml](docker-compose.prod.yml)
(app + monitoring en profil) et [docker-compose.copot-public.prod.yml](docker-compose.copot-public.prod.yml)
(Matomo + exposition Grafana/app sur copot.fr). **Toute commande compose en prod
doit inclure les deux `-f` et `--profile monitoring`**, sinon Matomo est supprimé
(orphan) et les routes copot.fr sont perdues :

```bash
docker compose --env-file .env.prod \
  -f docker-compose.prod.yml -f docker-compose.copot-public.prod.yml \
  --profile monitoring up -d
```

### Architecture

```
                            ┌─ copot.fr, www, zap.cloud          ─▶ frontend (nginx :80)
Internet ──443──▶ Traefik ──┼─ (mêmes hosts) && /api             ─▶ backend (FrankenPHP :80)
                            ├─ matomo.copot.fr                   ─▶ matomo (+ mariadb interne)
                            └─ grafana.copot.fr                  ─▶ grafana ─▶ prometheus ─▶ backend /metrics
Traefik : TLS auto (Let's Encrypt) + redirect HTTP→HTTPS
backend ─▶ postgres, redis   (réseau interne uniquement)
```

### 0. DNS (registrar → VPS)

Chez le registrar (IONOS), pointer le domaine sur l'IP du VPS :

```
A   @     <ip-du-vps>     # copot.fr (supprimer l'AAAA par défaut du registrar)
A   *     <ip-du-vps>     # wildcard : matomo., grafana., www., …
```

Traefik obtient ensuite automatiquement les certificats Let's Encrypt.

### 1. Secrets GitHub (Settings → Secrets and variables → Actions)

- `VPS_HOST`, `VPS_USER` (=`copot`), `VPS_PORT`, `VPS_SSH_KEY` (clé privée du user `copot`)
- `GHCR_USER`, `GHCR_TOKEN` (PAT avec `write:packages`)

### 2. Provisionner le VPS (Ansible)

```bash
cd infra/ansible
cp group_vars/all.example.yml group_vars/all.yml   # remplir les secrets
ansible-vault encrypt group_vars/all.yml
cp inventory.yml inventory.local.yml               # renseigner l'hôte/VPS
ansible-playbook -i inventory.local.yml playbook.yml --vault-password-file ~/.vault_pass
```

Le playbook : apt upgrade, Docker, user `copot`, **UFW (ports 22/80/443 uniquement)**,
`docker login` GHCR, déploiement Compose, et cron de sauvegarde 3-2-1.

### 3. Pipeline CI/CD

Tout `push` sur `main` déclenche [.github/workflows/deploy.yml](.github/workflows/deploy.yml) :

1. Build des images prod (`target: prod`, avec les build-args d'observabilité pour
   le frontend) → push vers `ghcr.io/<owner>/copot-{backend,frontend}:latest` (+ tag SHA)
2. SSH sur le VPS → `git pull` → `compose pull` → `doctrine:migrations:migrate` →
   `compose up -d` (toujours avec les deux fichiers compose + profil monitoring)

> ⚠️ `IMAGE_REGISTRY` dans `.env.prod` (VPS) doit pointer sur le **même** registre
> que celui où la CI pousse (`ghcr.io/<owner>` en minuscules), et le VPS doit être
> `docker login` sur ce registre (PAT `read:packages`) — pour l'utilisateur `copot`
> **et** pour root si on déploie à la main en root.

Le workflow [.github/workflows/ci.yml](.github/workflows/ci.yml) (PHPStan, PHPUnit,
lint, build frontend) reste le gate sur les PR.

### Sauvegardes 3-2-1

- `pg_dump` (custom format) + snapshot Redis, rotation locale (7 daily + 4 weekly)
- Sync off-site vers **Backblaze B2** via `rclone` (cron nocturne posé par Ansible)
- Scripts : [infra/backups/backup.sh](infra/backups/backup.sh) / [restore.sh](infra/backups/restore.sh)
- Restaurer : `make restore FILE=/var/backups/copot/copot-*.dump`


## Troubleshooting

### Docker n'est pas accessible

```bash
sudo systemctl start docker
sudo usermod -aG docker $USER  # puis se reconnecter
```

### PostgreSQL refuse la connexion

```bash
docker-compose ps
docker-compose restart postgres
```

### Les modules npm ne sont pas trouvés

```bash
cd frontend
rm -rf node_modules pnpm-lock.yaml
pnpm install
```

## Documentation

- [React](https://react.dev)
- [Symfony](https://symfony.com/doc/current/index.html)
- [Capacitor](https://capacitorjs.com/docs)
- [Docker](https://docs.docker.com)
- [Ansible](https://docs.ansible.com)

## Licence

Projet académique — Master ESGI