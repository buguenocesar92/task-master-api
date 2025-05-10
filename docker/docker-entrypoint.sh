#!/bin/bash
set -e

# Ejecutar todos los scripts en el directorio docker-entrypoint-initscript.d
if [ -d /docker-entrypoint-initscript.d ]; then
  echo "Ejecutando scripts de inicialización..."
  for script in /docker-entrypoint-initscript.d/*; do
    if [ -f "$script" ] && [ -x "$script" ]; then
      echo "Ejecutando $script"
      "$script"
    fi
  done
fi

# Asegurarnos de que los directorios de almacenamiento y caché tengan permisos correctos
echo "Verificando permisos de almacenamiento..."
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Ejecutar el comando proporcionado
exec "$@" 