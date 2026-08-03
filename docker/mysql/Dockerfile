# syntax=docker/dockerfile:1.7
# =============================================================================
# MySQL 8.4 LTS
# =============================================================================
ARG MYSQL_VERSION=8.4

FROM mysql:${MYSQL_VERSION}

LABEL org.opencontainers.image.title="laravel-mysql" \
      org.opencontainers.image.description="MySQL 8.4 LTS tuned for Laravel" \
      org.opencontainers.image.licenses="MIT"

# Configuration must not be group/world-writable or MySQL silently ignores it.
COPY --chmod=0444 docker/mysql/my.cnf /etc/mysql/conf.d/99-laravel.cnf

COPY docker/mysql/init/         /docker-entrypoint-initdb.d/
COPY docker/mysql/healthcheck.sh /usr/local/bin/mysql-healthcheck

# The slow query log needs a writable home outside the data volume, otherwise
# it lands in the datadir and gets shipped around with every backup.
RUN set -eux; \
    chmod +x /usr/local/bin/mysql-healthcheck; \
    mkdir -p /var/log/mysql; \
    chown mysql:mysql /var/log/mysql; \
    chmod 0750 /var/log/mysql

# start-period is generous because the FIRST boot on an empty volume has to
# build the data directory and pre-allocate innodb_redo_log_capacity (512M),
# which takes well over a minute on modest disks. Too short a window and
# `depends_on: service_healthy` gives up before MySQL has finished installing
# itself, failing every dependent container on a first run.
HEALTHCHECK --interval=10s --timeout=5s --start-period=180s --retries=20 \
    CMD ["mysql-healthcheck"]
