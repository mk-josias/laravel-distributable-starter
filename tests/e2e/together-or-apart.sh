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
# Laravel's cache lives in the database of its `cache` Redis connection (REDIS_CACHE_DB, 1); the stream in 0.
redis() { "${compose[@]}" exec -T redis valkey-cli -n 1 "$@"; }
as_traefik() { "${compose[@]}" exec -T redis valkey-cli --user traefik --pass "${REDIS_TRAEFIK_PASSWORD:-traefik-password}" --no-auth-warning "$@"; }

trap '"${compose[@]}" down -v >/dev/null 2>&1' EXIT
"${compose[@]}" up -d --build --wait

# Traefik loads its routes once it has compiled the plugin: until then, no answer or a 404; then the
# plugin's 401. Not on /iam/api/v1/register, which allows 6 calls a minute.
for _ in $(seq 60); do
    [[ $(status "$analytics/users/1") == 401 ]] && break
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

eventually "analytics recorded the signup, with the user copied locally" \
    bash -c "$(declare -f get); token='$token'; get '$analytics/signups?user_id=$id' | jq -e '.data[0].user.name == \"Ana\"' >/dev/null"
eventually "analytics reads the user from iam over RPC" \
    bash -c "$(declare -f get); token='$token'; get '$analytics/users/$id' | jq -e '.data.id == $id' >/dev/null"
eventually "notifications created the welcome notification" \
    bash -c "$(declare -f get); token='$token'; get '$notifications/notifications' | jq -e '.data | length == 1' >/dev/null"

key="database:cache:iam:user:$id"

[[ $(status "$analytics/users/$id") == 401 ]] || fail "a request without token went through"
[[ $(status -H "X-Identity: eyJzdWIiOjF9.forged" "$analytics/users/$id") == 401 ]] || fail "a forged X-Identity went through"
echo "ok: no token, or a forged X-Identity, is refused"

redis DEL "$key" >/dev/null
[[ $(status -H "Authorization: Bearer $token" "$analytics/users/$id") == 200 ]] || fail "a valid JWT was refused"
[[ $(redis GET "$key") == *'"id":'"$id"* ]] || fail "iam did not fill $key"
echo "ok: on a cache miss, Traefik asked iam, which filled $key as JSON"

[[ $(as_traefik SET "$key" x) == *NOPERM* ]] || fail "Traefik's Redis user can write"
[[ $(as_traefik XRANGE database:microservices:events - +) == *NOPERM* ]] || fail "Traefik's Redis user can read the stream"
echo "ok: Traefik's Redis user only reads the cached users"

[[ $(status -X POST "$gateway/iam/rpc/findUser") == 404 ]] || fail "the RPC route is reachable through Traefik"
echo "ok: the RPC routes stay inside"

echo "PASS: $compose_file"
