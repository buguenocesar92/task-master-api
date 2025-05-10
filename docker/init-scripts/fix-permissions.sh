#!/bin/bash

# Fijar permisos para los directorios de almacenamiento de Laravel
echo "Corrigiendo permisos en directorios de almacenamiento de Laravel..."

# Crear directorios si no existen
mkdir -p /var/www/html/storage/framework/{sessions,views,cache}
mkdir -p /var/www/html/storage/logs

# Cambiar propietario a www-data (usuario del servidor web)
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

# Permisos de escritura para todos los directorios
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

echo "Permisos corregidos correctamente." 