# Three tiers of verification, the cadence every repository of the suite follows:
#
#   make check                              every change: code style and static analysis.
#   make test-unit FILE=... FILTER=...      a feature is done: the tests that cover it.
#   make test                               end of a cycle, and before anything is handed over.
#
# What each tier skips, and when skipping is wrong:
#
# - `check` runs phpcs over this machine's cores, and both phpcs and psalm with their caches, each
#   keyed by file content and by the tool's own configuration, so a second run reads only what
#   changed. `make check-full` ignores both caches, and is the run the cached one is checked against.
# - `test-unit` touches no database and does not empty `tests/tmp/`, where the generated ORM test
#   classes land: a change to a generator or to the sample schema needs `make test`, which rebuilds
#   them from nothing. `test-integration` is the live-database half, on MySQL and PostgreSQL
#   (`make prepare-test-dbs` first, or the servers of `docker-compose.yml`).
# - `test` is both halves, from an empty `tests/tmp/`.
#
# The tiers are about re-running, never about writing: a feature's test is written with the feature.
# A failure in a narrowed run is repeated with the full target before it is believed or reported.

.PHONY: docs docs-dev docs-build docs-api docs-preview test test-unit test-integration test-docker test-docker-down check check-full cs lint fix help

# Documentation

# phpDocumentor phar - downloaded on first use
PHPDOC_PHAR ?= /tmp/phpDocumentor.phar
PHPDOC_VERSION ?= 3.7.1
PHPDOC_URL = https://github.com/phpDocumentor/phpDocumentor/releases/download/v$(PHPDOC_VERSION)/phpDocumentor.phar
TEST_TMP_DIR ?= tests/tmp/

# phpcs reads one file at a time unless told to fork (it needs ext-pcntl). psalm counts the cores
# itself, and takes one in CI on purpose, so it is left to decide.
PHPCS_JOBS  ?= $(shell nproc 2>/dev/null || echo 1)
# phpcs caches only when given a file to cache into; psalm caches unless told not to.
PHPCS_CACHE ?= --cache=.phpcs.cache
PSALM_CACHE ?=

PHPUNIT = vendor/bin/phpunit --testdox --do-not-record-test-run-history
# FILE is a path and wins over the suite a target selects; FILTER is a class or method pattern.
NARROW  = $(if $(FILTER),--filter '$(FILTER)',) $(FILE)

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
docs-build: fix docs-api
	@echo "Building VitePress site..."
	cd docs && pnpm run build

## Preview the production build locally
docs-preview:
	cd docs && pnpm run preview

## Install VitePress dependencies
docs-install:
	cd docs && pnpm install

# = Tests

## Prepare databases for integration local tests (MySQL and PostgreSQL)
prepare-test-dbs:
	./tests/prepare_test_dbs.sh

## Run the full test suite, from an empty tests/tmp/
test:
	rm -rf $(TEST_TMP_DIR)
	mkdir -p $(TEST_TMP_DIR)/output
	$(PHPUNIT) $(NARROW)

## Run only unit tests, no live DB and no tests/tmp/ rebuild (FILE=tests/DBAL/TableTest.php, FILTER=TableTest)
test-unit:
	mkdir -p $(TEST_TMP_DIR)/output
	$(PHPUNIT) --testsuite Unit $(NARROW)

## Run only the live-database tests, on MySQL and PostgreSQL (FILE=, FILTER= narrow them)
test-integration:
	mkdir -p $(TEST_TMP_DIR)/output
	$(PHPUNIT) --testsuite Integration $(NARROW)

## Run the full test suite inside Docker (requires Docker + Compose)
test-docker:
	docker compose run --rm --build php

## Stop and remove Docker test containers
test-docker-down:
	docker compose down --volumes --remove-orphans

# = Code style

## Code style and static analysis: the gate for every change
check: cs lint

## The same two with their caches ignored: what a handover and CI run
check-full: PHPCS_CACHE := --no-cache
check-full: PSALM_CACHE := --no-cache
check-full: cs lint

## Check code style
cs:
	vendor/bin/phpcs --parallel=$(PHPCS_JOBS) $(PHPCS_CACHE)

## Lint with Psalm (static analysis)
lint:
	mkdir -p $(TEST_TMP_DIR)
	vendor/bin/psalm $(PSALM_CACHE)

## Fix code style automatically
fix: lint
	vendor/bin/oliup-cs fix
