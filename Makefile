SHELL := bash

COMPOSE = docker compose
PHP     = $(COMPOSE) exec php

_TITLE := "\033[32m[%s]\033[0m %s\n"
_ERROR := "\033[31m[%s]\033[0m %s\n"

##
## This Makefile is used for *local development* only.
## It requires Docker and Docker Compose.
##

## —— General ——————————————————————————————————————————————————————————————————
.DEFAULT_GOAL := help

help: ## Show this help message
	@grep -hE '(^[0-9a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-25s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'
.PHONY: help

## —— Docker ———————————————————————————————————————————————————————————————————
build: ## Build the Docker image
	@printf $(_TITLE) "Docker" "Building PHP image"
	@$(COMPOSE) build --no-cache
.PHONY: build

up: ## Start the PHP container in background
	@printf $(_TITLE) "Docker" "Starting PHP container"
	@$(COMPOSE) up -d
.PHONY: up

down: ## Stop the PHP container
	@printf $(_TITLE) "Docker" "Stopping containers"
	@$(COMPOSE) down --remove-orphans
.PHONY: down

bash: ## Open a shell in the PHP container
	@$(PHP) sh
.PHONY: bash

## —— Dependencies —————————————————————————————————————————————————————————————
install: ## Install root dev dependencies and link local packages (run this first)
	@printf $(_TITLE) "Composer" "Installing root dependencies"
	@$(PHP) composer install
	@printf $(_TITLE) "Composer" "Linking local packages"
	@$(PHP) php .github/build-packages.php
.PHONY: install

install-component: ## Install dependencies for a component — make install-component c=platform
	@$(eval c ?=)
	@if [ -z "$(c)" ]; then \
		printf $(_ERROR) "install-component" "Usage: make install-component c=platform"; \
		exit 1; \
	fi
	@printf $(_TITLE) "Composer" "Installing dependencies for src/$(c)"
	@$(PHP) sh -c 'cd src/$(c) && composer install'
.PHONY: install-component

composer: ## Run a Composer command inside a component — make composer c=platform cmd='require vendor/pkg'
	@$(eval c ?=)
	@$(eval cmd ?=)
	@$(PHP) sh -c 'cd src/$(c) && composer $(cmd)'
.PHONY: composer

## —— Tests ————————————————————————————————————————————————————————————————————
test: ## Run PHPUnit for a component — make test c=platform
	@$(eval c ?=)
	@if [ -z "$(c)" ]; then \
		printf $(_ERROR) "test" "Usage: make test c=platform"; \
		exit 1; \
	fi
	@printf $(_TITLE) "PHPUnit" "Running tests for src/$(c)"
	@$(PHP) sh -c 'cd src/$(c) && vendor/bin/phpunit'
.PHONY: test

test-all: ## Install deps and run PHPUnit for all core components
	@for component in platform agent store chat ai-bundle mcp-bundle mate; do \
		printf $(_TITLE) "PHPUnit" "Testing $$component..."; \
		$(PHP) sh -c "cd src/$$component && composer install && vendor/bin/phpunit" || exit 1; \
	done
.PHONY: test-all

## —— Code Quality —————————————————————————————————————————————————————————————
cs: ## Fix coding standard issues (run make install first)
	@$(PHP) vendor/bin/php-cs-fixer fix
.PHONY: cs

cs-check: ## Check coding standards without fixing
	@$(PHP) vendor/bin/php-cs-fixer check --diff
.PHONY: cs-check

phpstan: ## Run PHPStan for a component — make phpstan c=platform
	@$(eval c ?=)
	@if [ -z "$(c)" ]; then \
		printf $(_ERROR) "phpstan" "Usage: make phpstan c=platform"; \
		exit 1; \
	fi
	@printf $(_TITLE) "PHPStan" "Analysing src/$(c)"
	@$(PHP) sh -c 'cd src/$(c) && vendor/bin/phpstan analyse'
.PHONY: phpstan
