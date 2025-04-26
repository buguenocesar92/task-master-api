# Guía de Despliegue en AWS

Este documento describe el proceso para desplegar la aplicación en AWS utilizando GitHub Actions.

## Requisitos Previos

- Cuenta AWS (Capa gratuita es suficiente)
- Credenciales de AWS con permisos IAM apropiados
- Repositorio GitHub configurado con GitHub Actions

## Configuración de Secretos en GitHub

1. Navega a tu repositorio en GitHub
2. Ve a Settings > Secrets and variables > Actions
3. Agrega los siguientes secretos:
   - `AWS_ACCESS_KEY_ID`: Tu ID de clave de acceso de AWS
   - `AWS_SECRET_ACCESS_KEY`: Tu clave secreta de acceso de AWS
   - `AWS_REGION`: La región donde desplegarás (ej. us-east-1)
   - `AWS_ECR_REPOSITORY`: Nombre del repositorio ECR
   - `EC2_HOST`: Dirección IP o DNS de tu instancia EC2
   - `EC2_USERNAME`: Usuario para conectarse a EC2 (normalmente ec2-user)
   - `EC2_SSH_KEY`: Clave SSH privada para conectarse a la instancia EC2
   - `RDS_HOST`: Endpoint de tu instancia RDS
   - `RDS_DATABASE`: Nombre de la base de datos
   - `RDS_USERNAME`: Usuario de la base de datos
   - `RDS_PASSWORD`: Contraseña de la base de datos

## Infraestructura AWS

### Creación de Recursos en AWS

1. Accede a la consola de AWS en https://aws.amazon.com/console/
2. Crea los siguientes recursos manualmente desde la consola:
   - VPC con subredes públicas y privadas
   - Instancia EC2 (t2.micro)
   - Base de datos RDS MySQL
   - Repositorio ECR para imágenes Docker
   - Bucket S3 para archivos estáticos
3. Configura los grupos de seguridad para permitir comunicación entre los servicios
4. Establece roles y políticas IAM necesarios para el despliegue

### Recursos Creados

- VPC con subredes públicas y privadas
- Instancia EC2 (t2.micro)
- Base de datos RDS MySQL
- Repositorio ECR para imágenes Docker
- Bucket S3 para archivos estáticos
- Roles y políticas IAM necesarios
- Grupo de seguridad configurado

## Proceso de Despliegue Automatizado

El flujo de despliegue automatizado con GitHub Actions incluye:

1. **Verificación**: Pruebas y análisis de calidad de código
2. **Construcción**: Compilación de la aplicación y creación de imagen Docker
3. **Publicación**: Subida de la imagen Docker a Amazon ECR
4. **Despliegue**: Actualización de la instancia EC2 con la nueva imagen

### Detalles del Flujo de CI/CD

```
Código → GitHub → GitHub Actions → Tests y Análisis → Construcción de Docker → ECR → EC2
```

## Despliegue Manual (en caso necesario)

Si necesitas hacer un despliegue manual, sigue estos pasos:

1. Conéctate a la instancia EC2:
   ```bash
   ssh -i "tu-clave.pem" ec2-user@tu-instancia-ec2.amazonaws.com
   ```

2. Inicia sesión en ECR:
   ```bash
   aws ecr get-login-password --region tu-region | docker login --username AWS --password-stdin tu-cuenta.dkr.ecr.tu-region.amazonaws.com
   ```

3. Actualiza la imagen del contenedor:
   ```bash
   docker pull tu-cuenta.dkr.ecr.tu-region.amazonaws.com/tu-repositorio:latest
   docker-compose down
   docker-compose up -d
   ```

## Verificación del Despliegue

1. La aplicación estará disponible en:
   ```
   http://tu-instancia-ec2.amazonaws.com
   ```

2. Verifica los logs:
   ```bash
   docker-compose logs -f app
   ```

3. Comprueba el estado de los contenedores:
   ```bash
   docker ps
   ```

4. Verifica la conexión a la base de datos:
   ```bash
   docker-compose exec app php artisan migrate:status
   ```

## Troubleshooting

- **Problema de conexión a la base de datos**: Verifica que los grupos de seguridad permitan tráfico entre EC2 y RDS
- **Problemas de despliegue**: Revisa los logs de GitHub Actions y CloudWatch
- **Errores de aplicación**: Consulta los logs del contenedor Docker

## Rollback

Para revertir a una versión anterior:

1. Identifica la etiqueta de la versión anterior en ECR
2. Actualiza el archivo `.github/workflows/deploy.yml` para usar esa etiqueta
3. Ejecuta el flujo de GitHub Actions manualmente o haz push de un cambio menor 