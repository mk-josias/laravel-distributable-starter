#!/usr/bin/env bash
set -e

# The container that issues tokens (iam) writes its key pair once, before the config is cached: it reads them.
if [ "${WITH_JWT_KEYS:-false}" = "true" ]; then
    php artisan auth:jwt-keys
fi

if [ "${APP_ENV:-production}" != "local" ]; then
    php artisan optimize
fi

# Only the http role migrates: one container per module set does it, the others wait on its healthcheck.
if [ "${WITH_HTTP:-true}" = "true" ]; then
    php artisan migrate --module=* --database=owner --force
fi

# Only the container that runs iam owns the permissions.
if [ "${SYNC_PERMISSIONS:-false}" = "true" ]; then
    php artisan iam:sync-permissions --prune
fi

# supervisord aborts on any %(ENV_…)s it can't resolve: every switch it reads gets a value here.
: "${WITH_HTTP:=true}" "${WITH_RPC:=false}" "${WITH_WORKER:=false}" "${WITH_CONSUMERS:=false}" "${WITH_SCHEDULER:=false}" "${WITH_REVERB:=false}"
: "${OCTANE_WORKERS:=auto}"
export WITH_HTTP WITH_RPC WITH_WORKER WITH_CONSUMERS WITH_SCHEDULER WITH_REVERB OCTANE_WORKERS

exec supervisord -c /app/docker/supervisord.conf
