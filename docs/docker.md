# Docker

The starter ships both setups, on Postgres (one database per module) and Redis (the event
stream), served by Octane with Swoole:

```bash
# every module in one container
docker compose -f docker-compose.mono.yml up -d
# one container per module
docker compose -f docker-compose.mesh.yml up -d
```

In both, Traefik is the only way into the API, on `:8000` (`GATEWAY_PORT`); the modules' ports stay
private, except Reverb's 8080 (the live push). Postgres and the modules' Redis are on a network
Traefik doesn't join; it reads the users from `gateway-cache`, a Redis of its own.
Send the token iam issued: Traefik turns it into the `X-Identity` the modules read. The container
that runs iam writes its JWT key pair to the `jwt-keys` volume at its first start
(`WITH_JWT_KEYS`), and Traefik reads the public key there.

In the second setup, each image is built with `--build-arg RUN_MODULES=<module>`, so it holds only
its module: the Dockerfile runs `modules:purge` before `composer dump-autoload`. A container
migrates only the database of the module it runs, so two containers never migrate the same one.

- **`docker/Dockerfile`:** Installs the dependencies, purges the modules the image doesn't run and
  the packages only they required, then builds the PHP and Swoole runtime.
- **`docker/entrypoint.sh`:** Writes iam's JWT keys (`WITH_JWT_KEYS`), runs `optimize`, migrates (on
  the `http` role only), then starts supervisord.
- **`gateway/`:** Traefik in front of the modules, and its auth plugin: see
  [`gateway/README.md`](https://github.com/mk-josias/laravel-distributable-starter/blob/main/gateway/README.md).
- **`docker/supervisord.conf`:** The roles a container can take, each switched on by a variable.
- **`docker/postgres/`:** A Postgres image that creates the databases of `MODULE_DATABASES`, owned
  by `distributable`, written by `distributable_app`.

- **`http` (Octane)**
  - Variable: `WITH_HTTP`
  - Default: `true`
  - Run at most: as many as you need
- **`rpc` (`rpc:serve`, port `DISTRIBUTABLE_RPC_PORT`)**
  - Variable: `WITH_RPC`
  - Default: `false`
  - Run at most: as many as you need: the calls between modules, kept off the API's workers
- **`worker` (`queue:work`)**
  - Variable: `WITH_WORKER`
  - Default: `false`
  - Run at most: as many as you need
- **`reverb` (WebSocket, port 8080)**
  - Variable: `WITH_REVERB`
  - Default: `false`
  - Run at most: one per container that pushes: it pushes to its own, on localhost
- **`scheduler` (supercronic)**
  - Variable: `WITH_SCHEDULER`
  - Default: `false`
  - Run at most: one per module set: two would run each task twice
- **`consumers` (`stream:consume`)**
  - Variable: `WITH_CONSUMERS`
  - Default: `false`
  - Run at most: one per module set: it serves every module of `RUN_MODULES`, each with its own
    cursor, and two would break the order a module reads in

## Go to production

The values in the compose files are for development: the key that signs the tokens and the
passwords are public. For production, add the override that requires every secret:

```bash
cp .env.production.example .env.production   # then fill it in, with the command each line names
docker compose --env-file .env.production -f docker-compose.mesh.yml -f docker-compose.mesh.prod.yml up -d
```

Compose refuses to start while one of the nine secrets is unset. For the one-container setup, use
`docker-compose.mono.yml` and `docker-compose.mono.prod.yml`.

`DISTRIBUTABLE_STREAM_GUARD` is `false` in both files: a redelivered event is handled twice. The
starter's handlers tolerate that. Remove the line (the guard then turns itself on with Redis) once
you add one that doesn't.
