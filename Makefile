# Define default values for variables
COMPOSE_FILE ?= docker-compose.yml
APP_NAME ?= service

#-----------------------------------------------------------
# Management
#-----------------------------------------------------------

help: ## Show this help message
	@grep -E '^[a-zA-Z0-9_.-]+:.*?## .*$$' $(MAKEFILE_LIST) \
    	| awk 'BEGIN {FS = ":.*?## "}; {printf "  make %-20s %s\n", $$1, $$2}'

up: ## Start containers with default service sns.php
	@echo "Default service: sns. Running containers in the background..."
	docker compose -f ${COMPOSE_FILE} up -d

up.http: ## Start containers with HTTP service
	@echo "Service: http. Running containers in the background..."
	SERVICE_FILE=http.php docker compose -f ${COMPOSE_FILE} up -d

up.websocket: ## Start containers with Websocket service
	@echo "Service: websocket. Running containers in the background..."
	SERVICE_FILE=websocket.php docker compose -f ${COMPOSE_FILE} up -d

up.websocket_http: ## Start containers with HTTP and Websocket service
	@echo "Service: websocket and http. Running containers in the background..."
	SERVICE_FILE=websocket_and_http.php docker compose -f ${COMPOSE_FILE} up -d

down: ## Stop containers
	@echo "Stopping and removing containers..."
	docker compose -f ${COMPOSE_FILE} down --remove-orphans

build: ## Build containers
	@echo "Building containers..."
	docker compose -f ${COMPOSE_FILE} build

ps: ## Show list of running containers
	@echo "Listing running containers..."
	docker compose -f ${COMPOSE_FILE} ps

restart: ## Restart containers
	@echo "Restarting containers..."
	docker compose -f ${COMPOSE_FILE} restart

logs: ## View output from containers
	docker compose -f ${COMPOSE_FILE} logs --tail 500

fl: ## Follow output from containers (short of 'follow logs')
	docker compose -f ${COMPOSE_FILE} logs --tail 500 -f

prune: ## Prune stopped docker containers and dangling images
	docker system prune

#-----------------------------------------------------------
# Application
#-----------------------------------------------------------

app.bash: ## Enter the service container
	docker compose -f ${COMPOSE_FILE} exec ${APP_NAME} /bin/bash

restart.app: ## Restart the app container
	docker compose -f ${COMPOSE_FILE} restart ${APP_NAME}

## Alias to restart the app container
ra: restart.app


composer.install: ## Install composer dependencies
	docker compose -f ${COMPOSE_FILE} exec ${APP_NAME} composer install

## Alias to install composer dependencies
ci: composer.install

composer.update: ## Update composer dependencies
	docker compose -f ${COMPOSE_FILE} exec ${APP_NAME} composer update

## Alias to update composer dependencies
cu: composer.update

composer.outdated: ## Show outdated composer dependencies
	docker compose -f ${COMPOSE_FILE} exec ${APP_NAME} composer outdated

composer.autoload: ## PHP composer autoload command
	docker compose -f ${COMPOSE_FILE} exec ${APP_NAME} composer dump-autoload
