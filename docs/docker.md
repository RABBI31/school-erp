# Docker Setup

Everything runs in containers: PHP 8.3, Node 20, MySQL 8, Redis, and Chromium with Bangla fonts for PDF generation. You only install Docker on your machine.

## Services

| Service | What it does | Port |
|---|---|---|
| app | Laravel (`artisan serve`) | 8000 |
| vite | Frontend dev server with hot reload | 5173 |
| queue | Queue worker (SMS, bulk PDFs) | — |
| scheduler | Laravel scheduler | — |
| mysql | Database | 3306 |
| redis | Cache and queues | 6379 |

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

REDIS_HOST=redis
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_DOMAIN=null

CHROME_PATH=/usr/bin/chromium
NODE_BINARY=/usr/bin/node
NPM_BINARY=/usr/bin/npm
```

4. Start and install:

```bash
docker compose up -d --build     # or: make up
docker compose exec app composer install
docker compose exec app npm install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

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
- **Permission denied on `storage/`:** rebuild passing your user id: `UID=$(id -u) GID=$(id -g) docker compose up -d --build` (Linux/macOS only).
- **Frontend changes don't appear:** the `vite` container isn't running, or `usePolling` is missing in the Vite config.
- **Slow on Windows:** keep the project inside the WSL filesystem (`\\wsl$\Ubuntu\home\...`), not in `C:\Users\...`.

## Later, for production

This setup is for development. Before deploying to a real school server, swap `artisan serve` for Nginx + PHP-FPM, build assets in a multi-stage Dockerfile, run `php artisan config:cache route:cache view:cache`, and set `APP_ENV=production`, `APP_DEBUG=false`. Ask Claude Code for a production compose file when you get there.
