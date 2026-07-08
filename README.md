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

Le déploiement cible un **VPS Ubuntu 24.04** via Ansible + Docker Compose.

```bash
# Provisionner le VPS
cd infra/ansible
ansible-playbook -i inventory.yml playbook.yml

# Build et push des images
make build
docker push registry.example.com/copot-frontend:latest
docker push registry.example.com/copot-backend:latest
```

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
