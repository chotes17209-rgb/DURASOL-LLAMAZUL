#!/bin/sh
# Arranque del contenedor: prepara Laravel, migra la base de datos y levanta PHP-FPM + Nginx.
set -e
cd /var/www/html

# Render genera un secreto aleatorio; Laravel necesita una clave "base64:" de 32 bytes.
if [ -n "$APP_KEY" ] && [ "${APP_KEY#base64:}" = "$APP_KEY" ]; then
    APP_KEY="base64:$(php -r 'echo base64_encode(hash("sha256", getenv("APP_KEY"), true));')"
    export APP_KEY
fi

echo "Esperando la base de datos..."
for i in $(seq 1 30); do
    php artisan tinker --execute="DB::connection()->getPdo();" > /dev/null 2>&1 && break
    sleep 2
done

php artisan migrate --force

# Solo la primera vez: usuarios, catálogos y datos del Excel.
if [ "$(php artisan tinker --execute='echo App\Models\User::count();' 2>/dev/null | tail -n1)" = "0" ]; then
    echo "Base de datos vacía: cargando datos iniciales..."
    php artisan db:seed --force
fi

php artisan storage:link > /dev/null 2>&1 || true
php artisan optimize

php-fpm -D
exec nginx -g 'daemon off;'
