# Usage: make up, make sh, make test ...
up:        ## start everything
	docker compose up -d --build
down:      ## stop everything
	docker compose down
logs:
	docker compose logs -f app
sh:        ## shell inside the app container
	docker compose exec app bash
install:
	docker compose exec app composer install
	docker compose exec app npm install
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate --seed
fresh:     ## reset database with demo data
	docker compose exec app php artisan migrate:fresh --seed
test:
	docker compose exec app php artisan test
build:
	docker compose exec app npm run build
