# syntax=docker/dockerfile:1.7
# =============================================================================
# Redis — Laravel cache / session / queue backend
# =============================================================================
ARG REDIS_VERSION=8

FROM redis:${REDIS_VERSION}-bookworm

LABEL org.opencontainers.image.title="laravel-redis" \
      org.opencontainers.image.description="Redis with AOF persistence and runtime auth" \
      org.opencontainers.image.licenses="MIT"

# The config directory is created explicitly BEFORE the COPY. Using
# `COPY --chmod=0444` to create it implicitly applies 0444 to the new parent
# directory as well — and a directory without its execute bit cannot be
# traversed, so the entrypoint fails with a very confusing
# "cp: cannot stat '/usr/local/etc/redis/redis.conf': Permission denied".
RUN mkdir -p /usr/local/etc/redis

COPY docker/redis/redis.conf                   /usr/local/etc/redis/redis.conf
COPY --chmod=0555 docker/redis/entrypoint.sh   /usr/local/bin/redis-entrypoint
COPY --chmod=0555 docker/redis/healthcheck.sh  /usr/local/bin/redis-healthcheck

# /data holds the AOF directory and RDB snapshots; it is the volume mount point.
RUN set -eux; \
    chmod 0444 /usr/local/etc/redis/redis.conf; \
    mkdir -p /data; \
    chown redis:redis /data

USER redis

VOLUME ["/data"]
EXPOSE 6379

HEALTHCHECK --interval=10s --timeout=4s --start-period=15s --retries=6 \
    CMD ["redis-healthcheck"]

ENTRYPOINT ["redis-entrypoint"]
CMD ["redis-server", "/tmp/redis.conf"]
