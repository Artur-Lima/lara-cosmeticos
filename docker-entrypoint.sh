#!/bin/sh
set -e

# Volume de storage chega vazio no primeiro deploy: garante a estrutura.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public
chown -R www-data:www-data storage 2>/dev/null || true

if [ "${APP_ENV}" = "production" ]; then
    # Config/rotas/views em cache: lidos do ambiente do serviço a cada boot.
    php artisan optimize --quiet
else
    php artisan optimize:clear --quiet 2>/dev/null || true
fi

# Só o serviço "app" roda migrations (RUN_MIGRATIONS=true); queue e
# scheduler sobem com a mesma imagem e não devem concorrer pelo schema.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

php artisan storage:link --quiet 2>/dev/null || true

exec "$@"
