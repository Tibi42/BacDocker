.PHONY: help up down build restart logs shell migrate fixtures test tailwind cache-clear setup

COMPOSE = docker compose
PHP     = $(COMPOSE) exec php

help: ## Affiche l'aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-18s\033[0m %s\n", $$1, $$2}'

setup: ## Première installation (copie .env.docker.local + build + migrations)
	@test -f .env.docker.local || cp .env.docker.local.dist .env.docker.local
	$(COMPOSE) up -d --build
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction
	$(PHP) php bin/console tailwind:build
	@echo "Application disponible sur http://localhost:8080"

up: ## Démarre les conteneurs
	$(COMPOSE) up -d

down: ## Arrête les conteneurs
	$(COMPOSE) down

build: ## Reconstruit l'image PHP
	$(COMPOSE) build --no-cache php

restart: ## Redémarre les conteneurs
	$(COMPOSE) restart

logs: ## Affiche les logs (tous services)
	$(COMPOSE) logs -f

shell: ## Ouvre un shell dans le conteneur PHP
	$(PHP) sh

migrate: ## Lance les migrations Doctrine
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction

fixtures: ## Charge les fixtures de développement
	$(PHP) php bin/console doctrine:fixtures:load --no-interaction

test: ## Lance la suite de tests PHPUnit
	$(PHP) php bin/phpunit

tailwind: ## Compile Tailwind CSS
	$(PHP) php bin/console tailwind:build

cache-clear: ## Vide le cache Symfony
	$(PHP) php bin/console cache:clear
