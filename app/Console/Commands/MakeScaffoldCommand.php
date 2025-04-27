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
        if (!$noMigration) {
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
        if (!$noMigration) {
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
        if (!file_exists($modelPath)) {
            // En algunos proyectos el modelo se crea en la raíz de app/
            $modelPath = app_path("{$name}.php");
        }

        if (file_exists($modelPath)) {
            $modelContent = file_get_contents($modelPath);

            // Generar array de campos fillable
            $fillableFields = array_keys($fields);
            $fillableString = "'" . implode("', '", $fillableFields) . "'";

            if (strpos($modelContent, 'protected $fillable') === false) {
                $modelContent = preg_replace(
                    '/(class\s+' . $name . '\s+extends\s+\S+\s*\{)/',
                    "$1\n    protected \$fillable = [{$fillableString}];",
                    $modelContent
                );

                // Añadir métodos de relaciones si existen
                if (!empty($relations)) {
                    $relationsCode = $this->generateModelRelationsCode($relations);
                    $modelContent = str_replace('}', $relationsCode . "\n}", $modelContent);
                }

                file_put_contents($modelPath, $modelContent);
                $this->info("Modelo {$name} actualizado con fillable" . (!empty($relations) ? " y relaciones" : "") . ".");
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
            $this->info("API Resource {$resourceClass} generado exitosamente.");
        }

        $controllerPath = app_path("Http/Controllers/{$controllerName}.php");

        // Preparar las importaciones según las opciones
        $imports = [
            "use App\Services\\{$name}Service;",
            "use App\Http\Requests\\{$name}\\Store{$name}Request;",
            "use App\Http\Requests\\{$name}\\Update{$name}Request;"
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

        if (!is_dir(app_path('Repositories/Contracts'))) {
            mkdir(app_path('Repositories/Contracts'), 0755, true);
        }
        if (!is_dir(app_path('Repositories'))) {
            mkdir(app_path('Repositories'), 0755, true);
        }
        if (!is_dir(app_path('Services'))) {
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
        return {$name}::all();
    }

    public function findById(int \$id)
    {
        return {$name}::findOrFail(\$id);
    }

    public function create(array \$data)
    {
        return {$name}::create(\$data);
    }

    public function update(int \$id, array \$data)
    {
        \$model = {$name}::findOrFail(\$id);
        \$model->update(\$data);
        return \$model;
    }

    public function delete(int \$id): void
    {
        \$model = {$name}::findOrFail(\$id);
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
        $providerPath = app_path("Providers/AppServiceProvider.php");
        $providerContent = file_get_contents($providerPath);
        $binding = "\$this->app->bind(\\App\\Repositories\\Contracts\\{$name}RepositoryInterface::class, \\App\\Repositories\\{$name}Repository::class);";
        if (strpos($providerContent, $binding) === false) {
            $providerContent = preg_replace(
                '/(public function register\(\): void\s*\{\s*)/',
                "$1\n        {$binding}\n",
                $providerContent
            );
            file_put_contents($providerPath, $providerContent);
            $this->info("Se ha registrado la vinculación en AppServiceProvider.");
        } else {
            $this->info("La vinculación ya existe en AppServiceProvider.");
        }

        // 5. Generar el archivo de rutas genérico en routes/api y actualizar routes/api.php.
        $apiDir = base_path('routes/api');
        if (!is_dir($apiDir)) {
            mkdir($apiDir, 0755, true);
        }
        $routesFilePath = $apiDir . '/' . $prefix . '.php';
        if (file_exists($routesFilePath)) {
            $this->error("El archivo de rutas {$routesFilePath} ya existe. No se sobrescribirá.");
        } else {
            $routesContent = <<<EOT
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\\{$name}Controller;

Route::group(['prefix' => '{$prefix}'], function () {
    Route::get('/', [{$name}Controller::class, 'index'])->name('{$prefix}.index');
    Route::post('/', [{$name}Controller::class, 'store'])->name('{$prefix}.store');
    Route::get('/{PARAM}', [{$name}Controller::class, 'show'])->name('{$prefix}.show');
    Route::put('/{PARAM}', [{$name}Controller::class, 'update'])->name('{$prefix}.update');
    Route::delete('/{PARAM}', [{$name}Controller::class, 'destroy'])->name('{$prefix}.destroy');
});
EOT;
            $routesContent = str_replace('PARAM', $parameter, $routesContent);
            file_put_contents($routesFilePath, $routesContent);
            $this->info("Archivo de rutas generado en routes/api/{$prefix}.php");
        }
        $mainRoutesPath = base_path('routes/api.php');
        $mainRoutesContent = file_get_contents($mainRoutesPath);
        $requireLine = "require __DIR__ . '/api/{$prefix}.php';";
        if (strpos($mainRoutesContent, $requireLine) === false) {
            file_put_contents($mainRoutesPath, "\n" . $requireLine, FILE_APPEND);
            $this->info("Se ha actualizado routes/api.php para incluir {$prefix}.php.");
        }

        // 6. Crear ApiFormRequest si no existe.
        $apiFormRequestPath = app_path("Http/Requests/ApiFormRequest.php");
        if (!file_exists($apiFormRequestPath)) {
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
            $this->info("ApiFormRequest creado en app/Http/Requests/ApiFormRequest.php");
        } else {
            $this->info("ApiFormRequest ya existe.");
        }

        // 7. Crear las requests Store y Update para la entidad basadas en los campos.
        $requestDir = app_path("Http/Requests/{$name}");
        if (!is_dir($requestDir)) {
            mkdir($requestDir, 0755, true);
        }
        $storeRequestPath = $requestDir . "/Store{$name}Request.php";
        $updateRequestPath = $requestDir . "/Update{$name}Request.php";

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
        if ($withFactory && !file_exists(database_path("factories/{$name}Factory.php"))) {
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
                        "use Illuminate\\Database\\Seeder;",
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
    }

    /**
     * Analiza la cadena de campos y devuelve un array estructurado.
     *
     * @param string|null $fieldsString Ejemplo: 'nombre:string,edad:integer'
     * @return array
     */
    protected function parseFields(?string $fieldsString): array
    {
        if (empty($fieldsString)) {
            // Campos por defecto si no se proporcionan
            return [
                'name' => ['type' => 'string', 'nullable' => false, 'default' => null],
                'description' => ['type' => 'text', 'nullable' => true, 'default' => null],
                'status' => ['type' => 'boolean', 'nullable' => false, 'default' => true]
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
                'default' => $default
            ];
        }

        return $fields;
    }

    /**
     * Analiza la cadena de relaciones y devuelve un array estructurado.
     *
     * @param string|null $relationsString Ejemplo: 'belongsTo:User,hasMany:Comment'
     * @return array
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
                'model' => $relatedModel
            ];
        }

        return $relations;
    }

    /**
     * Genera el código para los campos de migración.
     *
     * @param array $fields
     * @return string
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
                    $default = "'" . json_encode($default) . "'";
                } elseif (in_array($field['type'], ['string', 'text'])) {
                    $default = "'" . $default . "'";
                }

                $line .= "->default({$default})";
            }

            $line .= ';';
            $code .= $line . "\n";
        }

        return $code;
    }

    /**
     * Genera el código para las relaciones del modelo.
     *
     * @param array $relations
     * @return string
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
            $code .= "{$indent} */\n";
            $code .= "{$indent}public function {$methodName}()\n";
            $code .= "{$indent}{\n";
            $code .= "{$indent}    return \$this->{$relationType}(\\App\\Models\\{$relatedModel}::class);\n";
            $code .= "{$indent}}\n";
        }

        return $code;
    }

    /**
     * Genera las reglas de validación para los campos.
     *
     * @param array $fields
     * @param bool $isUpdate Si es para actualización (sometimes)
     * @return string
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
            if (!$field['nullable']) {
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
                    $fieldRules[] = 'array';
                    break;

                case 'email':
                    $fieldRules[] = 'email';
                    break;
            }

            $rules[] = "{$indent}'{$name}' => '" . implode('|', $fieldRules) . "',";
        }

        return implode("\n", $rules);
    }

    /**
     * Genera los campos para el factory.
     *
     * @param array $fields
     * @return string
     */
    protected function generateFactoryFields(array $fields): string
    {
        $result = '';
        $indent = '            ';

        foreach ($fields as $name => $field) {
            $fakerMethod = $this->getFakerMethodForFieldType($name, $field['type']);
            $result .= "{$indent}'{$name}' => \$this->{$fakerMethod},\n";
        }

        return $result;
    }

    /**
     * Obtiene el método de faker apropiado para el tipo de campo.
     *
     * @param string $fieldName
     * @param string $fieldType
     * @return string
     */
    protected function getFakerMethodForFieldType(string $fieldName, string $fieldType): string
    {
        // Casos especiales por nombre de campo
        if (str_contains($fieldName, 'email')) {
            return 'faker->safeEmail()';
        }

        if (str_contains($fieldName, 'name')) {
            return 'faker->name()';
        }

        if (str_contains($fieldName, 'title')) {
            return 'faker->sentence()';
        }

        if (str_contains($fieldName, 'description')) {
            return 'faker->paragraph()';
        }

        if (str_contains($fieldName, 'url') || str_contains($fieldName, 'website')) {
            return 'faker->url()';
        }

        if (str_contains($fieldName, 'image') || str_contains($fieldName, 'photo')) {
            return 'faker->imageUrl()';
        }

        // Por tipo de campo
        switch ($fieldType) {
            case 'string':
                return 'faker->word()';

            case 'text':
                return 'faker->paragraph()';

            case 'integer':
            case 'bigInteger':
            case 'smallInteger':
            case 'tinyInteger':
                return 'faker->numberBetween(1, 1000)';

            case 'float':
            case 'double':
            case 'decimal':
                return 'faker->randomFloat(2, 1, 1000)';

            case 'boolean':
                return 'faker->boolean()';

            case 'date':
                return 'faker->date()';

            case 'datetime':
            case 'timestamp':
                return 'faker->dateTime()';

            case 'json':
                return 'faker->words(3, true)';

            default:
                return 'faker->word()';
        }
    }

    /**
     * Genera tests para el controlador, servicio y repositorio.
     *
     * @param string $name
     * @param string $prefix
     * @param array $fields
     * @return void
     */
    protected function generateTests(string $name, string $prefix, array $fields): void
    {
        // Crear directorio de tests si no existe
        $testsDir = base_path('tests/Feature');
        if (!is_dir($testsDir)) {
            mkdir($testsDir, 0755, true);
        }

        // Test para el controlador
        $controllerTestPath = $testsDir . "/{$name}ControllerTest.php";

        if (!file_exists($controllerTestPath)) {
            $controllerTestContent = $this->generateControllerTest($name, $prefix, $fields);
            file_put_contents($controllerTestPath, $controllerTestContent);
            $this->info("Test para controlador generado en tests/Feature/{$name}ControllerTest.php");
        }

        // Test para el servicio
        $testsDir = base_path('tests/Unit');
        if (!is_dir($testsDir)) {
            mkdir($testsDir, 0755, true);
        }

        $serviceTestPath = $testsDir . "/{$name}ServiceTest.php";

        if (!file_exists($serviceTestPath)) {
            $serviceTestContent = $this->generateServiceTest($name, $fields);
            file_put_contents($serviceTestPath, $serviceTestContent);
            $this->info("Test para servicio generado en tests/Unit/{$name}ServiceTest.php");
        }

        // Test para el repositorio
        $repositoryTestPath = $testsDir . "/{$name}RepositoryTest.php";

        if (!file_exists($repositoryTestPath)) {
            $repositoryTestContent = $this->generateRepositoryTest($name, $fields);
            file_put_contents($repositoryTestPath, $repositoryTestContent);
            $this->info("Test para repositorio generado en tests/Unit/{$name}RepositoryTest.php");
        }
    }

    /**
     * Genera el contenido del test para el controlador.
     *
     * @param string $name
     * @param string $prefix
     * @param array $fields
     * @return string
     */
    protected function generateControllerTest(string $name, string $prefix, array $fields): string
    {
        $testData = $this->generateTestData($fields);
        $updateData = $this->generateTestData($fields, true);
        $assertContent = $this->generateAssertContent($fields);

        return <<<EOT
<?php

namespace Tests\Feature;

use App\Models\\{$name};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class {$name}ControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test de listado de {$name}.
     */
    public function test_index(): void
    {
        // Crear algunos registros
        {$name}::factory(3)->create();

        // Hacer la petición
        \$response = \$this->getJson('/{$prefix}');

        // Verificar respuesta
        \$response->assertStatus(200);
        \$response->assertJsonCount(3);
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

        // Verificar respuesta
        \$response->assertStatus(201);
        {$assertContent}

        // Verificar que existe en la base de datos
        \$this->assertDatabaseHas('{$prefix}', \$data);
    }

    /**
     * Test de mostrar un {$name}.
     */
    public function test_show(): void
    {
        // Crear un registro
        \${$name} = {$name}::factory()->create();

        // Hacer la petición
        \$response = \$this->getJson("/{$prefix}/{\${$name}->id}");

        // Verificar respuesta
        \$response->assertStatus(200);
        \$response->assertJson([
            'id' => \${$name}->id,
        ]);
    }

    /**
     * Test de actualización de {$name}.
     */
    public function test_update(): void
    {
        // Crear un registro
        \${$name} = {$name}::factory()->create();

        // Datos para actualizar
        \$data = {$updateData};

        // Hacer la petición
        \$response = \$this->putJson("/{$prefix}/{\${$name}->id}", \$data);

        // Verificar respuesta
        \$response->assertStatus(200);
        {$assertContent}

        // Verificar que se actualizó en la base de datos
        \$this->assertDatabaseHas('{$prefix}', [
            'id' => \${$name}->id,
        ] + \$data);
    }

    /**
     * Test de eliminación de {$name}.
     */
    public function test_destroy(): void
    {
        // Crear un registro
        \${$name} = {$name}::factory()->create();

        // Hacer la petición
        \$response = \$this->deleteJson("/{$prefix}/{\${$name}->id}");

        // Verificar respuesta
        \$response->assertStatus(200);
        \$response->assertJson(['message' => '{$name} eliminado']);

        // Verificar que no existe en la base de datos
        \$this->assertDatabaseMissing('{$prefix}', ['id' => \${$name}->id]);
    }
}
EOT;
    }

    /**
     * Genera el contenido del test para el servicio.
     *
     * @param string $name
     * @param array $fields
     * @return string
     */
    protected function generateServiceTest(string $name, array $fields): string
    {
        $lowercaseName = strtolower($name);
        $testData = $this->generateTestData($fields);
        $updateData = $this->generateTestData($fields, true);

        return <<<EOT
<?php

namespace Tests\Unit;

use App\Models\\{$name};
use App\Repositories\\{$name}Repository;
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
        \$repository = new {$name}Repository();
        \$this->service = new {$name}Service(\$repository);
    }

    /**
     * Test obtener todos los {$lowercaseName}.
     */
    public function test_get_all(): void
    {
        // Crear algunos registros
        {$name}::factory(3)->create();

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
        // Crear un registro
        \${$lowercaseName} = {$name}::factory()->create();

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
        \$this->assertDatabaseHas('{$lowercaseName}s', \$data);
    }

    /**
     * Test actualizar.
     */
    public function test_update(): void
    {
        // Crear un registro
        \${$lowercaseName} = {$name}::factory()->create();

        // Datos para actualizar
        \$data = {$updateData};

        // Actualizar
        \$result = \$this->service->update(\${$lowercaseName}->id, \$data);

        // Verificar resultado
        \$this->assertInstanceOf({$name}::class, \$result);
        \$this->assertDatabaseHas('{$lowercaseName}s', [
            'id' => \${$lowercaseName}->id,
        ] + \$data);
    }

    /**
     * Test eliminar.
     */
    public function test_delete(): void
    {
        // Crear un registro
        \${$lowercaseName} = {$name}::factory()->create();

        // Eliminar
        \$this->service->delete(\${$lowercaseName}->id);

        // Verificar que se eliminó
        \$this->assertDatabaseMissing('{$lowercaseName}s', ['id' => \${$lowercaseName}->id]);
    }
}
EOT;
    }

    /**
     * Genera el contenido del test para el repositorio.
     *
     * @param string $name
     * @param array $fields
     * @return string
     */
    protected function generateRepositoryTest(string $name, array $fields): string
    {
        $lowercaseName = strtolower($name);
        $testData = $this->generateTestData($fields);
        $updateData = $this->generateTestData($fields, true);

        return <<<EOT
<?php

namespace Tests\Unit;

use App\Models\\{$name};
use App\Repositories\\{$name}Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class {$name}RepositoryTest extends TestCase
{
    use RefreshDatabase;

    private {$name}Repository \$repository;

    protected function setUp(): void
    {
        parent::setUp();
        \$this->repository = new {$name}Repository();
    }

    /**
     * Test obtener todos los {$lowercaseName}.
     */
    public function test_get_all(): void
    {
        // Crear algunos registros
        {$name}::factory(3)->create();

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
        // Crear un registro
        \${$lowercaseName} = {$name}::factory()->create();

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
        \$this->assertDatabaseHas('{$lowercaseName}s', \$data);
    }

    /**
     * Test actualizar.
     */
    public function test_update(): void
    {
        // Crear un registro
        \${$lowercaseName} = {$name}::factory()->create();

        // Datos para actualizar
        \$data = {$updateData};

        // Actualizar
        \$result = \$this->repository->update(\${$lowercaseName}->id, \$data);

        // Verificar resultado
        \$this->assertInstanceOf({$name}::class, \$result);
        \$this->assertDatabaseHas('{$lowercaseName}s', [
            'id' => \${$lowercaseName}->id,
        ] + \$data);
    }

    /**
     * Test eliminar.
     */
    public function test_delete(): void
    {
        // Crear un registro
        \${$lowercaseName} = {$name}::factory()->create();

        // Eliminar
        \$this->repository->delete(\${$lowercaseName}->id);

        // Verificar que se eliminó
        \$this->assertDatabaseMissing('{$lowercaseName}s', ['id' => \${$lowercaseName}->id]);
    }
}
EOT;
    }

    /**
     * Genera datos de prueba para los tests.
     *
     * @param array $fields
     * @param bool $isUpdate Si es para actualización
     * @return string
     */
    protected function generateTestData(array $fields, bool $isUpdate = false): string
    {
        $data = [];

        foreach ($fields as $name => $field) {
            // Omitir algunos campos para la actualización
            if ($isUpdate && ($name === 'id' || mt_rand(0, 1) === 0)) {
                continue;
            }

            $value = $this->getTestValueForField($name, $field['type']);
            $data[] = "'{$name}' => {$value}";
        }

        return '[' . implode(', ', $data) . ']';
    }

    /**
     * Obtiene un valor de prueba para un campo según su tipo.
     *
     * @param string $fieldName
     * @param string $fieldType
     * @return string
     */
    protected function getTestValueForField(string $fieldName, string $fieldType): string
    {
        switch ($fieldType) {
            case 'string':
            case 'text':
                return "'{$fieldName}_test'";

            case 'integer':
            case 'bigInteger':
            case 'smallInteger':
            case 'tinyInteger':
                return "1";

            case 'float':
            case 'double':
            case 'decimal':
                return "1.5";

            case 'boolean':
                return "true";

            case 'date':
                return "'2023-01-01'";

            case 'json':
                return "json_encode(['test' => 'data'])";

            default:
                return "'{$fieldName}_value'";
        }
    }

    /**
     * Genera el código para las aserciones en los tests.
     *
     * @param array $fields
     * @return string
     */
    protected function generateAssertContent(array $fields): string
    {
        $assertions = [];
        $count = 0;

        foreach ($fields as $name => $field) {
            if ($count++ >= 3) {
                break; // Limitar a 3 aserciones para no sobrecargar
            }

            $assertions[] = "\$response->assertJsonPath('{$name}', \$data['{$name}']);";
        }

        return implode("\n        ", $assertions);
    }
}
