# Makefile

# Variables
COMPOSE = docker-compose
DOCKER_RUN = $(COMPOSE) run --rm
DOCKER_EXEC = $(COMPOSE) exec
PHP_SERVICE = ulizaln-app
DB_SERVICE = ulizaln-db
NETWORK = ulizaln-network

# Targets
.PHONY: up down build bash artisan migrate seed phpmyadmin

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build

bash-php:
	docker exec -it $(PHP_SERVICE) bash

bash-mysql:
	docker exec -it $(DB_SERVICE) bash

artisan:
	$(DOCKER_EXEC) $(PHP_SERVICE) php artisan

migrate:
	$(DOCKER_EXEC) $(PHP_SERVICE) php artisan migrate

seed:
	$(DOCKER_EXEC) $(PHP_SERVICE) php artisan db:seed

install:
	$(DOCKER_EXEC) $(PHP_SERVICE) composer install

dump-autoload:
	$(DOCKER_EXEC) $(PHP_SERVICE) composer dump-autoload

phpmyadmin:
	$(DOCKER_RUN) --service-ports $(DB_SERVICE) phpmyadmin

network:
	docker network create $(NETWORK)
