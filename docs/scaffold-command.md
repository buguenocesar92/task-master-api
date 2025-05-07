# Comando make:scaffold

## Descripción

El comando `make:scaffold` es una herramienta potente de generación de código que permite crear rápidamente una estructura CRUD completa para una entidad. Genera automáticamente todos los componentes necesarios siguiendo los patrones de arquitectura y las convenciones de código establecidas en el proyecto Task Master.

## Beneficios

- **Ahorro de tiempo**: Reduce drásticamente el tiempo necesario para implementar funcionalidades CRUD.
- **Consistencia**: Asegura que todos los componentes cumplan con las convenciones de código y arquitectura del proyecto.
- **Calidad**: Genera código con buenas prácticas y patrones de diseño.
- **Flexibilidad**: Permite personalizar los campos, relaciones y componentes generados.

## Uso básico

```bash
php artisan make:scaffold {name} [opciones]
```

**Donde**:
- `{name}` es el nombre de la entidad que deseas crear en formato StudlyCase (por ejemplo, `Task`, `Project`, `UserProfile`).

## Opciones disponibles

| Opción | Descripción |
|--------|-------------|
| `--no-migration` | No generar archivo de migración |
| `--fields=` | Campos personalizados para la entidad en formato `nombre:tipo,nombre2:tipo2` |
| `--api-resource` | Usar API Resources para formatear respuestas |
| `--with-relations=` | Relaciones entre modelos en formato `tipoRelacion:Modelo,tipoRelacion2:Modelo2` |
| `--with-tests` | Generar tests para controlador, servicio y repositorio |
| `--with-factory` | Generar factory para testing |
| `--with-seeder` | Generar seeder para datos de prueba |

## Tipos de campos soportados

| Tipo | Descripción | Ejemplo |
|------|-------------|---------|
| `string` | Cadena de texto | `nombre:string` |
| `integer` | Número entero | `edad:integer` |
| `boolean` | Valor booleano | `activo:boolean` |
| `text` | Texto largo | `descripcion:text` |
| `date` | Fecha | `fecha_nacimiento:date` |
| `datetime` | Fecha y hora | `creado_en:datetime` |
| `decimal` | Número decimal | `precio:decimal:8,2` |
| `enum` | Enumeración | `estado:enum:pendiente;en_progreso;completado` |
| `json` | Datos JSON | `configuracion:json` |
| `foreignId` | Clave foránea | `user_id:foreignId` |

Para campos opcionales, añadir `:nullable` al final: `descripcion:text:nullable`

## Tipos de relaciones soportados

| Tipo | Descripción | Ejemplo |
|------|-------------|---------|
| `belongsTo` | Pertenece a | `belongsTo:User` |
| `hasMany` | Tiene muchos | `hasMany:Comment` |
| `hasOne` | Tiene uno | `hasOne:Profile` |
| `belongsToMany` | Pertenece a muchos | `belongsToMany:Tag` |

## Ejemplos de uso

### Crear una entidad Task con campos básicos

```bash
php artisan make:scaffold Task --fields=title:string,description:text:nullable,status:enum:pending;in_progress;completed,due_date:date:nullable
```

### Crear una entidad Project con relaciones

```bash
php artisan make:scaffold Project --fields=name:string,description:text:nullable,start_date:date,end_date:date:nullable,user_id:foreignId --with-relations=belongsTo:User,hasMany:Task
```

### Crear una entidad con todos los componentes

```bash
php artisan make:scaffold Comment --fields=content:text,user_id:foreignId,task_id:foreignId --with-relations=belongsTo:User,belongsTo:Task --api-resource --with-tests --with-factory --with-seeder
```

## Componentes generados

Para una entidad llamada `Example`, el comando genera:

1. **Modelo**: `app/Models/Example.php`
2. **Migración**: `database/migrations/xxxx_xx_xx_xxxxxx_create_examples_table.php`
3. **Controlador**: `app/Http/Controllers/ExampleController.php`
4. **Requests**:
   - `app/Http/Requests/Example/StoreExampleRequest.php`
   - `app/Http/Requests/Example/UpdateExampleRequest.php`
5. **Servicio**: `app/Services/ExampleService.php`
6. **Interfaz del Repositorio**: `app/Repositories/Interfaces/ExampleRepositoryInterface.php`
7. **Implementación del Repositorio**: `app/Repositories/ExampleRepository.php`
8. **API Resource** (opcional): `app/Http/Resources/ExampleResource.php`
9. **Factory** (opcional): `database/factories/ExampleFactory.php`
10. **Seeder** (opcional): `database/seeders/ExampleSeeder.php`
11. **Tests** (opcionales):
    - `tests/Feature/Controllers/ExampleControllerTest.php`
    - `tests/Unit/Services/ExampleServiceTest.php`
    - `tests/Unit/Repositories/ExampleRepositoryTest.php`

## Personalización del código generado

El comando intenta ser inteligente al generar el código:

- Añade campos `fillable` en el modelo basados en los campos definidos
- Genera PHPDoc con los tipos de propiedades en el modelo
- Crea validaciones específicas en los FormRequests según los tipos de campos
- Implementa métodos CRUD en el controlador con manejo de errores
- Genera factories con datos de prueba realistas según los tipos de campos
- Crea tests que verifican el comportamiento esperado de cada componente

## Buenas prácticas

- Revisa siempre el código generado para asegurarte de que cumple con tus requisitos específicos
- Considera añadir validaciones adicionales en los FormRequests generados
- Completa las implementaciones de los métodos en los servicios y repositorios según la lógica de negocio
- Añade índices adicionales en las migraciones si es necesario para mejorar el rendimiento

## Limitaciones conocidas

- Las relaciones polimórficas no están soportadas directamente
- Para relaciones complejas, puede ser necesario editar manualmente el código generado
- Los tests generados cubren casos básicos y pueden necesitar ampliación para escenarios específicos 
