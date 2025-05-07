# Sistema de Logging Centralizado

## Descripción

Task Master API utiliza un sistema de logging centralizado basado en la stack ELK (Elasticsearch, Logstash, Kibana) para monitorear la aplicación en tiempo real.

## Configuración

La conexión con Logstash está configurada a través de un helper personalizado que garantiza la entrega confiable de los logs.

## Uso básico

Para enviar logs al sistema centralizado, utiliza el `LogHelper`:

```php
use App\Helpers\LogHelper;

// Log informativo básico
LogHelper::toLogstash('Usuario registrado correctamente', [
    'user_id' => $user->id,
    'email' => $user->email
]);

// Log de error con nivel personalizado
LogHelper::toLogstash('Error al procesar pago', [
    'user_id' => $user->id,
    'amount' => $amount,
    'transaction_id' => $transactionId
], 'error');
```

## Niveles de log

Los niveles de log disponibles son:
- `debug`: Información detallada para desarrollo
- `info`: Información general (valor por defecto)
- `warning`: Advertencias que no interrumpen el flujo
- `error`: Errores que afectan operaciones específicas
- `critical`: Errores críticos que requieren atención inmediata

## Estructura de contexto recomendada

Para mantener la consistencia, se recomienda incluir en el contexto:

- **Identificadores**: IDs de usuarios, transacciones, etc.
- **Timestamps**: Para eventos con tiempo específico
- **Datos relevantes**: Valores importantes para el análisis
- **Origen**: Componente o módulo que genera el log

## Pruebas

Puedes probar la conexión con Logstash usando el comando Artisan:

```bash
php artisan log:test "Mensaje de prueba"
```

## Visualización

Los logs están disponibles para visualización en:
- **Kibana**: http://localhost:5601
- Crea un índice pattern `logstash-*` para ver todos los logs

## Notas importantes

- Evita incluir información sensible como contraseñas o tokens en los logs
- Los logs de errores también se almacenan localmente en `storage/logs/laravel.log`
- Se recomienda usar el monitoreo centralizado para detección proactiva de problemas 

# Configuración del Sistema de Logging

Este documento describe cómo configurar correctamente el sistema de logging en la aplicación Task Master.

## Problema Común: Fallo de Conexión a Logstash

Si encuentras el error:

```
Failed connecting to tcp://logstash-dev:5000 (0: php_network_getaddresses: getaddrinfo for logstash-dev failed: Host desconocido.)
```

Esto significa que la aplicación está intentando conectarse a un servidor Logstash que no está disponible o no es accesible.

## Solución

### 1. Configuración del archivo .env

Añade las siguientes variables a tu archivo `.env`:

```
# Usar servidor Logstash local o desactivar Logstash
LOGSTASH_CONNECTION=tcp://localhost:5000
LOGSTASH_TIMEOUT=0.5
```

Si no tienes un servidor Logstash en ejecución localmente y estás en entorno de desarrollo, puedes simplemente evitar usar el canal Logstash modificando la pila de logging:

```
LOG_CHANNEL=stack
LOG_STACK=single,daily
```

### 2. Deshabilitar Logstash en entorno local

Si no necesitas enviar logs a Logstash en tu entorno local, puedes agregar esta configuración a tu archivo `.env`:

```
LOG_CHANNEL=stack
LOG_STACK=single
```

### 3. Configuración para producción

En entornos de producción, asegúrate de que el servicio Logstash esté correctamente configurado y accesible desde tu aplicación:

```
LOGSTASH_CONNECTION=tcp://tu-servidor-logstash:5000
LOGSTASH_TIMEOUT=2.0
LOG_CHANNEL=stack
LOG_STACK=single,daily,logstash
```

## Estructura del Logging

La aplicación utiliza un sistema de logging de múltiples capas:

1. **LoggingService**: Implementa la interfaz `LoggingServiceInterface` y proporciona un método `log()` que maneja la lógica de decisión sobre dónde enviar los logs según el entorno.

2. **LogHelper**: Proporciona un método estático `toLogstash()` que intenta conectarse directamente a Logstash. Si la conexión falla, registra el error y vuelve a usar el logger estándar de Laravel.

3. **Canales de Log**: La aplicación está configurada para usar varios canales de log:
   - `single`: Escribe en un solo archivo
   - `daily`: Escribe en archivos diarios
   - `logstash`: Envía logs a un servidor Logstash

## Prueba de la Configuración

Para probar que tu sistema de logging está funcionando correctamente:

```php
// En cualquier controlador o servicio
$this->logger->log('Mensaje de prueba', ['datos' => 'adicionales']);
```

Si no ves errores en la consola y los logs se escriben correctamente en `storage/logs/laravel.log`, la configuración es correcta. 
