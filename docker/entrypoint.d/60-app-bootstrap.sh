#!/bin/sh
# Roda depois das automações do Laravel (50-laravel-automations), ou seja, depois do migrate.
# Não é fatal: se falhar, o erro fica no log e o site sobe mesmo assim.
if ! php /var/www/html/artisan app:bootstrap --no-interaction; then
    echo "⚠️  app:bootstrap falhou (veja o erro acima). O site vai subir sem os dados iniciais." >&2
fi
