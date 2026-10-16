# The demo under Laravel Octane + FrankenPHP (worker mode), PHP 8.3 + Node 22 for the SSR server.
FROM dunglas/frankenphp:1-php8.3
RUN install-php-extensions pcntl zip intl >/dev/null
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
