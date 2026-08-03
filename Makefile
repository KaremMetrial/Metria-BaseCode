# =============================================================================
# Laravel Docker Platform
#
# Run `make` or `make help` for the full command list.
#
# Every target funnels through `docker compose --project-directory <root>`, so
# the compose files can live in compose/ while all their relative paths still
# resolve against the project root.
# =============================================================================

SHELL := /bin/bash
.DEFAULT_GOAL := help
.ONESHELL:

ROOT_DIR    := $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))
COMPOSE_DIR := $(ROOT_DIR)/compose
ENV_FILE    := $(ROOT_DIR)/.env.docker

# Match the host user so bind-mounted files are not written as root.
export UID  := $(shell id -u)
export GID  := $(shell id -g)

# BuildKit: parallel stages, cache mounts, and named build contexts.
export DOCKER_BUILDKIT        := 1
export COMPOSE_DOCKER_CLI_BUILD := 1
export COMPOSE_BAKE           := true

BASE_FILE := -f $(COMPOSE_DIR)/docker-compose.yml
DEV_FILE  := $(BASE_FILE) -f $(COMPOSE_DIR)/docker-compose.dev.yml
PROD_FILE := $(BASE_FILE) -f $(COMPOSE_DIR)/docker-compose.prod.yml
CI_FILE   := $(BASE_FILE) -f $(COMPOSE_DIR)/docker-compose.ci.yml

COMPOSE_BASE := docker compose --project-directory $(ROOT_DIR) --env-file $(ENV_FILE)
DC           := $(COMPOSE_BASE) $(DEV_FILE)
DC_PROD      := $(COMPOSE_BASE) $(PROD_FILE)
DC_CI        := $(COMPOSE_BASE) $(CI_FILE)

# Interactive when a TTY is present, non-interactive under CI.
TTY := $(shell [ -t 0 ] && echo "" || echo "-T")

EXEC_APP  := $(DC) exec $(TTY) app
RUN_APP   := $(DC) run --rm $(TTY) app
RUN_COMP  := $(DC) run --rm $(TTY) composer
RUN_NPM   := $(DC) run --rm $(TTY) npm

CYAN  := \033[0;36m
GREEN := \033[0;32m
YELLOW:= \033[0;33m
RED   := \033[0;31m
BOLD  := \033[1m
RESET := \033[0m

# =============================================================================
# Help
# =============================================================================
.PHONY: help
help: ## Show this help
	@printf "$(BOLD)Laravel Docker Platform$(RESET)\n\n"
	@printf "  $(CYAN)make <target>$(RESET)\n\n"
	@awk 'BEGIN {FS = ":.*##"} \
		/^# =+$$/ { next } \
		/^## / { printf "\n$(BOLD)%s$(RESET)\n", substr($$0, 4); next } \
		/^[a-zA-Z0-9_-]+:.*?##/ { printf "  $(CYAN)%-20s$(RESET) %s\n", $$1, $$2 }' \
		$(MAKEFILE_LIST)
	@printf "\n$(YELLOW)Pass arguments with ARGS:$(RESET) make artisan ARGS=\"route:list\"\n\n"

## Lifecycle
# =============================================================================
.PHONY: build
build: ## Build all development images
	@printf "$(CYAN)▶ building images$(RESET)\n"
	@$(DC) build --pull

.PHONY: build-nocache
build-nocache: ## Rebuild all images from scratch
	@$(DC) build --pull --no-cache

.PHONY: up
up: ## Start the stack (app, nginx, mysql, redis, queue, scheduler)
	@printf "$(CYAN)▶ starting stack$(RESET)\n"
	@$(DC) up -d --remove-orphans
	@$(MAKE) --no-print-directory health

.PHONY: dev
dev: ## Start the stack plus the Vite dev server (HMR)
	@if [[ -f package.json ]]; then \
		$(DC) --profile frontend up -d --remove-orphans; \
	else \
		$(DC) up -d --remove-orphans; \
		printf "$(YELLOW)! no package.json; starting without Vite$(RESET)\\n"; \
	fi
	@$(MAKE) --no-print-directory health
	@if [[ -f package.json ]]; then \
		printf "$(GREEN)✔ Vite dev server on http://localhost:$${VITE_PORT:-5173}$(RESET)\\n"; \
	fi

.PHONY: down
down: ## Stop and remove containers (volumes are kept)
	@$(DC) --profile frontend --profile tools down --remove-orphans

.PHONY: restart
restart: ## Restart every service
	@$(DC) restart
	@$(MAKE) --no-print-directory health

.PHONY: stop
stop: ## Stop containers without removing them
	@$(DC) stop

.PHONY: start
start: ## Start previously stopped containers
	@$(DC) start

.PHONY: ps
ps: ## Show container status
	@$(DC) ps

.PHONY: health
health: ## Wait for every healthcheck to report healthy
	@bash $(ROOT_DIR)/docker/scripts/wait-for-health.sh

.PHONY: destroy
destroy: ## ⚠ Remove containers, images AND volumes (all data is lost)
	@printf "$(RED)This deletes all containers, images and volumes for this project.$(RESET)\n"
	@read -p "Type the project name to confirm [$${COMPOSE_PROJECT_NAME:-laravel}]: " confirm; \
	if [ "$$confirm" = "$${COMPOSE_PROJECT_NAME:-laravel}" ]; then \
		$(DC) --profile frontend --profile tools down -v --rmi local --remove-orphans; \
		printf "$(GREEN)✔ destroyed$(RESET)\n"; \
	else \
		printf "$(YELLOW)aborted$(RESET)\n"; \
	fi

## Logs & shell
# =============================================================================
.PHONY: logs
logs: ## Tail logs (all services, or SERVICE=nginx)
	@$(DC) logs -f --tail=100 $(SERVICE)

.PHONY: shell
shell: ## Open a bash shell in the app container
	@$(DC) exec app bash

.PHONY: root-shell
root-shell: ## Open a ROOT shell in the app container (debugging only)
	@$(DC) exec -u 0 app bash

.PHONY: db
db: ## Open a MySQL client on the application database
	@$(DC) exec mysql sh -c 'MYSQL_PWD="$$MYSQL_PASSWORD" mysql -u"$$MYSQL_USER" "$$MYSQL_DATABASE"'

.PHONY: redis-cli
redis-cli: ## Open a redis-cli session
	@$(DC) exec redis sh -c 'REDISCLI_AUTH="$$REDIS_PASSWORD" redis-cli'

## Laravel
# =============================================================================
.PHONY: artisan
artisan: ## Run Artisan: make artisan ARGS="route:list"
	@$(EXEC_APP) php artisan $(ARGS)

.PHONY: tinker
tinker: ## Open Tinker
	@$(DC) exec app php artisan tinker

.PHONY: migrate
migrate: ## Run database migrations
	@$(EXEC_APP) php artisan migrate --no-interaction

.PHONY: migrate-fresh
migrate-fresh: ## Drop all tables and re-migrate
	@$(EXEC_APP) php artisan migrate:fresh --no-interaction

.PHONY: seed
seed: ## Run database seeders
	@$(EXEC_APP) php artisan db:seed --no-interaction

.PHONY: fresh
fresh: ## Re-migrate and seed from scratch
	@$(EXEC_APP) php artisan migrate:fresh --seed --no-interaction

.PHONY: key
key: ## Generate APP_KEY
	@$(EXEC_APP) php artisan key:generate

.PHONY: optimize
optimize: ## Cache config, routes, views and events
	@$(EXEC_APP) php artisan optimize

.PHONY: cache
cache: ## Alias for optimize
	@$(MAKE) --no-print-directory optimize

.PHONY: cache-clear
cache-clear: ## Clear every Laravel cache
	@$(EXEC_APP) php artisan optimize:clear

.PHONY: storage-link
storage-link: ## Create the public/storage symlink
	@$(EXEC_APP) php artisan storage:link

.PHONY: queue-restart
queue-restart: ## Gracefully restart the queue workers
	@$(EXEC_APP) php artisan queue:restart
	@$(DC) restart queue

## Dependencies
# =============================================================================
.PHONY: install
install: ## Install PHP and JS dependencies
	@$(RUN_COMP) install --no-interaction --prefer-dist
	@if [[ -f package.json ]]; then \
		$(RUN_NPM) install; \
	else \
		printf "$(YELLOW)! no package.json; skipping frontend dependencies$(RESET)\\n"; \
	fi

.PHONY: update
update: ## Update PHP dependencies
	@$(RUN_COMP) update --no-interaction --prefer-dist

.PHONY: composer
composer: ## Run Composer: make composer ARGS="require vendor/pkg"
	@$(RUN_COMP) $(ARGS)

.PHONY: composer-validate
composer-validate: ## Validate composer.json / composer.lock
	@$(RUN_COMP) validate --strict

.PHONY: npm-install
npm-install: ## Install JS dependencies
	@$(RUN_NPM) install

.PHONY: npm
npm: ## Run npm: make npm ARGS="run build"
	@$(RUN_NPM) $(ARGS)

.PHONY: build-assets
build-assets: ## Build frontend assets for production
	@if [[ -f package.json ]]; then \
		$(RUN_NPM) run build; \
	else \
		printf "$(YELLOW)! no package.json; no frontend assets to build$(RESET)\\n"; \
	fi

## Quality
# =============================================================================
.PHONY: test
test: ## Run the test suite
	@$(EXEC_APP) php artisan test $(ARGS)

.PHONY: test-coverage
test-coverage: ## Run tests with coverage (needs Xdebug)
	@$(DC) exec -e XDEBUG_MODE=coverage $(TTY) app php artisan test --coverage $(ARGS)

.PHONY: pest
pest: ## Run Pest directly
	@$(EXEC_APP) ./vendor/bin/pest $(ARGS)

.PHONY: phpunit
phpunit: ## Run PHPUnit directly
	@$(EXEC_APP) ./vendor/bin/phpunit $(ARGS)

.PHONY: pint
pint: ## Fix code style with Laravel Pint
	@$(EXEC_APP) ./vendor/bin/pint $(ARGS)

.PHONY: pint-test
pint-test: ## Check code style without modifying files
	@$(EXEC_APP) ./vendor/bin/pint --test

.PHONY: phpstan
phpstan: ## Run PHPStan / Larastan
	@$(EXEC_APP) ./vendor/bin/phpstan analyse --memory-limit=1G $(ARGS)

.PHONY: stan
stan: phpstan ## Alias for phpstan

.PHONY: lint
lint: pint-test phpstan ## Run all static analysis

## Backup & restore
# =============================================================================
.PHONY: backup
backup: ## Dump the database to backups/
	@bash $(ROOT_DIR)/docker/scripts/backup.sh

.PHONY: restore
restore: ## Restore a dump: make restore FILE=backups/dump.sql.gz
	@bash $(ROOT_DIR)/docker/scripts/restore.sh $(FILE)

## Production
# =============================================================================
.PHONY: prod-build
prod-build: ## Build the production images
	@printf "$(CYAN)▶ building production images$(RESET)\n"
	@$(DC_PROD) build --pull

.PHONY: prod-up
prod-up: ## Start the production stack
	@$(DC_PROD) up -d --remove-orphans
	@COMPOSE_MODE=prod bash $(ROOT_DIR)/docker/scripts/wait-for-health.sh

.PHONY: prod-down
prod-down: ## Stop the production stack
	@$(DC_PROD) down --remove-orphans

.PHONY: prod-logs
prod-logs: ## Tail production logs
	@$(DC_PROD) logs -f --tail=100 $(SERVICE)

.PHONY: prod-migrate
prod-migrate: ## Run migrations against the production stack
	@$(DC_PROD) exec $(TTY) app php artisan migrate --force --no-interaction

.PHONY: prod-ps
prod-ps: ## Show production container status
	@$(DC_PROD) ps

## CI
# =============================================================================
.PHONY: ci-build
ci-build: ## Build the CI images
	@$(DC_CI) build --pull

.PHONY: ci-up
ci-up: ## Start the CI stack
	@$(DC_CI) up -d --remove-orphans mysql redis app
	@COMPOSE_MODE=ci bash $(ROOT_DIR)/docker/scripts/wait-for-health.sh

.PHONY: ci-test
ci-test: ## Run the full CI pipeline locally
	@$(DC_CI) run --rm -T app bash -lc '\
		composer install --no-interaction --prefer-dist && \
		composer validate --strict && \
		php artisan test'

.PHONY: ci-down
ci-down: ## Tear down the CI stack
	@$(DC_CI) down -v --remove-orphans

## Diagnostics
# =============================================================================
.PHONY: config
config: ## Render the merged development compose config
	@$(DC) config

.PHONY: config-prod
config-prod: ## Render the merged production compose config
	@$(DC_PROD) config

.PHONY: validate
validate: ## Validate every compose file and Dockerfile
	@bash $(ROOT_DIR)/docker/scripts/validate.sh

.PHONY: stats
stats: ## Live resource usage per container
	@docker stats $$($(DC) ps -q)

.PHONY: info
info: ## Show Laravel's environment report
	@$(EXEC_APP) php artisan about

.PHONY: prune
prune: ## Remove dangling Docker data (project-wide safe)
	@docker system prune -f
