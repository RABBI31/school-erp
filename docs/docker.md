# Docker Setup

Everything runs in containers: PHP 8.4, Node 20, MySQL 8, Redis, and Chromium with Bangla fonts for PDF generation. You only install Docker on your machine.

## Services

| Service | What it does | Port |
|---|---|---|
| app | Laravel (`artisan serve`) | 8000 |
| vite | Frontend dev server with hot reload | 5173 |
| queue | Queue worker (SMS, bulk PDFs) — `database` driver | — |
| scheduler | Laravel scheduler | — |
| mysql | Database | 3306 |
| redis | Not used yet (queues, cache and sessions are on `database` for the demo) | 6379 |

## First run

1. Install **Docker Desktop** (docker.com). On Windows, accept the WSL 2 setup it offers.
2. Create the Laravel project on your machine first (needs the Laravel installer once), or ask Claude Code to create it inside the container. Then copy this kit into the project root.
3. Create `.env` from `.env.example` and set:

```dotenv
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Dhaka
APP_LOCALE=bn
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=school_erp
DB_USERNAME=school
DB_PASSWORD=secret

# Queues, cache and sessions use the database for the demo (see CLAUDE.md).
# The image has no phpredis extension, so don't set REDIS_CLIENT=phpredis.
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_DOMAIN=null
REDIS_HOST=redis

CHROME_PATH=/usr/bin/chromium
NODE_BINARY=/usr/bin/node
NPM_BINARY=/usr/bin/npm

# macOS/Linux: build the image with your own user id so files stay editable
HOST_UID=501        # output of `id -u`
HOST_GID=1000
```

4. Start and install:

```bash
docker compose up -d --build     # or: make up
docker compose exec app composer install
docker compose exec app npm install
docker compose exec app php artisan key:generate
docker compose exec app php artisan storage:link   # uploaded school logos
docker compose exec app php artisan migrate --seed
```

The MySQL container also creates `school_erp_test` (from `docker/mysql/init.sql`) the first time its volume is created. The test suite runs against it — `phpunit.xml` points there — never SQLite.

5. Open **http://localhost:8000**. Tenant subdomains work as `http://demo.localhost:8000` in Chrome and Firefox without editing any hosts file.

## Daily commands

```bash
make up                  # start
make down                # stop
make sh                  # shell inside the app container
make fresh               # reset database with demo data
make test                # run tests
docker compose logs -f app
```

Every `php artisan` and `npm` command must run **inside** the container:

```bash
docker compose exec app php artisan make:model Student -m
docker compose exec app npm install some-package
```

## Vite config

For hot reload to work from inside a container, `vite.config.ts` needs:

```ts
server: {
  host: '0.0.0.0',
  port: 5173,
  hmr: { host: 'localhost' },
  watch: { usePolling: true },   // needed on Windows/macOS
}
```

## PDFs with Browsershot

Chromium and Bengali fonts are already installed in the image. Configure Browsershot once, in a service class:

```php
Browsershot::html($html)
    ->setNodeBinary(config('services.node.binary', '/usr/bin/node'))
    ->setNpmBinary(config('services.node.npm', '/usr/bin/npm'))
    ->setChromePath(config('services.chrome.path', '/usr/bin/chromium'))
    ->noSandbox()
    ->format('A4')
    ->savePdf($path);
```

`noSandbox()` is required inside Docker. If a PDF shows boxes instead of Bangla letters, the HTML is using a font that has no Bengali glyphs — set `font-family: 'Noto Sans Bengali', sans-serif` in the PDF stylesheet.

## Common problems

- **Port already in use:** something else uses 8000 or 3306. Change the left side of the port mapping in `docker-compose.yml`, e.g. `"8001:8000"`.
- **`SQLSTATE[HY000] [2002] Connection refused`:** MySQL wasn't ready yet, or `DB_HOST` is still `127.0.0.1`. It must be `mysql`.
- **Permission denied on `storage/`:** rebuild with your user id: set `HOST_UID` / `HOST_GID` in `.env`, or run `HOST_UID=$(id -u) HOST_GID=$(id -g) docker compose up -d --build` (Linux/macOS only). Don't use `UID=`/`GID=` — in zsh (the macOS default shell) those are read-only shell variables and the command fails with `failed to change group ID: operation not permitted`.
- **Tests fail with `Unknown database 'school_erp_test'`:** the MySQL volume was created before `docker/mysql/init.sql` existed. Run the SQL in that file once: `docker compose exec -T mysql mysql -uroot -psecret < docker/mysql/init.sql`.
- **Frontend changes don't appear:** the `vite` container isn't running, or `usePolling` is missing in the Vite config.
- **`Vite manifest not found` in the browser:** `public/hot` is missing — this can happen after Vite restarts itself because `.env` or `vite.config.ts` changed. Run `docker compose restart vite`.
- **Slow on Windows:** keep the project inside the WSL filesystem (`\\wsl$\Ubuntu\home\...`), not in `C:\Users\...`.

## Later, for production

This setup is for development. Before deploying to a real school server, swap `artisan serve` for Nginx + PHP-FPM, build assets in a multi-stage Dockerfile, run `php artisan config:cache route:cache view:cache`, and set `APP_ENV=production`, `APP_DEBUG=false`. Ask Claude Code for a production compose file when you get there.
