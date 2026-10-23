# PHP 8.4 with GD (png, jpeg, webp) and Composer: the harness installs this package and every
# omnimap/* provider at start.
FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd \
    && rm -rf /var/lib/apt/lists/* \
    && git config --system --add safe.directory '*'
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1 COMPOSER_CACHE_DIR=/harness/.cache
WORKDIR /harness
ENTRYPOINT ["/omnimap/core/docker/entrypoint.sh"]
CMD ["test"]
