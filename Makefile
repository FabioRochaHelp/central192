DC := docker compose
NODE_USER := $(shell id -u):$(shell id -g)

.DEFAULT_GOAL := help

.PHONY: help build up down restart logs ps shell composer-install artisan migrate migrate-fresh seed key test lint lint-fix npm-install npm-build npm-clean-build npm-install-docker npm-build-docker npm-dev setup clean

help: ## Mostra os alvos disponíveis
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-18s\033[0m %s\n", $$1, $$2}'

build: ## Constrói as imagens Docker
	$(DC) build

up: ## Sobe a stack em segundo plano
	$(DC) up -d

down: ## Para e remove os containers (mantém volumes)
	$(DC) down

restart: ## Reinicia todos os serviços
	$(DC) restart

logs: ## Segue logs (opcional: make logs s=app)
	$(DC) logs -f $(s)

ps: ## Lista containers e estado
	$(DC) ps

shell: ## Shell no container app (bash ou sh)
	@$(DC) exec app bash 2>/dev/null || $(DC) exec app sh

composer-install: ## composer install no serviço app
	$(DC) run --rm app composer install

artisan: ## Artisan via app (ex.: make artisan a="migrate --force")
	@test -n "$(a)" || (echo 'Use: make artisan a="comando"'; exit 1)
	$(DC) run --rm app php artisan $(a)

migrate: ## php artisan migrate
	$(DC) run --rm app php artisan migrate

migrate-fresh: ## php artisan migrate:fresh (apaga dados)
	$(DC) run --rm app php artisan migrate:fresh

seed: ## php artisan db:seed
	$(DC) run --rm app php artisan db:seed

key: ## php artisan key:generate
	$(DC) run --rm app php artisan key:generate

test: ## php artisan test (Pest)
	$(DC) run --rm app php artisan test

lint: ## Pint em modo verificação
	$(DC) run --rm app ./vendor/bin/pint --parallel --test

lint-fix: ## Pint aplica correções de estilo
	$(DC) run --rm app ./vendor/bin/pint --parallel

npm-install: ## npm ci no host
	npm ci

npm-build: ## npm run build no host
	$(MAKE) npm-clean-build
	npm run build

npm-clean-build: ## Remove public/build (evita EACCES por arquivos do Docker)
	$(DC) run --rm node sh -lc 'rm -rf public/build'

npm-install-docker: ## npm ci no container node (imagem PHP não inclui Node)
	$(DC) run --rm --user $(NODE_USER) node npm ci

npm-build-docker: ## npm run build no container node
	$(MAKE) npm-clean-build
	$(DC) run --rm --user $(NODE_USER) node npm run build

npm-dev: ## npm run dev no host (Vite)
	npm run dev

setup: ## Primeira configuração (requer .env; use make key se APP_KEY vazio)
	$(MAKE) composer-install
	$(MAKE) npm-install
	$(MAKE) migrate
	$(MAKE) npm-build
	@echo "Próximo: make key (se necessário), depois make up"

clean: ## Para a stack e remove volumes (apaga dados de Postgres/Redis)
	$(DC) down -v
