# Task Master API
asd
Backend API para la aplicación Task Master, desarrollado con Laravel, siguiendo principios de Clean Architecture y TDD.

## Características

- API RESTful con Laravel
- Autenticación basada en JWT
- Arquitectura limpia y modular
- Base de datos MySQL
- Tests unitarios y de integración
- Dockerizado para desarrollo y producción
- Documentación completa con Swagger/OpenAPI

## Requisitos

- PHP 8.1+
- Composer
- Docker y Docker Compose (recomendado)
- MySQL 8.0+

## Instalación y configuración

### Usando Docker (recomendado)

```bash
# Clonar el repositorio
git clone https://github.com/tu-usuario/task-master.git
cd task-master/task-master-api

# Configurar variables de entorno
cp .env.example .env

# Iniciar contenedores Docker
docker-compose up -d

# Instalar dependencias
docker-compose exec app composer install

# Generar clave de aplicación
docker-compose exec app php artisan key:generate

# Ejecutar migraciones y seeders
docker-compose exec app php artisan migrate --seed
```

### Instalación local

```bash
# Clonar el repositorio
git clone https://github.com/tu-usuario/task-master.git
cd task-master/task-master-api

# Configurar variables de entorno
cp .env.example .env

# Instalar dependencias
composer install

# Generar clave de aplicación
php artisan key:generate

# Configurar base de datos en .env

# Ejecutar migraciones y seeders
php artisan migrate --seed
```

## Estructura del Proyecto

```
task-master-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/     # Controladores
│   │   ├── Middleware/      # Middleware
│   │   └── Requests/        # Form Requests para validación
│   ├── Models/              # Modelos Eloquent
│   ├── Services/            # Servicios de la aplicación
│   ├── Repositories/        # Repositorios para acceso a datos
│   └── Exceptions/          # Manejadores de excepciones
├── config/                  # Configuración
├── database/                # Migraciones y seeders
├── routes/                  # Definición de rutas
├── tests/                   # Pruebas unitarias y de integración
├── docs/                    # Documentación
└── docker/                  # Configuración de Docker
```

## Scripts disponibles

```bash
# Iniciar el servidor de desarrollo
php artisan serve

# Ejecutar todas las pruebas
php artisan test

# Ejecutar pruebas con coverage
XDEBUG_MODE=coverage php artisan test --coverage

# Ejecutar pruebas específicas
php artisan test --filter=UserTest

# Ejecutar migraciones
php artisan migrate

# Generar documentación API
php artisan l5-swagger:generate
```

## Desarrollo

### Enfoque TDD

Seguimos un enfoque de desarrollo basado en pruebas (TDD):

1. Escribir una prueba que falle
2. Implementar el código mínimo para que la prueba pase
3. Refactorizar manteniendo las pruebas en verde

Ver más detalles en [docs/tdd-approach.md](docs/tdd-approach.md)

### Estándares de Código

- Seguimos los estándares PSR-12
- Utilizamos PHP CS Fixer y PHPStan para calidad de código
- Documentamos todas las APIs con anotaciones OpenAPI
- Validamos las peticiones con Form Requests

## API Endpoints

La API ofrece los siguientes endpoints principales:

- `POST /api/auth/register` - Registro de usuarios
- `POST /api/auth/login` - Inicio de sesión
- `GET /api/auth/me` - Obtener usuario actual
- `POST /api/auth/logout` - Cerrar sesión
- `GET /api/tasks` - Listar tareas
- `POST /api/tasks` - Crear nueva tarea
- `GET /api/tasks/{id}` - Obtener detalles de tarea
- `PUT /api/tasks/{id}` - Actualizar tarea
- `DELETE /api/tasks/{id}` - Eliminar tarea

Puedes ver la documentación completa en `/api/documentation` cuando el servidor está en ejecución.

## Despliegue

### Con Docker

La API incluye configuración para despliegue con Docker en producción:

```bash
# Construir la imagen
docker build -t task-master-api:latest .

# Ejecutar el contenedor
docker run -d -p 8000:80 --name task-master-api task-master-api:latest
```

Ver más detalles en [docs/deployment.md](docs/deployment.md).

## CI/CD

Utilizamos GitHub Actions para nuestro pipeline de CI/CD:

- Pruebas y linting en cada pull request
- Construcción y despliegue automático en cada merge a main
- Construcción y publicación de imágenes Docker

## Documentación

- [Arquitectura](docs/architecture.md)
- [Enfoque TDD](docs/tdd-approach.md)
- [Guía de Despliegue](docs/deployment.md)

## Contribuir

1. Crea un fork del repositorio
2. Crea tu rama de características (`git checkout -b feature/amazing-feature`)
3. Haz commit de tus cambios (`git commit -m 'Add some amazing feature'`)
4. Haz push a la rama (`git push origin feature/amazing-feature`)
5. Abre un Pull Request

## Licencia

Este proyecto está licenciado bajo la Licencia MIT - consulta el archivo LICENSE para más detalles.
