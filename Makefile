# Atalhos para o ambiente Docker. Rode `make help` para ver a lista.
COMPOSE ?= docker compose
APP     = $(COMPOSE) exec app
RUN     = $(COMPOSE) run --rm --no-deps app

.DEFAULT_GOAL := help

.PHONY: help setup env build up down restart logs ps shell install migrate fresh seed test lint format analyse check build-assets queue reset

help: ## Lista os comandos disponíveis
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

setup: env build install ## Primeira execução: .env, imagem, dependências, banco e seed
	@grep -q '^APP_KEY=base64' .env || $(RUN) php artisan key:generate --no-interaction
	@test -f storage/oauth-private.key || $(RUN) php artisan passport:keys --no-interaction
	$(COMPOSE) up -d --wait app
	$(APP) php artisan migrate:fresh --seed --no-interaction
	$(COMPOSE) up -d
	@echo "\nPronto! Acesse http://localhost:$${APP_PORT:-8000} (e-mails em http://localhost:8025)"

env: ## Cria o .env a partir do .env.example (se ainda não existir)
	@test -f .env || cp .env.example .env

build: ## Constrói a imagem da aplicação
	$(COMPOSE) build

up: ## Sobe todos os serviços (app, vite, worker, postgres, redis, mailpit)
	$(COMPOSE) up -d

down: ## Derruba os containers (mantém o banco)
	$(COMPOSE) down

restart: down up ## Reinicia os containers

logs: ## Acompanha os logs de todos os serviços
	$(COMPOSE) logs -f --tail=100

ps: ## Status dos containers
	$(COMPOSE) ps

shell: ## Abre um shell no container da aplicação
	$(APP) bash

install: ## Instala dependências PHP e Node dentro do container
	$(RUN) composer install
	$(RUN) npm install

migrate: ## Executa as migrations
	$(APP) php artisan migrate

seed: ## Popula o banco com dados de desenvolvimento
	$(APP) php artisan db:seed

fresh: ## Recria o banco do zero com dados de desenvolvimento
	$(APP) php artisan migrate:fresh --seed

test: ## Executa a suíte de testes (PostgreSQL, banco ebd_testing)
	$(APP) php artisan test

lint: ## Verifica formatação/lint PHP e front-end sem alterar arquivos
	$(APP) composer lint:check
	$(APP) npm run check

format: ## Corrige formatação PHP e front-end
	$(APP) composer lint
	$(APP) npm run check:fix

analyse: ## Análise estática (Larastan) e checagem de tipos TypeScript
	$(APP) composer types:check
	$(APP) npm run types:check

check: lint analyse test ## Tudo o que o CI executa

build-assets: ## Gera o build de produção do front-end
	$(APP) npm run build

queue: ## Mostra os logs do worker de filas
	$(COMPOSE) logs -f worker

reset: ## APAGA volumes (banco incluído) e recria tudo do zero
	$(COMPOSE) down -v --remove-orphans
	$(MAKE) setup
