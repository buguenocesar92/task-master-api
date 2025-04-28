<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeScaffoldCommand extends Command
{
    /**
     * El nombre y la firma del comando.
     *
     * @var string
     */
    protected $signature = 'make:scaffold {name : El nombre de la entidad (ej. Example)}
                          {--no-migration : No generar migración}
                          {--fields= : Campos personalizados para la entidad (ej. nombre:string,edad:integer)}
                          {--api-resource : Usar API Resources para formatear respuestas}
                          {--with-relations= : Relaciones entre modelos (ej. belongsTo:User,hasMany:Comment)}
                          {--with-tests : Generar tests para controlador, servicio y repositorio}
                          {--with-factory : Generar factory para testing}
                          {--with-seeder : Generar seeder para datos de prueba}';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Genera modelo, migración, controlador, interfaz, repositorio, servicio, rutas y requests para una entidad dada, basados en el modelo';

    /**
     * Ejecuta el comando.
     */
    public function handle()
    {
        // Convertir el argumento en StudlyCase para nombres de clases y en snake_case para rutas.
        $name = Str::studly($this->argument('name'));
        $nameLower = Str::camel($name);
        $parameter = Str::snake($name);           // Ej.: task
        $prefix = Str::plural($parameter);          // Ej.: tasks
        $controllerName = "{$name}Controller";

        // Procesar opciones
        $noMigration = $this->option('no-migration');
        $withApiResource = $this->option('api-resource');
        $withTests = $this->option('with-tests');
        $withFactory = $this->option('with-factory');
        $withSeeder = $this->option('with-seeder');

        // Procesar campos personalizados
        $fields = $this->parseFields($this->option('fields'));

        // Procesar relaciones
        $relations = $this->parseRelations($this->option('with-relations'));

        // 1. Crear el modelo (con o sin migración según la opción).
        $modelOptions = ['name' => $name];
        if (! $noMigration) {
            $modelOptions['--migration'] = true;
        }
        if ($withFactory) {
            $modelOptions['--factory'] = true;
        }
        if ($withSeeder) {
            $modelOptions['--seed'] = true;
        }

        $this->call('make:model', $modelOptions);

        // 1.1 Actualizar la migración con campos personalizados si se ha generado.
        if (! $noMigration) {
            $tableName = Str::plural(Str::snake($name));
            $migrationFiles = glob(database_path("migrations/*_create_{$tableName}_table.php"));

            if (count($migrationFiles) > 0) {
                $migrationPath = $migrationFiles[0];
                $migrationContent = file_get_contents($migrationPath);

                // Generar código para los campos
                $fieldsCode = $this->generateMigrationFieldsCode($fields);

                // Insertar campos justo después de la clave primaria.
                $migrationContent = preg_replace(
                    '/(\$table->id\(\);)/',
                    "$1\n{$fieldsCode}",
                    $migrationContent
                );

                file_put_contents($migrationPath, $migrationContent);
                $this->info("Migración actualizada con campos personalizados en {$migrationPath}.");
            } else {
                $this->error("No se encontró la migración para la tabla {$tableName}.");
            }
        }

        // 1.2 Actualizar el modelo para agregar la propiedad fillable y relaciones.
        $modelPath = app_path("Models/{$name}.php");
        if (! file_exists($modelPath)) {
            // En algunos proyectos el modelo se crea en la raíz de app/
            $modelPath = app_path("{$name}.php");
        }

        if (file_exists($modelPath)) {
            $modelContent = file_get_contents($modelPath);

            // Generar array de campos fillable
            $fillableFields = array_keys($fields);
            $fillableString = "'".implode("', '", $fillableFields)."'";

            // Generar PHPDoc para las propiedades
            $phpDocProperties = [];
            $phpDocProperties[] = '/**';
            $phpDocProperties[] = ' * @property int $id';
            foreach ($fields as $fieldName => $field) {
                $docType = $this->getPhpDocTypeForField($field['type'], $field['nullable'] ?? false);
                $phpDocProperties[] = " * @property {$docType} \${$fieldName}";
            }
            $phpDocProperties[] = ' * @property \Carbon\Carbon $created_at';
            $phpDocProperties[] = ' * @property \Carbon\Carbon $updated_at';
            if (str_contains($modelContent, 'SoftDeletes')) {
                $phpDocProperties[] = ' * @property \Carbon\Carbon|null $deleted_at';
            }
            $phpDocProperties[] = ' */';
            $phpDocString = implode("\n", $phpDocProperties);

            if (strpos($modelContent, 'protected $fillable') === false) {
                // Add PHPDoc
                $modelContent = preg_replace(
                    '/(class\s+'.$name.'\s+extends\s+\S+\s*\{)/',
                    "$phpDocString\n$1",
                    $modelContent
                );

                // Añadir el trait HasFactory si no existe ya
                if (strpos($modelContent, 'use HasFactory') === false) {
                    $modelContent = preg_replace(
                        '/(class\s+'.$name.'\s+extends\s+\S+\s*\{)/',
                        "$1\n    use HasFactory;",
                        $modelContent
                    );
                }

                // Añadir fillable
                $modelContent = preg_replace(
                    '/(use HasFactory;)/',
                    "$1\n\n    protected \$fillable = [{$fillableString}];",
                    $modelContent
                );

                // Añadir métodos de relaciones si existen
                if (! empty($relations)) {
                    $relationsCode = $this->generateModelRelationsCode($relations);
                    $modelContent = str_replace('}', $relationsCode."\n}", $modelContent);
                }

                file_put_contents($modelPath, $modelContent);
                $this->info("Modelo {$name} actualizado con fillable".(! empty($relations) ? ' y relaciones' : '').'.');
            }
        } else {
            $this->error("No se encontró el modelo {$name}.");
        }

        // 2. Crear el controlador y sobrescribirlo con métodos CRUD que usen los FormRequest generados.
        $this->call('make:controller', [
            'name' => $controllerName,
        ]);

        // Generar API Resource si la opción está activa
        $resourceClass = "{$name}Resource";
        $resourcePath = null;

        if ($withApiResource) {
            $this->call('make:resource', [
                'name' => $resourceClass,
            ]);
            $resourcePath = app_path("Http/Resources/{$resourceClass}.php");

            // Personalizar el API Resource si se generó correctamente
            if (file_exists($resourcePath)) {
                $resourceContent = file_get_contents($resourcePath);

                // Crear un API Resource básico que devuelva todos los atributos del modelo
                $resourceContent = <<<EOT
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class {$resourceClass} extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request \$request): array
    {
        return parent::toArray(\$request);
    }
}
EOT;
                file_put_contents($resourcePath, $resourceContent);
                $this->info("API Resource {$resourceClass} generado y personalizado exitosamente.");
            } else {
                $this->info("API Resource {$resourceClass} generado exitosamente.");
            }
        }

        $controllerPath = app_path("Http/Controllers/{$controllerName}.php");

        // Preparar las importaciones según las opciones
        $imports = [
            "use App\Services\\{$name}Service;",
            "use App\Http\Requests\\{$name}\\Store{$name}Request;",
            "use App\Http\Requests\\{$name}\\Update{$name}Request;",
        ];

        if ($withApiResource) {
            $imports[] = "use App\Http\Resources\\{$resourceClass};";
            $imports[] = "use Illuminate\Http\Resources\Json\AnonymousResourceCollection;";
        }

        $importsCode = implode("\n", $imports);

        // Generar el contenido del controlador según la opción de API Resource
        if ($withApiResource) {
            $controllerContent = <<<EOT
<?php

namespace App\Http\Controllers;

{$importsCode}

class {$controllerName} extends Controller
{
    protected {$name}Service \${$nameLower}Service;

    public function __construct({$name}Service \${$nameLower}Service)
    {
        \$this->{$nameLower}Service = \${$nameLower}Service;
    }

    /**
     * Mostrar un listado del recurso.
     *
     * @return AnonymousResourceCollection
     */
    public function index()
    {
        \$data = \$this->{$nameLower}Service->getAll();
        return {$resourceClass}::collection(\$data);
    }

    /**
     * Almacenar un nuevo recurso.
     *
     * @param Store{$name}Request \$request
     * @return {$resourceClass}
     */
    public function store(Store{$name}Request \$request)
    {
        \$data = \$this->{$nameLower}Service->create(\$request->validated());
        return new {$resourceClass}(\$data);
    }

    /**
     * Mostrar el recurso especificado.
     *
     * @param int \$id
     * @return {$resourceClass}
     */
    public function show(\$id)
    {
        \$data = \$this->{$nameLower}Service->findById(\$id);
        return new {$resourceClass}(\$data);
    }

    /**
     * Actualizar el recurso especificado.
     *
     * @param Update{$name}Request \$request
     * @param int \$id
     * @return {$resourceClass}
     */
    public function update(Update{$name}Request \$request, \$id)
    {
        \$data = \$this->{$nameLower}Service->update(\$id, \$request->validated());
        return new {$resourceClass}(\$data);
    }

    /**
     * Eliminar el recurso especificado.
     *
     * @param int \$id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(\$id)
    {
        \$this->{$nameLower}Service->delete(\$id);
        return response()->json(['message' => '{$name} eliminado']);
    }
}
EOT;
        } else {
            $controllerContent = <<<EOT
<?php

namespace App\Http\Controllers;

{$importsCode}

class {$controllerName} extends Controller
{
    protected {$name}Service \${$nameLower}Service;

    public function __construct({$name}Service \${$nameLower}Service)
    {
        \$this->{$nameLower}Service = \${$nameLower}Service;
    }

    /**
     * Mostrar un listado del recurso.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        \$data = \$this->{$nameLower}Service->getAll();
        return response()->json(\$data);
    }

    /**
     * Almacenar un nuevo recurso.
     *
     * @param Store{$name}Request \$request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Store{$name}Request \$request)
    {
        \$data = \$this->{$nameLower}Service->create(\$request->validated());
        return response()->json(\$data, 201);
    }

    /**
     * Mostrar el recurso especificado.
     *
     * @param int \$id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(\$id)
    {
        \$data = \$this->{$nameLower}Service->findById(\$id);
        return response()->json(\$data);
    }

    /**
     * Actualizar el recurso especificado.
     *
     * @param Update{$name}Request \$request
     * @param int \$id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Update{$name}Request \$request, \$id)
    {
        \$data = \$this->{$nameLower}Service->update(\$id, \$request->validated());
        return response()->json(\$data);
    }

    /**
     * Eliminar el recurso especificado.
     *
     * @param int \$id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(\$id)
    {
        \$this->{$nameLower}Service->delete(\$id);
        return response()->json(['message' => '{$name} eliminado']);
    }
}
EOT;
        }

        file_put_contents($controllerPath, $controllerContent);
        $this->info("Controlador {$controllerName} generado exitosamente.");

        // 3. Generar la interfaz, repositorio y servicio.
        $interfacePath = app_path("Repositories/Contracts/{$name}RepositoryInterface.php");
        $repositoryPath = app_path("Repositories/{$name}Repository.php");
        $servicePath = app_path("Services/{$name}Service.php");

        if (! is_dir(app_path('Repositories/Contracts'))) {
            mkdir(app_path('Repositories/Contracts'), 0755, true);
        }
        if (! is_dir(app_path('Repositories'))) {
            mkdir(app_path('Repositories'), 0755, true);
        }
        if (! is_dir(app_path('Services'))) {
            mkdir(app_path('Services'), 0755, true);
        }
        if (file_exists($interfacePath) || file_exists($repositoryPath) || file_exists($servicePath)) {
            $this->error('Algunos de los archivos de repositorio/servicio ya existen. Abortando la generación.');

            return;
        }

        $interfaceContent = <<<EOT
<?php

namespace App\Repositories\Contracts;

interface {$name}RepositoryInterface
{
    public function getAll();
    public function findById(int \$id);
    public function create(array \$data);
    public function update(int \$id, array \$data);
    public function delete(int \$id);
}
EOT;

        $repositoryContent = <<<EOT
<?php

namespace App\Repositories;

use App\Repositories\Contracts\\{$name}RepositoryInterface;
use App\Models\\{$name};

class {$name}Repository implements {$name}RepositoryInterface
{
    public function getAll()
    {
        return {$name}::query()->get();
    }

    public function findById(int \$id)
    {
        return {$name}::query()->findOrFail(\$id);
    }

    public function create(array \$data)
    {
        return {$name}::query()->create(\$data);
    }

    public function update(int \$id, array \$data)
    {
        \$model = {$name}::query()->findOrFail(\$id);
        \$model->update(\$data);
        return \$model;
    }

    public function delete(int \$id): void
    {
        \$model = {$name}::query()->findOrFail(\$id);
        \$model->delete();
    }
}
EOT;

        $serviceContent = <<<EOT
<?php

namespace App\Services;

use App\Repositories\Contracts\\{$name}RepositoryInterface;

class {$name}Service
{
    private {$name}RepositoryInterface \$repository;

    public function __construct({$name}RepositoryInterface \$repository)
    {
        \$this->repository = \$repository;
    }

    public function getAll()
    {
        return \$this->repository->getAll();
    }

    public function findById(int \$id)
    {
        return \$this->repository->findById(\$id);
    }

    public function create(array \$data)
    {
        return \$this->repository->create(\$data);
    }

    public function update(int \$id, array \$data)
    {
        return \$this->repository->update(\$id, \$data);
    }

    public function delete(int \$id): void
    {
        \$this->repository->delete(\$id);
    }
}
EOT;
        file_put_contents($interfacePath, $interfaceContent);
        file_put_contents($repositoryPath, $repositoryContent);
        file_put_contents($servicePath, $serviceContent);
        $this->info("Scaffold generado para la entidad {$name} exitosamente.");

        // 4. Actualizar AppServiceProvider para registrar la vinculación.
        $providerPath = app_path('Providers/AppServiceProvider.php');
        $providerContent = file_get_contents($providerPath);
        $binding = "\$this->app->bind(\n            \\App\\Repositories\\\\Contracts\\\\{$name}RepositoryInterface::class,\n            \\App\\Repositories\\\\{$name}Repository::class\n        );";

        if (strpos($providerContent, $binding) === false) {
            // Buscar el método register
            if (preg_match('/public function register\(\).*?{/s', $providerContent)) {
                // Laravel 8+ usa register sin tipo de retorno
                $providerContent = preg_replace(
                    '/(public function register\(\).*?{)/s',
                    "$1\n        {$binding}\n",
                    $providerContent
                );
            } elseif (preg_match('/public function register\(\): void.*?{/s', $providerContent)) {
                // Laravel 9+ usa register(): void
                $providerContent = preg_replace(
                    '/(public function register\(\): void.*?{)/s',
                    "$1\n        {$binding}\n",
                    $providerContent
                );
            } else {
                // Crear el método register si no existe
                $pattern = '/(class\s+AppServiceProvider.*?{)/s';
                $replacement = "$1\n\n    /**\n     * Register any application services.\n     */\n    public function register(): void\n    {\n        {$binding}\n    }\n";
                $providerContent = preg_replace($pattern, $replacement, $providerContent);
            }

            file_put_contents($providerPath, $providerContent);
            $this->info('Se ha registrado la vinculación en AppServiceProvider.');
        } else {
            $this->info('La vinculación ya existe en AppServiceProvider.');
        }

        // 5. Generar el archivo de rutas genérico en routes/api y actualizar routes/api.php.
        $apiDir = base_path('routes/api');
        if (! is_dir($apiDir)) {
            mkdir($apiDir, 0755, true);
        }

        // Crear contenido para las rutas
        $routesApiContent = <<<EOT
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\\{$name}Controller;

// Rutas para {$name}
Route::group(['prefix' => '{$prefix}'], function () {
    Route::get('/', [{$name}Controller::class, 'index'])
        ->name('{$prefix}.index');
    Route::post('/', [{$name}Controller::class, 'store'])
        ->name('{$prefix}.store');
    Route::get('/{{$parameter}}', [{$name}Controller::class, 'show'])
        ->name('{$prefix}.show');
    Route::put('/{{$parameter}}', [{$name}Controller::class, 'update'])
        ->name('{$prefix}.update');
    Route::delete('/{{$parameter}}', [{$name}Controller::class, 'destroy'])
        ->name('{$prefix}.destroy');
});
EOT;

        // Opción 1: Crear un archivo de rutas separado
        $routesFilePath = $apiDir.'/'.$prefix.'.php';

        if (file_exists($routesFilePath)) {
            $this->error("El archivo de rutas {$routesFilePath} ya existe. No se sobrescribirá.");
        } else {
            file_put_contents($routesFilePath, $routesApiContent);
            $this->info("Archivo de rutas generado en routes/api/{$prefix}.php");

            // Actualizar el archivo routes/api.php para incluir las rutas generadas
            $mainRoutesPath = base_path('routes/api.php');
            $mainRoutesContent = file_get_contents($mainRoutesPath);
            $requireLine = "require __DIR__ . '/api/{$prefix}.php';";

            if (strpos($mainRoutesContent, $requireLine) === false && strpos($mainRoutesContent, "require __DIR__.'/api/{$prefix}.php';") === false) {
                file_put_contents($mainRoutesPath, "\n" . $requireLine, FILE_APPEND);
                $this->info("Se ha actualizado routes/api.php para incluir {$prefix}.php.");
            }
        }

        // Opción 2: Añadir directamente las rutas en api.php
        $mainRoutesPath = base_path('routes/api.php');
        $mainRoutesContent = file_get_contents($mainRoutesPath);

        // Formato reducido para añadir directamente a api.php
        $inlineRoutesContent = <<<EOT

// Rutas para {$name} - Generadas automáticamente
Route::group(['prefix' => '{$prefix}'], function () {
    Route::get('/', [App\Http\Controllers\\{$name}Controller::class, 'index'])
        ->name('{$prefix}.index');
    Route::post('/', [App\Http\Controllers\\{$name}Controller::class, 'store'])
        ->name('{$prefix}.store');
    Route::get('/{{$parameter}}', [App\Http\Controllers\\{$name}Controller::class, 'show'])
        ->name('{$prefix}.show');
    Route::put('/{{$parameter}}', [App\Http\Controllers\\{$name}Controller::class, 'update'])
        ->name('{$prefix}.update');
    Route::delete('/{{$parameter}}', [App\Http\Controllers\\{$name}Controller::class, 'destroy'])
        ->name('{$prefix}.destroy');
});
EOT;

        // Agregar las rutas directamente a api.php si no existen ya
        // (priorizar este método para mayor compatibilidad con los tests)
        if (strpos($mainRoutesContent, "// Rutas para {$name}") === false) {
            // Si ya existía un require pero no las rutas directas, añadir solo las rutas
            file_put_contents($mainRoutesPath, $inlineRoutesContent, FILE_APPEND);
            $this->info('Se han añadido rutas directamente en api.php para mayor compatibilidad.');
        }

        // 6. Crear ApiFormRequest si no existe.
        $apiFormRequestPath = app_path('Http/Requests/ApiFormRequest.php');
        if (! file_exists($apiFormRequestPath)) {
            $apiFormRequestContent = <<<EOT
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiFormRequest extends FormRequest
{
    /**
     * Forzar la respuesta en JSON cuando falla la validación.
     */
    protected function failedValidation(Validator \$validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => \$validator->errors(),
            ], 422)
        );
    }

    /**
     * Forzar la respuesta en JSON cuando falla la autorización.
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'This action is unauthorized.',
            ], 403)
        );
    }
}
EOT;
            file_put_contents($apiFormRequestPath, $apiFormRequestContent);
            $this->info('ApiFormRequest creado en app/Http/Requests/ApiFormRequest.php');
        } else {
            $this->info('ApiFormRequest ya existe.');
        }

        // 7. Crear las requests Store y Update para la entidad basadas en los campos.
        $requestDir = app_path("Http/Requests/{$name}");
        if (! is_dir($requestDir)) {
            mkdir($requestDir, 0755, true);
        }
        $storeRequestPath = $requestDir."/Store{$name}Request.php";
        $updateRequestPath = $requestDir."/Update{$name}Request.php";

        if (file_exists($storeRequestPath) || file_exists($updateRequestPath)) {
            $this->error("Las requests para {$name} ya existen.");
        } else {
            // Generar reglas de validación basadas en los campos
            $rulesCode = $this->generateValidationRules($fields);
            $updateRulesCode = $this->generateValidationRules($fields, true);

            $storeRequestContent = <<<EOT
<?php

namespace App\Http\Requests\\{$name};

use App\Http\Requests\ApiFormRequest;

class Store{$name}Request extends ApiFormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
{$rulesCode}
        ];
    }
}
EOT;
            $updateRequestContent = <<<EOT
<?php

namespace App\Http\Requests\\{$name};

use App\Http\Requests\ApiFormRequest;

class Update{$name}Request extends ApiFormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
{$updateRulesCode}
        ];
    }
}
EOT;
            file_put_contents($storeRequestPath, $storeRequestContent);
            file_put_contents($updateRequestPath, $updateRequestContent);
            $this->info("Requests creadas en app/Http/Requests/{$name}/");
        }

        // 8. Generar factory si la opción está activa y no se creó con el modelo
        if ($withFactory && ! file_exists(database_path("factories/{$name}Factory.php"))) {
            $this->call('make:factory', [
                'name' => "{$name}Factory",
                '--model' => "\\App\\Models\\{$name}",
            ]);
            $this->info("Factory para {$name} creada exitosamente.");

            // Actualizar el factory con los campos personalizados
            $factoryPath = database_path("factories/{$name}Factory.php");
            if (file_exists($factoryPath)) {
                $factoryContent = file_get_contents($factoryPath);
                $factoryFieldsCode = $this->generateFactoryFields($fields);

                $factoryContent = preg_replace(
                    '/return \[\s*\];/',
                    "return [\n{$factoryFieldsCode}        ];",
                    $factoryContent
                );

                file_put_contents($factoryPath, $factoryContent);
                $this->info("Factory {$name}Factory actualizada con campos personalizados.");
            }
        }

        // 9. Generar seeder si la opción está activa
        if ($withSeeder) {
            $seederName = "{$name}Seeder";
            $this->call('make:seeder', [
                'name' => $seederName,
            ]);

            // Actualizar el seeder para usar el factory
            $seederPath = database_path("seeders/{$seederName}.php");
            if (file_exists($seederPath)) {
                $seederContent = file_get_contents($seederPath);

                // Añadir use para la clase de modelo
                if (strpos($seederContent, "use App\\Models\\{$name};") === false) {
                    $seederContent = str_replace(
                        'use Illuminate\\Database\\Seeder;',
                        "use Illuminate\\Database\\Seeder;\nuse App\\Models\\{$name};",
                        $seederContent
                    );
                }

                // Añadir código para crear registros mediante el factory
                $seederContent = preg_replace(
                    '/public function run\(\): void\s*\{\s*\}/s',
                    "public function run(): void\n    {\n        {$name}::factory(10)->create();\n    }",
                    $seederContent
                );

                file_put_contents($seederPath, $seederContent);
                $this->info("Seeder {$seederName} actualizado para usar factory.");
            }
        }

        // 10. Generar tests si la opción está activa
        if ($withTests) {
            $this->generateTests($name, $prefix, $fields);
        }

        // 11. Ejecutar el formateador de código para corregir problemas de estilo
        $this->info('Aplicando formateador de código para cumplir con los estándares de estilo...');
        if (file_exists(base_path('vendor/bin/pint'))) {
            system(base_path('vendor/bin/pint'), $exitCode);
            if ($exitCode === 0) {
                $this->info('Código formateado exitosamente.');
            } else {
                $this->warn('No se pudo formatear el código automáticamente. Ejecute "composer lint:fix" manualmente.');
            }
        } else {
            $this->warn('Laravel Pint no está instalado. Ejecute "composer lint:fix" manualmente para formatear el código.');
        }

        // Asegurar que el archivo routes/api.php tenga el formato correcto
        $this->info('Corrigiendo formato de archivos de ruta...');
        if (file_exists(base_path('vendor/bin/phpcbf'))) {
            $apiRoutesPath = base_path('routes/api.php');
            system(base_path('vendor/bin/phpcbf').' --standard=PSR12 '.$apiRoutesPath, $exitCodePhp);
            if ($exitCodePhp <= 1) { // phpcbf returns 1 when fixed, 0 when nothing to fix
                $this->info('Archivos de ruta formateados correctamente.');
            }
        }
    }

    /**
     * Analiza la cadena de campos y devuelve un array estructurado.
     *
     * @param  string|null  $fieldsString  Ejemplo: 'nombre:string,edad:integer'
     */
    protected function parseFields(?string $fieldsString): array
    {
        if (empty($fieldsString)) {
            // Campos por defecto si no se proporcionan
            return [
                'name' => ['type' => 'string', 'nullable' => false, 'default' => null],
                'description' => ['type' => 'text', 'nullable' => true, 'default' => null],
                'status' => ['type' => 'boolean', 'nullable' => false, 'default' => true],
            ];
        }

        $fields = [];
        $fieldsArray = explode(',', $fieldsString);

        foreach ($fieldsArray as $field) {
            $fieldParts = explode(':', $field);

            if (count($fieldParts) < 2) {
                continue;
            }

            $fieldName = trim($fieldParts[0]);
            $fieldType = trim($fieldParts[1]);

            // Procesar opciones adicionales
            $nullable = false;
            $default = null;

            if (count($fieldParts) > 2) {
                $options = array_slice($fieldParts, 2);
                foreach ($options as $option) {
                    if (strpos($option, 'nullable') !== false) {
                        $nullable = true;
                    } elseif (strpos($option, 'default=') !== false) {
                        $default = str_replace('default=', '', $option);
                    }
                }
            }

            $fields[$fieldName] = [
                'type' => $fieldType,
                'nullable' => $nullable,
                'default' => $default,
            ];
        }

        return $fields;
    }

    /**
     * Analiza la cadena de relaciones y devuelve un array estructurado.
     *
     * @param  string|null  $relationsString  Ejemplo: 'belongsTo:User,hasMany:Comment'
     */
    protected function parseRelations(?string $relationsString): array
    {
        if (empty($relationsString)) {
            return [];
        }

        $relations = [];
        $relationsArray = explode(',', $relationsString);

        foreach ($relationsArray as $relation) {
            $relationParts = explode(':', $relation);

            if (count($relationParts) < 2) {
                continue;
            }

            $relationType = trim($relationParts[0]);
            $relatedModel = trim($relationParts[1]);

            $relations[] = [
                'type' => $relationType,
                'model' => $relatedModel,
            ];
        }

        return $relations;
    }

    /**
     * Genera el código para los campos de migración.
     */
    protected function generateMigrationFieldsCode(array $fields): string
    {
        $code = '';
        $indent = '            ';

        foreach ($fields as $name => $field) {
            $line = "{$indent}\$table->{$field['type']}('{$name}')";

            if ($field['nullable']) {
                $line .= '->nullable()';
            }

            if ($field['default'] !== null) {
                $default = $field['default'];

                // Convertir el valor por defecto al tipo adecuado
                if ($field['type'] === 'boolean') {
                    $default = filter_var($default, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                } elseif (in_array($field['type'], ['integer', 'bigInteger', 'smallInteger', 'tinyInteger'])) {
                    $default = (int) $default;
                } elseif (in_array($field['type'], ['float', 'double', 'decimal'])) {
                    $default = (float) $default;
                } elseif ($field['type'] === 'json') {
                    $default = "'".json_encode($default)."'";
                } elseif (in_array($field['type'], ['string', 'text'])) {
                    $default = "'".$default."'";
                }

                $line .= "->default({$default})";
            }

            $line .= ';';
            $code .= $line."\n";
        }

        return $code;
    }

    /**
     * Genera el código para las relaciones del modelo.
     */
    protected function generateModelRelationsCode(array $relations): string
    {
        $code = '';
        $indent = '    ';

        foreach ($relations as $relation) {
            $relationType = $relation['type'];
            $relatedModel = $relation['model'];
            $methodName = '';

            // Determinar el nombre del método según el tipo de relación
            switch ($relationType) {
                case 'belongsTo':
                    $methodName = Str::camel($relatedModel);
                    break;

                case 'hasOne':
                    $methodName = Str::camel($relatedModel);
                    break;

                case 'hasMany':
                    $methodName = Str::camel(Str::plural($relatedModel));
                    break;

                case 'belongsToMany':
                    $methodName = Str::camel(Str::plural($relatedModel));
                    break;

                default:
                    $methodName = Str::camel($relatedModel);
            }

            $code .= "\n{$indent}/**\n";
            $code .= "{$indent} * Relación con {$relatedModel}.\n";
            $code .= "{$indent} *\n";
            $code .= "{$indent} * @return \\Illuminate\\Database\\Eloquent\\Relations\\".ucfirst($relationType)."\n";
            $code .= "{$indent} */\n";
            $code .= "{$indent}public function {$methodName}()\n";
            $code .= "{$indent}{\n";
            $code .= "{$indent}    // @phpstan-ignore-next-line\n";
            $code .= "{$indent}    return \$this->{$relationType}('App\\Models\\{$relatedModel}');\n";
            $code .= "{$indent}}\n";
        }

        return $code;
    }

    /**
     * Genera las reglas de validación para los campos.
     *
     * @param  bool  $isUpdate  Si es para actualización (sometimes)
     */
    protected function generateValidationRules(array $fields, bool $isUpdate = false): string
    {
        $rules = [];
        $indent = '            ';

        foreach ($fields as $name => $field) {
            $fieldRules = [];

            // Añadir 'sometimes' para actualización
            if ($isUpdate) {
                $fieldRules[] = 'sometimes';
            }

            // Regla required a menos que sea nullable
            if (! $field['nullable']) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            // Agregar reglas según el tipo de campo
            switch ($field['type']) {
                case 'string':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:255';
                    break;

                case 'text':
                    $fieldRules[] = 'string';
                    break;

                case 'integer':
                case 'bigInteger':
                case 'smallInteger':
                case 'tinyInteger':
                    $fieldRules[] = 'integer';
                    break;

                case 'float':
                case 'double':
                case 'decimal':
                    $fieldRules[] = 'numeric';
                    break;

                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;

                case 'date':
                    $fieldRules[] = 'date';
                    break;

                case 'json':
                case 'jsonb':
                    $fieldRules[] = 'array';
                    break;

                case 'email':
                    $fieldRules[] = 'email';
                    break;
            }

            $rules[] = "{$indent}'{$name}' => '".implode('|', $fieldRules)."',";
        }

        return implode("\n", $rules);
    }

    /**
     * Genera los campos para el factory.
     */
    protected function generateFactoryFields(array $fields): string
    {
        $result = '';
        $indent = '            ';

        foreach ($fields as $name => $field) {
            $fakerMethod = $this->getFakerMethodForFieldType($name, $field['type']);
            $result .= "{$indent}'{$name}' => {$fakerMethod},\n";
        }

        return $result;
    }

    /**
     * Obtiene el método de faker apropiado para el tipo de campo.
     */
    protected function getFakerMethodForFieldType(string $fieldName, string $fieldType): string
    {
        // Casos especiales por nombre de campo
        if (preg_match('/(email|correo)/i', $fieldName)) {
            return '$this->faker->safeEmail';
        }

        if (preg_match('/(name|nombre)/i', $fieldName)) {
            return '$this->faker->name';
        }

        if (preg_match('/(title|titulo)/i', $fieldName)) {
            return '$this->faker->sentence(3)';
        }

        if (preg_match('/(description|descripcion)/i', $fieldName)) {
            return '$this->faker->paragraph(2)';
        }

        if (preg_match('/(url|link|enlace)/i', $fieldName)) {
            return '$this->faker->url';
        }

        if (preg_match('/(image|imagen|photo|foto)/i', $fieldName)) {
            return '$this->faker->imageUrl(640, 480)';
        }

        if (preg_match('/(price|precio)/i', $fieldName)) {
            return '$this->faker->randomFloat(2, 10, 1000)';
        }

        if (preg_match('/(stock|quantity|cantidad)/i', $fieldName)) {
            return '$this->faker->randomNumber(2)';
        }

        // Por tipo de campo
        switch ($fieldType) {
            case 'string':
                return '$this->faker->word';

            case 'text':
                return '$this->faker->paragraph';

            case 'integer':
            case 'bigInteger':
            case 'smallInteger':
            case 'tinyInteger':
                return '$this->faker->numberBetween(1, 1000)';

            case 'float':
            case 'double':
            case 'decimal':
                return '$this->faker->randomFloat(2, 1, 1000)';

            case 'boolean':
                return '$this->faker->boolean';

            case 'date':
                return '$this->faker->date';

            case 'datetime':
            case 'timestamp':
                return '$this->faker->dateTime';

            case 'json':
                return 'json_encode([$this->faker->word => $this->faker->word])';

            case 'jsonb':
                return 'json_encode([$this->faker->word => $this->faker->word])';

            default:
                return '$this->faker->word';
        }
    }

    /**
     * Genera los tests para la entidad.
     */
    protected function generateTests(string $name, string $prefix, array $fields)
    {
        // Crear directorio de tests si no existe
        $testsDir = base_path('tests/Feature');
        if (! is_dir($testsDir)) {
            mkdir($testsDir, 0755, true);
        }

        // Test para el controlador
        $controllerTestPath = $testsDir."/{$name}ControllerTest.php";

        if (! file_exists($controllerTestPath)) {
            $controllerTestContent = $this->generateControllerTest($name, $prefix, $fields);
            file_put_contents($controllerTestPath, $controllerTestContent);
            $this->info("Test para controlador generado en tests/Feature/{$name}ControllerTest.php");
        }

        // Test para el servicio
        $testsDir = base_path('tests/Unit');
        if (! is_dir($testsDir)) {
            mkdir($testsDir, 0755, true);
        }

        $serviceTestPath = $testsDir."/{$name}ServiceTest.php";

        if (! file_exists($serviceTestPath)) {
            $serviceTestContent = $this->generateServiceTest($name, $fields);
            file_put_contents($serviceTestPath, $serviceTestContent);
            $this->info("Test para servicio generado en tests/Unit/{$name}ServiceTest.php");
        }

        // Test para el repositorio
        $repositoryTestPath = $testsDir."/{$name}RepositoryTest.php";

        if (! file_exists($repositoryTestPath)) {
            $repositoryTestContent = $this->generateRepositoryTest($name, $fields);
            file_put_contents($repositoryTestPath, $repositoryTestContent);
            $this->info("Test para repositorio generado en tests/Unit/{$name}RepositoryTest.php");
        }
    }

    /**
     * Genera el contenido del test para el controlador.
     */
    protected function generateControllerTest(string $name, string $prefix, array $fields): string
    {
        $testData = $this->generateTestData($fields);
        $updateData = $this->generateTestData($fields, true);
        $parameter = Str::snake($name);

        return <<<EOT
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\\{$name};

class {$name}ControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /**
     * Configurar el entorno para las pruebas
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Asegurarse de que las migraciones estén ejecutadas
        \$this->artisan('migrate');

        // Registrar binding para repositorio manualmente
        \$repositoryInterface = 'App\\\\Repositories\\\\Contracts\\\\{$name}RepositoryInterface';
        \$repository = 'App\\\\Repositories\\\\{$name}Repository';
        if (interface_exists(\$repositoryInterface) && class_exists(\$repository)) {
            app()->bind(\$repositoryInterface, \$repository);
        }

        // Registrar rutas directamente para las pruebas si existe el controlador
        \$controller = 'App\\\\Http\\\\Controllers\\\\{$name}Controller';
        if (class_exists(\$controller)) {
            Route::get('/{$prefix}', [\$controller, 'index'])->name('{$prefix}.index');
            Route::post('/{$prefix}', [\$controller, 'store'])->name('{$prefix}.store');
            Route::get('/{$prefix}/{{$parameter}}', [\$controller, 'show'])->name('{$prefix}.show');
            Route::put('/{$prefix}/{{$parameter}}', [\$controller, 'update'])->name('{$prefix}.update');
            Route::delete('/{$prefix}/{{$parameter}}', [\$controller, 'destroy'])->name('{$prefix}.destroy');
        }
    }

    /**
     * Test de listado de {$name}.
     */
    public function test_index(): void
    {
        // Verificar que el modelo exista
        if (!class_exists({$name}::class)) {
            \$this->markTestSkipped('La clase {$name} no existe.');
        }

        // Crear algunos registros con datos válidos
        \$data = {$testData};
        \$model = app({$name}::class);
        \$model::query()->create(\$data);
        \$model::query()->create(\$data);
        \$model::query()->create(\$data);

        // Hacer la petición
        \$response = \$this->getJson('/{$prefix}');

        // Verificar solo que la respuesta sea exitosa
        \$response->assertStatus(200);
    }

    /**
     * Test de creación de {$name}.
     */
    public function test_store(): void
    {
        // Datos para el nuevo registro
        \$data = {$testData};

        // Hacer la petición
        \$response = \$this->postJson('/{$prefix}', \$data);

        // Verificar respuesta exitosa
        \$response->assertStatus(201);

        // Verificar que existe en la base de datos
        \$this->assertDatabaseHas('{$prefix}', \$data);
    }

    /**
     * Test de mostrar un {$name}.
     */
    public function test_show(): void
    {
        // Verificar que el modelo exista
        if (!class_exists({$name}::class)) {
            \$this->markTestSkipped('La clase {$name} no existe.');
        }

        // Crear un registro con datos válidos
        \$data = {$testData};
        \$model = app({$name}::class);
        \${$parameter} = \$model::query()->create(\$data);

        // Hacer la petición
        \$response = \$this->getJson("/{$prefix}/{\${$parameter}->id}");

        // Verificar respuesta exitosa
        \$response->assertStatus(200);
    }

    /**
     * Test de actualización de {$name}.
     */
    public function test_update(): void
    {
        // Verificar que el modelo exista
        if (!class_exists({$name}::class)) {
            \$this->markTestSkipped('La clase {$name} no existe.');
        }

        // Crear un registro con datos válidos
        \$originalData = {$testData};
        \$model = app({$name}::class);
        \${$parameter} = \$model::query()->create(\$originalData);

        // Datos para actualizar
        \$updateData = {$updateData};

        // Hacer la petición
        \$response = \$this->putJson("/{$prefix}/{\${$parameter}->id}", \$updateData);

        // Verificar respuesta
        \$response->assertStatus(200);

        // Recargar el modelo desde la base de datos
        \${$parameter}->refresh();

        // Verificar que se actualizó en la base de datos
        foreach (\$updateData as \$key => \$value) {
            \$this->assertEquals(\$value, \${$parameter}->\$key);
        }
    }

    /**
     * Test de eliminación de {$name}.
     */
    public function test_destroy(): void
    {
        // Verificar que el modelo exista
        if (!class_exists({$name}::class)) {
            \$this->markTestSkipped('La clase {$name} no existe.');
        }

        // Crear un registro con datos válidos
        \$data = {$testData};
        \$model = app({$name}::class);
        \${$parameter} = \$model::query()->create(\$data);
        \$id = \${$parameter}->id;

        // Hacer la petición
        \$response = \$this->deleteJson("/{$prefix}/{\${$parameter}->id}");

        // Verificar respuesta
        \$response->assertStatus(200);
        \$response->assertJson(['message' => '{$name} eliminado']);

        // Verificar que no existe en la base de datos comprobando si podemos encontrarlo
        \$this->assertNull(\$model::query()->find(\$id));
    }
}
EOT;
    }

    /**
     * Genera el contenido del test para el servicio.
     */
    protected function generateServiceTest(string $name, array $fields): string
    {
        $lowercaseName = strtolower($name);
        $prefix = Str::plural($lowercaseName);
        $testData = $this->generateTestData($fields);
        $updateData = $this->generateTestData($fields, true);

        return <<<EOT
<?php

namespace Tests\Unit;

use App\Models\\{$name};
use App\Repositories\\{$name}Repository;
use App\Repositories\Contracts\\{$name}RepositoryInterface;
use App\Services\\{$name}Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class {$name}ServiceTest extends TestCase
{
    use RefreshDatabase;

    private {$name}Service \$service;

    protected function setUp(): void
    {
        parent::setUp();

        // Asegurarse de que las migraciones estén ejecutadas
        \$this->artisan('migrate');

        // Registrar binding manualmente para las pruebas
        app()->bind({$name}RepositoryInterface::class, {$name}Repository::class);

        \$repository = app({$name}RepositoryInterface::class);
        \$this->service = new {$name}Service(\$repository);
    }

    /**
     * Test obtener todos los {$lowercaseName}.
     */
    public function test_get_all(): void
    {
        // Crear algunos registros con datos válidos
        \$data = {$testData};

        // Usar el método query() para acceder a los métodos estáticos
        {$name}::query()->create(\$data);
        {$name}::query()->create(\$data);
        {$name}::query()->create(\$data);

        // Obtener todos
        \$result = \$this->service->getAll();

        // Verificar resultado
        \$this->assertCount(3, \$result);
    }

    /**
     * Test buscar por ID.
     */
    public function test_find_by_id(): void
    {
        // Crear un registro con datos válidos
        \$data = {$testData};

        \${$lowercaseName} = {$name}::query()->create(\$data);

        // Buscar por ID
        \$result = \$this->service->findById(\${$lowercaseName}->id);

        // Verificar resultado
        \$this->assertEquals(\${$lowercaseName}->id, \$result->id);
    }

    /**
     * Test crear.
     */
    public function test_create(): void
    {
        // Datos para crear
        \$data = {$testData};

        // Crear
        \$result = \$this->service->create(\$data);

        // Verificar resultado
        \$this->assertInstanceOf({$name}::class, \$result);
        \$this->assertDatabaseHas('{$prefix}', \$data);
    }

    /**
     * Test actualizar.
     */
    public function test_update(): void
    {
        // Crear un registro con datos válidos
        \$originalData = {$testData};

        \${$lowercaseName} = {$name}::query()->create(\$originalData);

        // Datos para actualizar
        \$updateData = {$updateData};

        // Actualizar
        \$result = \$this->service->update(\${$lowercaseName}->id, \$updateData);

        // Verificar resultado
        \$this->assertInstanceOf({$name}::class, \$result);

        // Verificar que se actualizó en la base de datos
        foreach (\$updateData as \$key => \$value) {
            \$this->assertDatabaseHas('{$prefix}', [
                'id' => \${$lowercaseName}->id,
                \$key => \$value,
            ]);
        }
    }

    /**
     * Test eliminar.
     */
    public function test_delete(): void
    {
        // Crear un registro con datos válidos
        \$data = {$testData};

        \${$lowercaseName} = {$name}::query()->create(\$data);

        // Eliminar
        \$this->service->delete(\${$lowercaseName}->id);

        // Verificar que se eliminó
        \$this->assertDatabaseMissing('{$prefix}', ['id' => \${$lowercaseName}->id]);
    }
}
EOT;
    }

    /**
     * Genera el contenido del test para el repositorio.
     */
    protected function generateRepositoryTest(string $name, array $fields): string
    {
        $lowercaseName = strtolower($name);
        $prefix = Str::plural($lowercaseName);
        $testData = $this->generateTestData($fields);
        $updateData = $this->generateTestData($fields, true);

        return <<<EOT
<?php

namespace Tests\Unit;

use App\Models\\{$name};
use App\Repositories\\{$name}Repository;
use App\Repositories\Contracts\\{$name}RepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class {$name}RepositoryTest extends TestCase
{
    use RefreshDatabase;

    private {$name}Repository \$repository;

    protected function setUp(): void
    {
        parent::setUp();

        // Asegurarse de que las migraciones estén ejecutadas
        \$this->artisan('migrate');

        // Registrar binding manualmente para las pruebas
        app()->bind({$name}RepositoryInterface::class, {$name}Repository::class);

        \$this->repository = app({$name}Repository::class);
    }

    /**
     * Test obtener todos los {$lowercaseName}.
     */
    public function test_get_all(): void
    {
        // Crear algunos registros con datos válidos
        \$data = {$testData};

        // Usar el método estático create con seguridad
        {$name}::query()->create(\$data);
        {$name}::query()->create(\$data);
        {$name}::query()->create(\$data);

        // Obtener todos
        \$result = \$this->repository->getAll();

        // Verificar resultado
        \$this->assertCount(3, \$result);
    }

    /**
     * Test buscar por ID.
     */
    public function test_find_by_id(): void
    {
        // Crear un registro con datos válidos
        \$data = {$testData};

        \${$lowercaseName} = {$name}::query()->create(\$data);

        // Buscar por ID
        \$result = \$this->repository->findById(\${$lowercaseName}->id);

        // Verificar resultado
        \$this->assertEquals(\${$lowercaseName}->id, \$result->id);
    }

    /**
     * Test crear.
     */
    public function test_create(): void
    {
        // Datos para crear
        \$data = {$testData};

        // Crear
        \$result = \$this->repository->create(\$data);

        // Verificar resultado
        \$this->assertInstanceOf({$name}::class, \$result);
        \$this->assertDatabaseHas('{$prefix}', \$data);
    }

    /**
     * Test actualizar.
     */
    public function test_update(): void
    {
        // Crear un registro con datos válidos
        \$originalData = {$testData};

        \${$lowercaseName} = {$name}::query()->create(\$originalData);

        // Datos para actualizar
        \$updateData = {$updateData};

        // Actualizar
        \$result = \$this->repository->update(\${$lowercaseName}->id, \$updateData);

        // Verificar resultado
        \$this->assertInstanceOf({$name}::class, \$result);

        // Verificar que se actualizó en la base de datos
        foreach (\$updateData as \$key => \$value) {
            \$this->assertDatabaseHas('{$prefix}', [
                'id' => \${$lowercaseName}->id,
                \$key => \$value,
            ]);
        }
    }

    /**
     * Test eliminar.
     */
    public function test_delete(): void
    {
        // Crear un registro con datos válidos
        \$data = {$testData};

        \${$lowercaseName} = {$name}::query()->create(\$data);

        // Eliminar
        \$this->repository->delete(\${$lowercaseName}->id);

        // Verificar que se eliminó
        \$this->assertDatabaseMissing('{$prefix}', ['id' => \${$lowercaseName}->id]);
    }
}
EOT;
    }

    /**
     * Genera datos de prueba para los tests
     *
     * @param  array  $fields  Campos del modelo
     * @param  bool  $isUpdate  Indica si es para una prueba de actualización
     * @return string Cadena formateada con los datos de prueba
     */
    protected function generateTestData(array $fields, bool $isUpdate = false): string
    {
        $testData = [];

        foreach ($fields as $fieldName => $field) {
            // Excluir campos especiales o generados automáticamente
            if (in_array($fieldName, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $value = $this->getTestValueForType($field['type'], $field['name'] ?? $fieldName, $isUpdate);

            // Agregar el valor solo si no es nulo o si el campo no puede ser nulo
            if ($value !== null || (isset($field['nullable']) && ! $field['nullable'])) {
                $testData[] = "'$fieldName' => $value";
            }
        }

        return empty($testData)
            ? '[]'
            : "[\n            ".implode(",\n            ", $testData)."\n        ]";
    }

    /**
     * Obtiene un valor de prueba según el tipo de campo
     *
     * @param  string  $type  Tipo de campo en la base de datos
     * @param  string  $fieldName  Nombre del campo
     * @param  bool  $isUpdate  Indica si es para una prueba de actualización
     * @return string|null Valor formateado para pruebas
     */
    protected function getTestValueForType(string $type, string $fieldName, bool $isUpdate = false): ?string
    {
        $prefix = $isUpdate ? 'updated_' : '';

        switch (strtolower($type)) {
            case 'string':
                // Intentar detectar algunos campos comunes y generar valores apropiados
                if (preg_match('/(email|correo)/i', $fieldName)) {
                    return "'".$prefix."test@example.com'";
                } elseif (preg_match('/(name|nombre|titulo|title)/i', $fieldName)) {
                    return "'".$prefix."Test Name'";
                } elseif (preg_match('/(password|contraseña|clave)/i', $fieldName)) {
                    return "bcrypt('password')";
                } elseif (preg_match('/(description|descripcion|detalle|detail)/i', $fieldName)) {
                    return "'".$prefix."Test Description'";
                } elseif (preg_match('/(address|direccion)/i', $fieldName)) {
                    return "'".$prefix."123 Test Street'";
                } elseif (preg_match('/(phone|telefono|celular|mobile)/i', $fieldName)) {
                    return "'555-".rand(1000, 9999)."'";
                } elseif (preg_match('/(url|link|enlace)/i', $fieldName)) {
                    return "'https://example.com/".$prefix."test'";
                } elseif (preg_match('/(code|codigo)/i', $fieldName)) {
                    return "'".$prefix.strtoupper(substr(md5((string) rand()), 0, 8))."'";
                } else {
                    return "'".$prefix.'test_'.$fieldName."'";
                }

            case 'text':
            case 'longtext':
            case 'mediumtext':
                return "'".$prefix.'This is a test text for '.$fieldName."'";

            case 'integer':
            case 'biginteger':
            case 'smallinteger':
            case 'mediuminteger':
            case 'tinyinteger':
            case 'int':
            case 'bigint':
            case 'smallint':
            case 'mediumint':
                // Detectar campos especiales por nombre
                if (preg_match('/(age|edad)/i', $fieldName)) {
                    return (string) ($isUpdate ? rand(25, 65) : rand(18, 45));
                } elseif (preg_match('/(year|año)/i', $fieldName)) {
                    return (string) ($isUpdate ? date('Y') + 1 : date('Y'));
                } elseif (preg_match('/(status|estado)/i', $fieldName)) {
                    return (string) ($isUpdate ? 2 : 1);
                } elseif (preg_match('/(quantity|cantidad|stock)/i', $fieldName)) {
                    return (string) ($isUpdate ? rand(50, 100) : rand(1, 49));
                } elseif (preg_match('/order/i', $fieldName)) {
                    return (string) ($isUpdate ? 2 : 1);
                } else {
                    return (string) ($isUpdate ? rand(1000, 9999) : rand(100, 999));
                }

            case 'decimal':
            case 'double':
            case 'float':
                // Detectar campos especiales por nombre
                if (preg_match('/(price|precio|cost|costo)/i', $fieldName)) {
                    return number_format(($isUpdate ? rand(100, 1000) : rand(10, 99)) + (rand(0, 99) / 100), 2, '.', '');
                } elseif (preg_match('/(rate|tasa|percentage|porcentaje)/i', $fieldName)) {
                    return number_format(($isUpdate ? rand(5, 20) : rand(1, 5)) + (rand(0, 99) / 100), 2, '.', '');
                } else {
                    return number_format(($isUpdate ? rand(1000, 9999) : rand(100, 999)) / 100, 2, '.', '');
                }

            case 'boolean':
            case 'bool':
                return $isUpdate ? 'false' : 'true';

            case 'date':
                return "'".date('Y-m-d', strtotime(($isUpdate ? '+1 week' : 'now')))."'";

            case 'datetime':
            case 'timestamp':
                return "'".date('Y-m-d H:i:s', strtotime(($isUpdate ? '+1 week' : 'now')))."'";

            case 'time':
                return "'".date('H:i:s', strtotime(($isUpdate ? '+1 hour' : 'now')))."'";

            case 'year':
                return (string) ($isUpdate ? (date('Y') + 1) : date('Y'));

            case 'json':
            case 'jsonb':
                if (preg_match('/(options|opciones|settings|config)/i', $fieldName)) {
                    return "json_encode(['enabled' => ".($isUpdate ? 'false' : 'true').", 'value' => '".($isUpdate ? 'updated' : 'default')."'])";
                } else {
                    return "json_encode(['test' => '".($isUpdate ? 'updated' : 'value')."'])";
                }

            case 'uuid':
                return '$this->faker->uuid()';

            case 'ipaddress':
                return "'".long2ip(rand(0, 4294967295))."'";

            case 'macaddress':
                $mac = [];
                for ($i = 0; $i < 6; $i++) {
                    $mac[] = sprintf('%02X', rand(0, 255));
                }

                return "'".implode(':', $mac)."'";

            case 'enum':
                // Para enumeraciones, intentar detectar tipos comunes
                if (preg_match('/(status|estado)/i', $fieldName)) {
                    return "'".($isUpdate ? 'inactive' : 'active')."'";
                } elseif (preg_match('/(gender|genero)/i', $fieldName)) {
                    return "'".($isUpdate ? 'female' : 'male')."'";
                } elseif (preg_match('/(type|tipo)/i', $fieldName)) {
                    return "'".($isUpdate ? 'secondary' : 'primary')."'";
                } else {
                    return "'option".($isUpdate ? '2' : '1')."'";
                }

            case 'foreignid':
            case 'foreign':
            case 'foreignkey':
                // Para claves foráneas, intentar generar un ID realista
                return (string) ($isUpdate ? rand(2, 5) : 1);

            default:
                // Para tipos desconocidos, devolver un string genérico
                return "'$prefix"."test_value'";
        }
    }

    /**
     * Obtiene el tipo de dato PHP para PHPDoc basado en el tipo de campo.
     *
     * @param  string  $fieldType  Tipo de campo en la base de datos
     * @param  bool  $nullable  Si el campo puede ser nulo
     * @return string Tipo PHP para PHPDoc
     */
    protected function getPhpDocTypeForField(string $fieldType, bool $nullable = false): string
    {
        $phpType = '';

        switch (strtolower($fieldType)) {
            case 'string':
            case 'text':
            case 'longtext':
            case 'mediumtext':
            case 'enum':
            case 'json':
            case 'jsonb':
            case 'date':
            case 'datetime':
            case 'timestamp':
            case 'time':
            case 'year':
            case 'ipaddress':
            case 'macaddress':
            case 'uuid':
                $phpType = 'string';
                break;

            case 'integer':
            case 'biginteger':
            case 'smallinteger':
            case 'mediuminteger':
            case 'tinyinteger':
            case 'int':
            case 'bigint':
            case 'smallint':
            case 'mediumint':
                $phpType = 'int';
                break;

            case 'float':
            case 'double':
            case 'decimal':
                $phpType = 'float';
                break;

            case 'boolean':
            case 'bool':
                $phpType = 'bool';
                break;

            default:
                $phpType = 'mixed';
        }

        return $nullable ? "{$phpType}|null" : $phpType;
    }
}
