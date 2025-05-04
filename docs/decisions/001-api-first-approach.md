# ADR-001: Adopción del Enfoque API-First

## Contexto

El desarrollo del e-commerce Task Master requiere tanto un frontend para clientes como un panel de administración. Debemos decidir la mejor estrategia para implementar estos componentes evitando duplicación de código y esfuerzos.

## Opciones consideradas

1. **Laravel Filament para administración + Frontend separado**: Usar Filament para el panel de administración, conectado directamente a la base de datos, y un frontend separado con Next.js.

2. **Enfoque API-first**: Desarrollar una única API REST que sirva a todos los frontends, tanto el público como el administrativo.

3. **Monolito tradicional**: Implementar tanto la lógica de administración como la de cliente dentro de un único monolito Laravel con Blade Templates.

## Decisión

**Hemos decidido adoptar el enfoque API-first (opción 2)**, donde desarrollaremos:

1. Una API REST completa en Laravel que contiene toda la lógica de negocio
2. Un frontend de cliente con Next.js que consume esta API
3. Un panel de administración también con Next.js que consume la misma API con diferente nivel de permisos

## Justificación

- **Evita duplicación**: La lógica de negocio se implementa una sola vez en la API
- **Consistencia**: Mismo modelo de datos y validaciones para toda la plataforma
- **Flexibilidad**: Facilita el desarrollo futuro de aplicaciones móviles u otras interfaces
- **Mantenibilidad**: Los cambios en lógica de negocio se implementan en un solo lugar
- **Separación de preocupaciones**: La API maneja la lógica mientras los frontends se enfocan en UI/UX
- **Rendimiento**: Interfaces de usuario óptimas con Next.js

## Consecuencias

### Positivas
- Mayor cohesión y menor acoplamiento en el código
- Escalabilidad mejorada (vertical y horizontal)
- Posibilidad de equipos especializados por componente (API, frontend cliente, frontend admin)
- Facilidad para implementar pruebas automatizadas

### Negativas
- Mayor complejidad inicial en configuración
- Necesidad de implementar robusta autenticación y autorización en la API
- Posible overhead en comunicación API para operaciones sencillas

## Estado

Aprobado

## Referencias

- [Arquitectura de Task Master](../architecture.md)
- [Estrategia de Frontend](../frontend-strategy.md) 
