# Análisis Estático de Código

Este proyecto implementa un conjunto completo de herramientas de análisis estático de código para garantizar la calidad y los estándares de codificación.

## Herramientas Implementadas

### 1. Laravel Pint

[Laravel Pint](https://laravel.com/docs/11.x/pint) es un formateador de código PHP opinativo que ayuda a seguir las convenciones de estilo de Laravel.

**Uso:**
```bash
# Verificar estilo sin modificar archivos
composer lint

# Arreglar automáticamente problemas de estilo
composer lint:fix
```

### 2. PHPStan

[PHPStan](https://phpstan.org/) es una herramienta de análisis estático que encuentra errores en el código sin ejecutarlo.

**Uso:**
```bash
# Ejecutar análisis de código
composer analyse
```

**Niveles de Reglas:**
- Actualmente configurado en nivel 5 (de 0 a 9, siendo 9 el más estricto)
- Excluye directorios de terceros como `vendor`

### 3. PHP_CodeSniffer

[PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) valida que el código sigue el estándar PSR-12.

**Uso:**
```bash
# Verificar cumplimiento del estándar
composer sniff

# Arreglar automáticamente problemas detectados
composer sniff:fix
```

## Integración con CI/CD

El análisis estático de código está integrado en el pipeline de CI/CD:

1. Se ejecuta automáticamente en cada push
2. Falla la construcción si se detectan problemas
3. Proporciona retroalimentación inmediata

## Configuración

Las configuraciones personalizadas se encuentran en:

- `phpstan.neon` - Configuración de PHPStan
- `phpcs.xml` - Reglas para PHP_CodeSniffer
- `pint.json` - Configuración de Laravel Pint

## Recomendaciones para Desarrolladores

1. Ejecuta `composer code:check` localmente antes de enviar cambios
2. Configura tu IDE para usar estas herramientas (PHPStorm, VS Code, etc.)
3. Utiliza el formateador automático `composer lint:fix` para ahorrar tiempo

## Referencias

- [Guía de estilo de código PSR-12](https://www.php-fig.org/psr/psr-12/)
- [Documentación de PHPStan](https://phpstan.org/user-guide/getting-started)
- [Documentación de Laravel Pint](https://laravel.com/docs/11.x/pint) 
