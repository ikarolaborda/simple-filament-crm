.DEFAULT_GOAL := help

help:
	@echo "Please choose what you want to do: \n" \
	" make dup: start docker container \n" \
	" make ddw: stop docker container \n" \
	" make drs: restart docker container \n" \
	" make dci: composer install inside container \n" \
	" make dcu: composer update inside container \n" \
	" make mysql: go into the mysql container \n" \
	" make access-php: go into the php container \n"

dup:
	export COMPOSE_FILE=docker-compose.yml; docker-compose up -d

ddw:
	export COMPOSE_FILE=docker-compose.yml; docker-compose down --volumes

drs:
	export COMPOSE_FILE=docker-compose.yml; docker-compose down --volumes && docker-compose up -d

dci:
	docker exec -it php composer install && sudo chown -R $(USER):$(shell id -g) vendor/

dcu:
	docker exec -it php composer update && sudo chown -R $(USER):$(shell id -g) vendor/

mysql:
	docker exec -it database bash

php:
	docker exec -it php bash