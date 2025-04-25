# Enfoque TDD en el Proyecto

Este documento describe el enfoque de Desarrollo Guiado por Pruebas (TDD) que seguimos en este proyecto.

## Principios de TDD

1. **Red-Green-Refactor**: 
   - Red: Escribe una prueba que falle
   - Green: Escribe el código mínimo para que la prueba pase
   - Refactor: Mejora el código manteniendo las pruebas en verde

2. **Escribe pruebas primero**: 
   - Define el comportamiento esperado antes de implementarlo
   - Clarifica los requisitos y el diseño
   - Mantiene el foco en lo que se necesita conseguir

3. **Pruebas pequeñas e incrementales**:
   - Cada prueba debe verificar una sola funcionalidad
   - Avanza en pequeños incrementos

## Estructura de Pruebas en el Proyecto

```
tests/
├── Feature/            # Pruebas de integración y endpoints API
│   ├── Auth/           # Pruebas de autenticación
│   ├── Tasks/          # Pruebas de API de tareas
│   └── ...
├── Unit/               # Pruebas unitarias
│   ├── Services/       # Pruebas de servicios
│   ├── Models/         # Pruebas de modelos
│   └── ...
└── TestCase.php        # Caso base para todas las pruebas
```

## Tipos de Pruebas

### Pruebas Unitarias

- Prueban componentes individuales en aislamiento
- Usan mocks/stubs para las dependencias
- Rápidas de ejecutar

```php
// Ejemplo de prueba unitaria para un servicio
public function test_task_service_can_create_task()
{
    $taskData = [
        'title' => 'Test Task',
        'description' => 'Test Description',
        'due_date' => now()->addDay(),
    ];
    
    $taskService = new TaskService();
    $task = $taskService->createTask($taskData);
    
    $this->assertEquals($taskData['title'], $task->title);
    $this->assertEquals($taskData['description'], $task->description);
}
```

### Pruebas de API/Feature

- Prueban endpoints completos de la API
- Verifican el flujo completo incluyendo middleware, validación, etc.
- Aseguran que la API responde correctamente

```php
// Ejemplo de prueba de API
public function test_can_create_task_via_api()
{
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
        ->postJson('/api/tasks', [
            'title' => 'New Task',
            'description' => 'Task Description',
            'due_date' => now()->addDay()->toDateTimeString(),
        ]);
    
    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'title', 'description', 'due_date']
        ]);
}
```

## Flujo de Trabajo TDD en el Proyecto

1. **Crear la prueba**:
   ```bash
   php artisan make:test TaskControllerTest --feature
   ```

2. **Escribir el caso de prueba**:
   - Definir los datos de entrada
   - Definir el comportamiento esperado
   - Ejecutar la prueba (debe fallar)

3. **Implementar la funcionalidad**:
   - Crear modelos, controladores, servicios necesarios
   - Implementar solo lo necesario para que la prueba pase

4. **Refactorizar**:
   - Mejorar el código sin cambiar su comportamiento
   - Asegurar que las pruebas siguen pasando

5. **Repetir** para cada funcionalidad

## Buenas Prácticas

1. **Datos de prueba claros**:
   - Usa factories y fakers para generar datos de prueba
   - Evita hardcodear IDs u otros valores dependientes del entorno

2. **Aislamiento de pruebas**:
   - Cada prueba debe funcionar independientemente
   - Usa DatabaseTransactions o DatabaseMigrations

3. **Cobertura de casos límite**:
   - Prueba valores nulos, vacíos, máximos, mínimos
   - Considera casos de error y excepciones

4. **Documentación a través de las pruebas**:
   - Nombres descriptivos de métodos de prueba
   - Comentarios que expliquen el propósito de la prueba

## Ejecución de Pruebas

```bash
# Ejecutar todas las pruebas
php artisan test

# Ejecutar un grupo específico
php artisan test --filter=TaskTest

# Ejecutar con cobertura (requiere Xdebug)
php artisan test --coverage
```

## Integración con CI/CD

Las pruebas se ejecutan automáticamente en el pipeline de CI/CD:

1. Cada Pull Request activa la ejecución de pruebas
2. Los cambios solo se fusionan si todas las pruebas pasan
3. Se genera un informe de cobertura para revisar

## Recursos de Aprendizaje

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [Test-Driven Laravel](https://testdrivenlaravel.com/)
- [Laravel Testing Decoded](https://leanpub.com/laravel-testing-decoded) 
