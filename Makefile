.PHONY: help setup dev build build-prod push deploy migrate-prod prod-up prod-down prod-logs backup restore test lint docker-up docker-down clean db-reset

# --- Prod image registry (override with: make push IMAGE_REGISTRY=ghcr.io/<owner>) ---
IMAGE_REGISTRY ?= ghcr.io/chanfrancis

help:
	@echo "🏦 Gestion de comptes bancaires - Commandes disponibles"
	@echo ""
	@echo "  make setup        - Installation complète du projet"
	@echo "  make dev          - Lancer l'environnement de développement"
	@echo "  make build        - Construire les images Docker (dev)"
	@echo "  make build-prod   - Construire les images de production"
	@echo "  make push         - Pousser les images prod vers GHCR"
	@echo "  make deploy       - SSH + pull + migrate + up (production)"
	@echo "  make prod-up      - Lancer la stack production (local/VPS)"
	@echo "  make prod-down    - Arrêter la stack production"
	@echo "  make migrate-prod - Appliquer les migrations en production"
	@echo "  make backup       - Lancer une sauvegarde (VPS)"
	@echo "  make restore FILE=- Restaurer une sauvegarde (VPS)"
	@echo "  make test         - Lancer tous les tests"
	@echo "  make lint         - Linter le code (frontend + backend)"
	@echo "  make docker-up    - Démarrer les services Docker"
	@echo "  make docker-down  - Arrêter les services Docker"
	@echo "  make clean        - Nettoyer les fichiers temporaires"

setup:
	@echo "🚀 Installation du projet..."
	@if [ -d "frontend" ]; then \
		cd frontend && pnpm install; \
	fi
	@if [ -d "backend" ]; then \
		cd backend && composer install; \
	fi
	@docker-compose up -d postgres redis
	@sleep 5
	@if [ -d "backend" ]; then \
		cd backend && \
		php bin/console doctrine:database:create --if-not-exists && \
		php bin/console doctrine:migrations:migrate --no-interaction && \
		php bin/console doctrine:fixtures:load --no-interaction || true; \
	fi
	@echo "✅ Installation terminée !"

dev:
	@echo "🚀 Démarrage de l'environnement de développement..."
	@docker-compose up -d
	@echo "Frontend: http://localhost:5173"
	@echo "Backend: http://localhost:8000"
	@echo "Utilisez 'make docker-logs' pour voir les logs"

build:
	@echo "🐳 Construction des images Docker (dev)..."
	@docker build -t copot-frontend:dev -f docker/frontend/Dockerfile --target dev .
	@docker build -t copot-backend:dev -f docker/backend/Dockerfile --target dev .
	@echo "✅ Images construites"

build-prod:
	@echo "🐳 Construction des images de production..."
	@docker build -t $(IMAGE_REGISTRY)/copot-frontend:latest -f docker/frontend/Dockerfile --target prod --build-arg VITE_API_URL=/api .
	@docker build -t $(IMAGE_REGISTRY)/copot-backend:latest -f docker/backend/Dockerfile --target prod .
	@echo "✅ Images prod construites: $(IMAGE_REGISTRY)/copot-{frontend,backend}:latest"

push: build-prod
	@echo "📤 Push vers $(IMAGE_REGISTRY)..."
	@docker push $(IMAGE_REGISTRY)/copot-frontend:latest
	@docker push $(IMAGE_REGISTRY)/copot-backend:latest
	@echo "✅ Images poussées"

# --- Production stack (run on the VPS or locally with a valid .env.prod) ---
PROD_COMPOSE = docker compose --env-file .env.prod -f docker-compose.prod.yml

prod-up:
	@$(PROD_COMPOSE) up -d

prod-down:
	@$(PROD_COMPOSE) down

prod-logs:
	@$(PROD_COMPOSE) logs -f

migrate-prod:
	@$(PROD_COMPOSE) run --rm backend php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

deploy:
	@echo "🚀 Déploiement en production via SSH..."
	@ssh $(SSH_TARGET) 'cd /opt/copot && $(PROD_COMPOSE) pull && $(PROD_COMPOSE) run --rm backend php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration && $(PROD_COMPOSE) up -d --remove-orphans'

# --- Backups (run on the VPS) ---
backup:
	@infra/backups/backup.sh

restore:
	@if [ -z "$(FILE)" ]; then echo "Usage: make restore FILE=/var/backups/copot/copot-*.dump"; exit 1; fi
	@infra/backups/restore.sh "$(FILE)"

test:
	@echo "🧪 Lancement des tests..."
	@if [ -d "frontend" ]; then \
		cd frontend && pnpm test; \
	fi
	@if [ -d "backend" ]; then \
		cd backend && vendor/bin/phpunit; \
	fi

lint:
	@echo "🔍 Linting du code..."
	@if [ -d "frontend" ]; then \
		cd frontend && pnpm lint; \
	fi
	@if [ -d "backend" ]; then \
		cd backend && \
		vendor/bin/phpstan analyse src tests && \
		vendor/bin/php-cs-fixer fix --dry-run --diff; \
	fi

docker-up:
	@docker-compose up -d

docker-down:
	@docker-compose down

docker-logs:
	@docker-compose logs -f

clean:
	@echo "🧹 Nettoyage..."
	@rm -rf frontend/node_modules/.cache
	@rm -rf backend/var/cache/*
	@rm -rf backend/var/log/*
	@echo "✅ Nettoyage terminé"

db-reset:
	@echo "🗄️  Réinitialisation de la base de données..."
	@cd backend && \
		php bin/console doctrine:database:drop --force --if-exists && \
		php bin/console doctrine:database:create && \
		php bin/console doctrine:migrations:migrate --no-interaction && \
		php bin/console doctrine:fixtures:load --no-interaction
	@echo "✅ Base de données réinitialisée"
