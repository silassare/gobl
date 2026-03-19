FROM php:8.3-cli

# System dependencies (cached as a layer)
RUN apt-get update -qq \
	&& apt-get install -y --no-install-recommends \
	libpq-dev \
	unzip \
	git \
	&& docker-php-ext-install -j"$(nproc)" pdo pdo_mysql pdo_pgsql bcmath \
	&& rm -rf /var/lib/apt/lists/*

# Composer (cached as a layer)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

# Install PHP dependencies as a separate cacheable layer.
# Only re-runs when composer.json or composer.lock changes.
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-progress --no-interaction --no-scripts

# Copy the rest of the source (invalidates only this layer on code change)
COPY . .

# Write .env.test from environment variables at container start, then run tests
CMD printenv | grep '^GOBL_TEST_' > .env.test && make test
