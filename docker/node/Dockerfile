# syntax=docker/dockerfile:1.7
# =============================================================================
# Node 22 LTS — frontend toolchain (Vite dev server, asset builds)
#
# This is a *tooling* container, not a runtime service: in production the
# compiled assets are baked into the PHP/nginx images by the `assets` stage of
# docker/php/Dockerfile and this container is not deployed at all.
# =============================================================================
ARG NODE_VERSION=22

FROM node:${NODE_VERSION}-bookworm-slim

LABEL org.opencontainers.image.title="laravel-node" \
      org.opencontainers.image.description="Node 22 LTS toolchain with npm/pnpm/yarn via Corepack" \
      org.opencontainers.image.licenses="MIT"

ARG UID=1000
ARG GID=1000

ENV NODE_ENV=development \
    npm_config_update_notifier=false \
    npm_config_fund=false \
    npm_config_audit=false \
    COREPACK_ENABLE_DOWNLOAD_PROMPT=0

WORKDIR /var/www/html

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        python3 \
        make \
        g++; \
    rm -rf /var/lib/apt/lists/*; \
    \
    # Corepack ships with Node 22 and provides pnpm and yarn on demand,
    # pinned by the project's own packageManager field.
    corepack enable

COPY docker/node/.npmrc /home/node/.npmrc

# The `node` user already exists as uid 1000 in this image; remap only if the
# host uses different ids, so bind-mounted files stay writable either way.
RUN set -eux; \
    if [ "${UID}" != "1000" ] || [ "${GID}" != "1000" ]; then \
        groupmod --gid "${GID}" node; \
        usermod --uid "${UID}" --gid "${GID}" node; \
    fi; \
    # node_modules and .npm MUST exist here even though they are empty. Each is
    # a named volume at runtime, and Docker initialises an empty named volume
    # from the image path it covers — inheriting that path's ownership. With no
    # such path in the image, Docker creates the volume root-owned and npm then
    # fails with "The operation was rejected by your operating system".
    mkdir -p /home/node/.npm /home/node/.cache \
             /var/www/html /var/www/html/node_modules; \
    chown -R "${UID}:${GID}" /home/node /var/www/html

USER ${UID}:${GID}

EXPOSE 5173

# Vite must bind 0.0.0.0 to be reachable from outside the container; binding
# 127.0.0.1 (its default) would make the dev server invisible on the host.
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
