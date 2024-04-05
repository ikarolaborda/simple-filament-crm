#!/bin/sh

php artisan optimize:clear

exec docker-php-entrypoint php-fpm