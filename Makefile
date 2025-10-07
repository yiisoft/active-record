help: ## Show the list of available commands with description.
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)
.DEFAULT_GOAL := help

build: ## Build services
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml --profile all build
up: ## Start services
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml --profile all up -d
ps: ## List running services
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml ps
stop: ## Stop running services
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml --profile all stop
down: ## Stop running services and remove containers, networks and volumes
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml --profile all down \
	--remove-orphans \
	--volumes
clear: ## Remove all containers, networks, volumes and images
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml --profile all down \
	--remove-orphans \
	--volumes \
    --rmi all

run: ## Run arbitrary command
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml --profile php run \
	--rm \
	--entrypoint $(CMD) \
	php

test-all: test-sqlite \
	test-mysql \
	test-pgsql \
	test-mssql \
	test-oracle
test-sqlite: ## Run SQLite tests
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml --profile php up -d
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml exec php \
		vendor/bin/phpunit --testsuite Sqlite $(RUN_ARGS)
test-mysql: ## Run MySQL tests
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml --profile mysql up -d
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml exec php-mysql \
		vendor/bin/phpunit --testsuite Mysql $(RUN_ARGS)
test-pgsql: ## Run PostgreSQL tests
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml --profile pgsql up -d
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml exec php-pgsql \
		vendor/bin/phpunit --testsuite Pgsql $(RUN_ARGS)
test-mssql: ## Run MSSQL tests
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml --profile mssql up -d
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml exec php-mssql \
		vendor/bin/phpunit --testsuite Mssql $(RUN_ARGS)
test-oracle: ## Run Oracle tests
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml --profile oracle up -d
	docker compose -f docker/docker-compose.yml -f docker/docker-compose.override.yml exec php-oracle \
		bash -c -l 'vendor/bin/phpunit --testsuite Oracle $(RUN_ARGS)'

psalm: CMD="vendor/bin/psalm --no-cache" ## Run static analysis using Psalm
psalm: run

mutation: CMD="\
vendor/bin/roave-infection-static-analysis-plugin \
--threads=2 \
--min-msi=0 \
--min-covered-msi=100 \
--ignore-msi-with-no-mutations \
--only-covered" ## Run mutation tests using Infection
mutation: run

composer-require-checker: CMD="vendor/bin/composer-require-checker" ## Check dependencies using Composer Require Checker
composer-require-checker: run

rector: CMD="vendor/bin/rector" ## Check code style using Rector
rector: run

shell: CMD="bash" ## Open interactive shell
shell: run
