.PHONY: help setup dev build test lint clean docker-up docker-down

help:
	@echo "🏦 Gestion de comptes bancaires - Commandes disponibles"
	@echo ""
	@echo "  make setup        - Installation complète du projet"
	@echo "  make dev          - Lancer l'environnement de développement"
	@echo "  make build        - Construire les images Docker"
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
	@echo "🐳 Construction des images Docker..."
	@docker build -t bank-accounts-frontend:latest -f docker/frontend/Dockerfile .
	@docker build -t bank-accounts-backend:latest -f docker/backend/Dockerfile .
	@echo "✅ Images construites"

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
