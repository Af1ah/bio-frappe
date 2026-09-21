# Production Docker deployment

This configuration runs an immutable production image: PHP 8.3-FPM is served by Caddy, PostgreSQL is private to the Docker network, and queue processing plus scheduling each run in a dedicated container. It does not bind-mount source code or publish PostgreSQL to the host.

## Prerequisites

- Docker Engine and the Docker Compose plugin.
- A DNS A/AAAA record for the production hostname pointing to the server.
- TCP ports 80 and 443 available on the server firewall. Caddy uses them to obtain and renew TLS certificates.

## Configure secrets

Copy the example file on the deployment server:

```sh
cp .env.docker.example .env.docker
```

Set a real hostname for `APP_URL`, `CENTRAL_DOMAIN`, and `CADDY_SITE_ADDRESS`. Set long, unique values for `DB_PASSWORD`, the Frappe credentials, and `APP_KEY`.

Generate an application key without writing it to Git:

```sh
docker compose --env-file .env.docker run --rm app php artisan key:generate --show
```

Copy the returned `base64:...` value into `APP_KEY` in `.env.docker`.

## First deployment

```sh
docker compose --env-file .env.docker build --pull
docker compose --env-file .env.docker up -d pgsql
docker compose --env-file .env.docker run --rm app php artisan migrate --force
docker compose --env-file .env.docker run --rm app php artisan tenants:migrate --force
docker compose --env-file .env.docker run --rm app php artisan optimize
docker compose --env-file .env.docker up -d
```

Create the initial master user only after the containers are healthy:

```sh
docker compose --env-file .env.docker exec app php artisan make:filament-user
```

## Validation and operations

```sh
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f caddy app worker scheduler
docker compose --env-file .env.docker exec app php artisan migrate:status
```

For an application update, pull the new source, rebuild, run migrations, optimize, and recreate the services:

```sh
docker compose --env-file .env.docker build --pull
docker compose --env-file .env.docker run --rm app php artisan migrate --force
docker compose --env-file .env.docker run --rm app php artisan tenants:migrate --force
docker compose --env-file .env.docker run --rm app php artisan optimize
docker compose --env-file .env.docker up -d --force-recreate
```

Do not run `docker compose down -v` in production: it deletes PostgreSQL, uploads, and Caddy certificate volumes.
