#!/bin/bash

# Script para verificar el estado del despliegue de Laravel

echo "=== Verificando estado del despliegue Laravel ==="
echo ""

# Verificar si los contenedores están corriendo
echo "1. Estado de los contenedores:"
docker ps | grep laravel-api
echo ""

# Verificar si Nginx puede acceder a los archivos de Laravel
echo "2. Verificando archivos en Nginx:"
docker exec -it laravel-api-nginx ls -la /var/www/html/public/
echo ""

# Verificar si el archivo .env existe
echo "3. Verificando archivo .env:"
docker exec -it laravel-api-app test -f /var/www/html/.env && echo "Archivo .env existe" || echo "¡ALERTA! No existe archivo .env"
echo ""

# Verificar permisos de storage
echo "4. Verificando permisos de storage:"
docker exec -it laravel-api-app ls -la /var/www/html/storage/
echo ""

# Verificar logs de Laravel
echo "5. Últimas 5 líneas de logs de Laravel:"
docker exec -it laravel-api-app tail -n 5 /var/www/html/storage/logs/laravel.log 2>/dev/null || echo "No hay logs todavía"
echo ""

# Probar conexión a la aplicación
echo "6. Probando conexión a la aplicación:"
curl -v http://localhost:8080/ 2>&1 | grep "< HTTP"
echo ""

# Verificar rutas de Laravel
echo "7. Rutas disponibles en Laravel:"
docker exec -it laravel-api-app php /var/www/html/artisan route:list
echo ""

echo "=== Verificación completa ==="
