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
