.PHONY: demo

demo:
	docker compose run --rm php php artisan migrate:refresh --seed
