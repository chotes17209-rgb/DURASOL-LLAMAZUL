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

# Si la base se creó con una versión anterior (sin el parte diario), se reconstruye:
# los datos son de demostración y se vuelven a importar del Excel.
ESQUEMA=$(php artisan tinker --execute='echo Schema::hasTable("migrations") ? (Schema::hasTable("partes") && Schema::hasColumn("precios_compra", "validado") ? "ok" : "antiguo") : "vacio";' 2>/dev/null | tail -n1)
if [ "$ESQUEMA" = "antiguo" ]; then
    echo "Esquema de una versión anterior: reconstruyendo la base de datos..."
    php artisan migrate:fresh --force
else
    php artisan migrate --force
fi

php artisan storage:link > /dev/null 2>&1 || true
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache

php-fpm -D

# Solo la primera vez: usuarios, catálogos y datos del Excel. Corre en segundo plano
# para que el servidor responda de inmediato (la importación toma uno o dos minutos).
if [ "$(php artisan tinker --execute='echo App\Models\User::count();' 2>/dev/null | tail -n1)" = "0" ]; then
    echo "Base de datos vacía: cargando datos iniciales en segundo plano..."
    (php artisan db:seed --force && echo "Datos iniciales cargados.") &
fi

exec nginx -g 'daemon off;'
