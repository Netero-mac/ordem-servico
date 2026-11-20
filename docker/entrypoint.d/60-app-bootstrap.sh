#!/bin/sh
# Roda depois das automações do Laravel (50-laravel-automations), ou seja, depois do migrate.
set -e
php /var/www/html/artisan app:bootstrap --no-interaction
