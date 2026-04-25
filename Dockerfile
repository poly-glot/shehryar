# Multi-stage Dockerfile for shehryar — shared base used by both the
# devcontainer (target: dev) and the Cloud Run image (target: prod).
#
#   base — PHP 8.3 + pdo_mysql, the runtime contract for both stages.
#   dev  — adds developer tooling and the vscode user; runs sleep infinity
#          so the devcontainer can exec into it. Devcontainer features
#          (claude-code, github-cli, common-utils) layer on top at start.
#   prod — adds nginx + supervisord, copies the application code, and
#          runs supervisord as PID 1.

# ─────────────────────────────────────────────────────────────
FROM php:8.3-fpm-bookworm AS base

RUN apt-get update -qq \
 && apt-get install -y -qq --no-install-recommends \
        ca-certificates \
 && docker-php-ext-install pdo_mysql \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# ─────────────────────────────────────────────────────────────
FROM base AS dev

RUN apt-get update -qq \
 && apt-get install -y -qq --no-install-recommends \
        default-mysql-client \
        git curl unzip sudo zsh \
 && apt-get clean && rm -rf /var/lib/apt/lists/* \
 && groupadd --gid 1000 vscode \
 && useradd  --uid 1000 --gid vscode --shell /bin/zsh --create-home vscode \
 && echo 'vscode ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/vscode

WORKDIR /workspace
USER vscode
CMD ["sleep", "infinity"]

# ─────────────────────────────────────────────────────────────
FROM base AS prod

RUN apt-get update -qq \
 && apt-get install -y -qq --no-install-recommends \
        nginx supervisor \
 && apt-get clean && rm -rf /var/lib/apt/lists/* \
 && mkdir -p /run/nginx /var/log/supervisor

# Layout mirrors the repo so bin/migrate.php's relative paths work the
# same way in dev (/workspace) and prod (/var/www).
COPY src/             /var/www/src/
COPY db/              /var/www/db/
COPY bin/migrate.php  /var/www/bin/migrate.php

COPY docker/nginx.conf       /etc/nginx/nginx.conf
COPY docker/php-fpm.conf     /usr/local/etc/php-fpm.d/zz-cloudrun.conf
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf

ENV PORT=8080
EXPOSE 8080
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
