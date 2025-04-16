<?php

namespace AdminCrud\CrudGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ApiCrud extends Command
{
    protected $signature   = 'make:api-crud {name}';
    protected $description = 'Generate API CRUD based on an existing model with controller, requests, and resource in model-specific Api subdirectories';

    protected $enumFields = [];

    public function handle()
    {
        $name = $this->argument('name');

        $modelPath = app_path("Models/{$name}.php");
        if (! File::exists($modelPath)) {
            $this->error("Model {$name} does not exist!");
            return;
        }

        $fields = $this->getFillableFields($name);
        if (empty($fields)) {
            $this->error("No fillable fields found in {$name} model!");
            return;
        }

        $this->createResource($name, $fields);
        $this->createRequestFiles($name, $fields);
        $this->createController($name, $fields);
        $this->updateRoutes($name);

        $this->info("API CRUD for {$name} successfully generated!");
    }

    protected function getFillableFields($name)
    {
        $modelClass = "App\\Models\\{$name}";
        if (! class_exists($modelClass)) {
            return [];
        }

        $model = new $modelClass();
        return $model->getFillable();
    }

    protected function createResource($name, $fields)
    {
        $resourcePath = app_path("Http/Resources/Api/{$name}");
        if (! File::exists($resourcePath)) {
            File::makeDirectory($resourcePath, 0755, true);
        }

        $resourceFields = '';
        foreach ($fields as $field) {
            $resourceFields .= "            '{$field}' => \$this->{$field},\n";
        }

        $resourceTemplate = <<<EOT
<?php

namespace App\Http\Resources\Api\\{$name};

use Illuminate\Http\Resources\Json\JsonResource;

class {$name}Resource extends JsonResource
{
    public function toArray(\$request)
    {
        return [
{$resourceFields}
            'created_at' => \$this->created_at,
            'updated_at' => \$this->updated_at,
        ];
    }
}
EOT;
        File::put(app_path("Http/Resources/Api/{$name}/{$name}Resource.php"), $resourceTemplate);
    }

    protected function createRequestFiles($name, $fields)
    {
        $requestPath = app_path("Http/Requests/Api/{$name}");
        if (! File::exists($requestPath)) {
            File::makeDirectory($requestPath, 0755, true);
        }

        $modelClass    = "App\\Models\\{$name}";
        $modelInstance = new $modelClass();
        $tableName     = $modelInstance->getTable();

        $enumFields = property_exists($modelInstance, 'enumValues') ? $modelInstance->enumValues : [];

        $validationRules = '';
        foreach ($fields as $field) {
            $columnType = Schema::getColumnType($tableName, $field);
            $rule       = 'required';

            if (isset($enumFields[$field])) {
                $this->enumFields[$field] = [
                    'values'  => $enumFields[$field]['values'],
                    'default' => $enumFields[$field]['default'] ?? null,
                ];
                $rule .= "|in:" . implode(',', $enumFields[$field]['values']);
            } else {
                if (Str::endsWith($field, '_id')) {
                    $rule .= '|integer';
                } else {
                    switch ($columnType) {
                        case 'integer':
                        case 'bigint':
                        case 'smallint':
                        case 'tinyint':
                            $rule .= '|integer';
                            break;
                        case 'unsignedBigInteger':
                            $rule .= '|integer|min:0';
                            break;
                        case 'string':
                        case 'varchar':
                            $rule .= '|string|max:255';
                            if (Str::endsWith($field, 'email')) {
                                $rule .= '|email';
                            }
                            break;
                        case 'text':
                            $rule .= '|string';
                            break;
                        case 'decimal':
                        case 'float':
                        case 'double':
                            $rule .= '|numeric';
                            break;
                        case 'boolean':
                            $rule .= '|boolean';
                            break;
                        case 'date':
                        case 'datetime':
                        case 'timestamp':
                            $rule .= '|date';
                            break;
                        default:
                            $rule .= '|string|max:255';
                            break;
                    }
                }
            }

            $validationRules .= "            '{$field}' => '{$rule}',\n";
        }

        $storeRequestTemplate = <<<EOT
<?php

namespace App\Http\Requests\Api\\{$name};

use Illuminate\Foundation\Http\FormRequest;

class Store{$name}Request extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
{$validationRules}
        ];
    }
}
EOT;
        File::put(app_path("Http/Requests/Api/{$name}/Store{$name}Request.php"), $storeRequestTemplate);

        $updateRequestTemplate = <<<EOT
<?php

namespace App\Http\Requests\Api\\{$name};

use Illuminate\Foundation\Http\FormRequest;

class Update{$name}Request extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
{$validationRules}
        ];
    }
}
EOT;
        File::put(app_path("Http/Requests/Api/{$name}/Update{$name}Request.php"), $updateRequestTemplate);
    }

    protected function createController($name, $fields)
    {
        $controllerPath = app_path("Http/Controllers/Api/{$name}");
        if (! File::exists($controllerPath)) {
            File::makeDirectory($controllerPath, 0755, true);
        }

        $searchLogic = '';
        foreach ($fields as $field) {
            $searchLogic .= "        if (\$request->filled('{$field}')) {\n";
            $searchLogic .= "            \$query->where('{$field}', 'like', '%' . \$request->input('{$field}') . '%');\n";
            $searchLogic .= "        }\n";
        }

        $controllerTemplate = <<<EOT
<?php

namespace App\Http\Controllers\Api\\{$name};

use App\Http\Controllers\Controller;
use App\Models\\{$name};
use App\Http\Requests\Api\\{$name}\Store{$name}Request;
use App\Http\Requests\Api\\{$name}\Update{$name}Request;
use App\Http\Resources\Api\\{$name}\\{$name}Resource;
use Illuminate\Http\Request;

class {$name}Controller extends Controller
{
    public function index(Request \$request)
    {
        \$query = {$name}::query();
{$searchLogic}
        \$models = \$query->paginate(10);
        return {$name}Resource::collection(\$models);
    }

    public function store(Store{$name}Request \$request)
    {
        \$model = {$name}::create(\$request->validated());
        return new {$name}Resource(\$model);
    }

    public function show(\$id)
    {
        \$model = {$name}::findOrFail(\$id);
        return new {$name}Resource(\$model);
    }

    public function update(Update{$name}Request \$request, \$id)
    {
        \$model = {$name}::findOrFail(\$id);
        \$model->update(\$request->validated());
        return new {$name}Resource(\$model);
    }

    public function destroy(\$id)
    {
        \$model = {$name}::findOrFail(\$id);
        \$model->delete();
        return response()->json(['message' => '{$name} deleted successfully'], 204);
    }
}
EOT;
        File::put(app_path("Http/Controllers/Api/{$name}/{$name}Controller.php"), $controllerTemplate);
    }

    protected function updateRoutes($name)
    {
        $pluralName = Str::plural(strtolower($name));
        $routeEntry = "Route::apiResource('{$pluralName}', App\\Http\\Controllers\\Api\\{$name}\\{$name}Controller::class);\n";
        File::append(base_path('routes/api.php'), $routeEntry);
    }
}