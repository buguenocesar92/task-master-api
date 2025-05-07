# Modelo de Base de Datos - Task Master E-commerce

## Visión General

Este documento detalla el modelo de datos para los usuarios, roles y permisos de la plataforma Task Master E-commerce, siguiendo el enfoque del paquete Spatie Laravel Permission.

## Diagrama ER

![Diagrama ER del Sistema de Permisos](images/database-er-diagram.png)

*Nota: Para crear o actualizar este diagrama:*
1. *Utilizar [dbdiagram.io](https://dbdiagram.io/) o [draw.io](https://app.diagrams.net/)*
2. *Exportar como PNG o SVG*
3. *Guardar en la carpeta `docs/images/`*
4. *Actualizar este documento si hay cambios en el modelo*

## Entidades Principales

### Users (Usuarios)
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | bigint | Identificador único |
| name | varchar(255) | Nombre completo |
| email | varchar(255) | Email (único) |
| password | varchar(255) | Contraseña hasheada |
| email_verified_at | timestamp | Verificación de email |
| remember_token | varchar(100) | Token para "recordarme" |
| created_at | timestamp | Fecha de creación |
| updated_at | timestamp | Fecha de actualización |

### Roles (Roles)
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | bigint | Identificador único |
| name | varchar(255) | Nombre del rol (admin, customer, etc.) |
| guard_name | varchar(255) | Nombre del guard para multiples guards |
| created_at | timestamp | Fecha de creación |
| updated_at | timestamp | Fecha de actualización |

### Permissions (Permisos)
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | bigint | Identificador único |
| name | varchar(255) | Nombre del permiso |
| guard_name | varchar(255) | Nombre del guard para multiples guards |
| created_at | timestamp | Fecha de creación |
| updated_at | timestamp | Fecha de actualización |

### Model_Has_Permissions (Permisos por Modelo)
| Campo | Tipo | Descripción |
|-------|------|-------------|
| permission_id | bigint | Referencia a permisos |
| model_type | varchar(255) | Tipo de modelo (normalmente App\Models\User) |
| model_id | bigint | ID del modelo |

### Model_Has_Roles (Roles por Modelo)
| Campo | Tipo | Descripción |
|-------|------|-------------|
| role_id | bigint | Referencia a roles |
| model_type | varchar(255) | Tipo de modelo (normalmente App\Models\User) |
| model_id | bigint | ID del modelo |

### Role_Has_Permissions (Permisos por Rol)
| Campo | Tipo | Descripción |
|-------|------|-------------|
| permission_id | bigint | Referencia a permisos |
| role_id | bigint | Referencia a roles |

## Índices y Optimizaciones

- Índices en columnas de búsqueda frecuente (email)
- Índices compuestos para model_has_permissions y model_has_roles
- Claves foráneas con índices para mejorar joins

## Consideraciones sobre el Modelado

1. **Herencia de Permisos**
   - Los usuarios pueden tener permisos directamente o a través de roles
   - Un usuario puede tener múltiples roles
   - Un rol puede tener múltiples permisos

2. **Guards**
   - El campo guard_name permite utilizar diferentes guards de autenticación
   - Por defecto se utiliza el guard 'web' si no se especifica otro

3. **Relaciones Polimórficas**
   - Las tablas model_has_roles y model_has_permissions utilizan relaciones polimórficas
   - Esto permite asignar roles y permisos a cualquier modelo, no solo a usuarios

## Uso con Laravel

```php
// Asignar rol a usuario
$user->assignRole('admin');

// Verificar si usuario tiene rol
$user->hasRole('admin');

// Asignar permiso a rol
$role->givePermissionTo('edit articles');

// Verificar permiso
$user->can('edit articles');
```

## Estrategia de Migraciones

Las migraciones se implementarán en el siguiente orden:

1. Tablas base (users, roles, permissions)


## Reglas de Integridad


