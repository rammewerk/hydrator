.PHONY: help composer-update start

help:
	@echo "Available commands:"
	@echo "  make composer-install - Runs composer install inside a Docker container"
	@echo "  make composer-update  - Runs composer update inside a Docker container"
	@echo "  make start            - Starts the Docker Compose environment in detached mode"
	@echo "  make help             - Shows this help message"

composer-install:
	docker run --rm -v $(PWD):/app -w /app composer:latest composer install

composer-update:
	docker run --rm -v $(PWD):/app -w /app composer:latest composer update

start:
	docker compose up -d