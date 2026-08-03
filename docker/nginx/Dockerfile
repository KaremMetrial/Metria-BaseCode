# syntax=docker/dockerfile:1.7
# =============================================================================
# Nginx — Laravel edge
#
# Built on nginx-unprivileged: unlike the stock nginx image (root master +
# nginx workers), *every* process here runs as uid 101. That means the
# container needs no capabilities at all and works with a read-only rootfs.
#
# Consequence: it listens on 8080, not 80. Compose maps the public port.
# =============================================================================
ARG NGINX_VERSION=1.29

FROM nginxinc/nginx-unprivileged:${NGINX_VERSION}-bookworm AS base

LABEL org.opencontainers.image.title="laravel-nginx" \
      org.opencontainers.image.description="Hardened Nginx edge for Laravel PHP-FPM" \
      org.opencontainers.image.licenses="MIT"

# Defaults for the envsubst-rendered server block. Declared here so the image
# is valid standalone; compose overrides them per environment. If any of these
# were unset, envsubst would leave a literal `${...}` in the config.
ENV NGINX_SERVER_NAME=_ \
    NGINX_FPM_HOST=app \
    NGINX_FPM_PORT=9000 \
    NGINX_ROOT=/var/www/html/public

USER root

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends curl; \
    rm -rf /var/lib/apt/lists/*; \
    rm -f /etc/nginx/conf.d/default.conf

COPY docker/nginx/nginx.conf   /etc/nginx/nginx.conf
COPY docker/nginx/snippets/    /etc/nginx/snippets/
COPY docker/nginx/templates/   /etc/nginx/templates/
# The internal-only template is copied explicitly by the `internal` target.
# Leaving it here would make nginx's entrypoint render a second public server.
RUN rm -f /etc/nginx/templates/internal-realtime.conf.template

# nginx creates its own *_temp_path directories at startup (see nginx.conf),
# so nothing needs pre-creating under /tmp — it is a tmpfs at runtime anyway.
# /etc/nginx must be writable because the entrypoint renders templates into it.
RUN chown -R 101:101 /etc/nginx

USER 101

EXPOSE 8080

HEALTHCHECK --interval=15s --timeout=5s --start-period=15s --retries=5 \
    CMD curl --fail --silent --show-error --max-time 4 http://127.0.0.1:8080/healthz || exit 1

STOPSIGNAL SIGQUIT

# =============================================================================
# PRODUCTION
#
# Bakes the *compiled* public/ tree into the edge image, so nginx serves static
# files straight from its own layer — no shared volume, no dependency on the
# PHP container being up, and no chance of the two drifting apart.
#
# public/ is taken from the finished app image rather than from the build
# context, because the build context only has the SOURCE public/ — the Vite
# output is produced by the `assets` stage of docker/php/Dockerfile.
#
# Compose wires this up automatically (build.additional_contexts). For a raw
# docker build, supply it yourself:
#   docker build --target production \
#     --build-context app_image=docker-image://laravel/app:latest \
#     -f docker/nginx/Dockerfile .
# =============================================================================
FROM base AS production

USER root
COPY --from=app_image --chown=101:101 /var/www/html/public /var/www/html/public
USER 101

# Backend-only Laravel HTTP upstream used solely by the realtime service.
# It is attached only to Compose's internal backend network and has no ports.
FROM base AS internal

COPY docker/nginx/templates/internal-realtime.conf.template /etc/nginx/templates/default.conf.template
RUN rm -f /etc/nginx/templates/internal-realtime.conf.template
