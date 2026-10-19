#!/usr/bin/env bash
# The same user journey against one setup, docker-compose.mono.yml (together) or docker-compose.yml
# (apart), through Traefik, the only way in.
set -euo pipefail

compose_file="${1:?usage: tests/e2e/together-or-apart.sh docker-compose.mono.yml|docker-compose.yml}"
cd "$(dirname "$0")/../.."
compose=(docker compose -f "$compose_file")

# In the monorepo, the image takes the packages from their folders rather than from Packagist.
if [[ -d ../packages/core ]]; then
    export DISTRIBUTABLE_PACKAGES=../packages
fi

gateway="http://localhost:${GATEWAY_PORT:-8000}"
iam="$gateway/iam/api/v1"
analytics="$gateway/analytics/api/v1"
notifications="$gateway/notifications/api/v1"

fail() { echo "FAIL: $*" >&2; "${compose[@]}" logs traefik | tail -20 >&2; exit 1; }
status() { curl -s -o /dev/null -w '%{http_code}' -H 'Accept: application/json' "$@"; }
# iam writes the users the gateway reads into gateway-cache; Traefik reads them.
as_iam() { "${compose[@]}" exec -T gateway-cache valkey-cli --user iam --pass "${GATEWAY_CACHE_IAM_PASSWORD:-iam-password}" --no-auth-warning "$@"; }
as_traefik() { "${compose[@]}" exec -T gateway-cache valkey-cli --user traefik --pass "${REDIS_TRAEFIK_PASSWORD:-traefik-password}" --no-auth-warning "$@"; }

trap '"${compose[@]}" down -v >/dev/null 2>&1' EXIT
"${compose[@]}" up -d --build --wait

# Traefik loads its routes once it has compiled the plugin: until then, no answer or a 404; then the
# plugin's 401. Not on /iam/api/v1/register, which allows 6 calls a minute.
for _ in $(seq 60); do
    [[ $(status "$analytics/metrics") == 401 ]] && break
    sleep 1
done

email="ana.$(date +%s)@example.test"
registered=$(curl -sf -X POST "$iam/register" -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -d "{\"name\":\"Ana\",\"email\":\"$email\",\"password\":\"secret-password\"}") || fail "iam refused the registration"
id=$(jq -r '.data.id' <<<"$registered")
token=$(curl -sf -X POST "$iam/login" -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -d "{\"email\":\"$email\",\"password\":\"secret-password\"}" | jq -r '.data.token') || fail "iam refused the login"
echo "registered user $id in iam"

get() { curl -sf -H 'Accept: application/json' -H "Authorization: Bearer $token" "$1"; }

# Events are asynchronous: each check retries for up to 30 seconds.
eventually() {
    local what="$1"; shift
    for _ in $(seq 30); do
        if "$@"; then echo "ok: $what"; return 0; fi
        sleep 1
    done
    fail "$what"
}

eventually "analytics knows the user and asked iam for the grants over RPC: authenticated, not allowed" \
    bash -c "[[ \$(curl -s -o /dev/null -w '%{http_code}' -H 'Accept: application/json' -H 'Authorization: Bearer $token' '$analytics/metrics') == 403 ]]"
eventually "notifications created the welcome notification" \
    bash -c "$(declare -f get); token='$token'; get '$notifications/notifications' | jq -e '.data | length == 1' >/dev/null"

key="gateway:user:$id"

[[ $(as_traefik GET "$key") == *'"id":'"$id"* ]] || fail "iam did not write $key when the user registered"

[[ $(status "$analytics/metrics") == 401 ]] || fail "a request without token went through"
[[ $(status -H "X-Identity: eyJzdWIiOjF9.forged" "$analytics/metrics") == 401 ]] || fail "a forged X-Identity went through"
echo "ok: no token, or a forged X-Identity, is refused"

as_iam DEL "$key" >/dev/null
[[ $(status -H "Authorization: Bearer $token" "$analytics/metrics") == 403 ]] || fail "a valid JWT was refused"
[[ $(as_traefik GET "$key") == *'"id":'"$id"* ]] || fail "iam did not write $key on a miss"
echo "ok: on a miss, Traefik asked iam, which wrote $key as JSON"

[[ $(as_traefik SET "$key" x) == *NOPERM* ]] || fail "Traefik's Redis user can write"
[[ -z $("${compose[@]}" exec -T traefik getent hosts redis postgres) ]] || fail "Traefik resolves Redis or Postgres"
echo "ok: Traefik reads the gateway's users and reaches neither Redis nor Postgres"

[[ $(status -X POST "$gateway/iam/rpc/1/find-user") == 404 ]] || fail "the RPC route is reachable through Traefik"
echo "ok: the RPC routes stay inside"

echo "PASS: $compose_file"
