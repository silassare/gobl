.PHONY: docs docs-dev docs-build docs-api docs-preview test test-unit test-docker test-docker-down cs cs-fix help

# Documentation

# phpDocumentor phar - downloaded on first use
PHPDOC_PHAR ?= /tmp/phpDocumentor.phar
PHPDOC_VERSION ?= 3.7.1
PHPDOC_URL = https://github.com/phpDocumentor/phpDocumentor/releases/download/v$(PHPDOC_VERSION)/phpDocumentor.phar

$(PHPDOC_PHAR):
	@echo "Downloading phpDocumentor $(PHPDOC_VERSION)..."
	@curl -sSL -o $(PHPDOC_PHAR) $(PHPDOC_URL)
	@chmod +x $(PHPDOC_PHAR)

## Start VitePress dev server
docs-dev:
	cd docs && pnpm run dev

## Generate PHP API reference (phpDocumentor)
docs-api: $(PHPDOC_PHAR)
	@echo "Generating PHP API reference..."
	php $(PHPDOC_PHAR) run --config phpdoc.xml

## Build complete docs site (API ref + VitePress static output)
docs-build: cs-fix lint docs-api
	@echo "Building VitePress site..."
	cd docs && pnpm run build

## Preview the production build locally
docs-preview:
	cd docs && pnpm run preview

## Install VitePress dependencies
docs-install:
	cd docs && pnpm install

# = Tests

## Run the full test suite
test:
	./run_test

## Run only unit tests (no live DB)
test-unit:
	vendor/bin/phpunit --exclude-group live

## Run the full test suite inside Docker (requires Docker + Compose)
test-docker:
	docker compose run --rm --build php

## Stop and remove Docker test containers
test-docker-down:
	docker compose down --volumes --remove-orphans

# = Code style

## Check code style
cs:
	vendor/bin/phpcs

## Fix code style automatically
cs-fix:
	vendor/bin/oliup-cs fix

## Lint with Psalm (static analysis) without using cache
lint:
	vendor/bin/psalm --no-cache

# = Help

## Print available targets
help:
	@grep -E '^## ' Makefile | sed 's/## //'
