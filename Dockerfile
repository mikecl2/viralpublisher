FROM php:8.3-apache

# --- System packages + PHP extensions ------------------------------------
# pdo_sqlite / sqlite3 : the entire app's storage layer
# mbstring             : required by tools/hook-generator/generate.php and
#                         tools/score-checker/generate.php for mb_strlen/mb_substr
#                         — NOT enabled by default in this base image. Also
#                         needs libonig-dev + pkg-config to actually COMPILE
#                         (the oniguruma regex library it's built against) —
#                         omitting these two causes a build-time failure, not
#                         a runtime one, which is what happened on first deploy.
# curl                  : required by api/ai-proxy.php (OpenRouter calls) and
#                         admin/lib/openrouter-models.php (model list fetch)
#                         — also not enabled by default in this base image.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        libcurl4-openssl-dev \
        libonig-dev \
        pkg-config \
        curl \
    && docker-php-ext-install pdo_sqlite mbstring curl \
    && apt-get purge -y --auto-remove libsqlite3-dev libcurl4-openssl-dev libonig-dev pkg-config \
    && rm -rf /var/lib/apt/lists/*

# --- Apache configuration --------------------------------------------------
RUN a2enmod headers

COPY docker/apache-security.conf /etc/apache2/conf-available/viralpublisher-security.conf
RUN a2enconf viralpublisher-security

# --- PHP configuration ------------------------------------------------------
COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-viralpublisher.ini

# --- Application code --------------------------------------------------------
WORKDIR /var/www/html
COPY . .

# The SQLite DB lives at data/viralpublisher.sqlite (see lib/db.php's
# VP_DB_PATH default). This directory must be a mounted volume in Coolify —
# see DEPLOYMENT.md — or every redeploy wipes all leads and generated content.
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html \
    && chmod 775 /var/www/html/data

VOLUME /var/www/html/data

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl -f http://localhost/ || exit 1
