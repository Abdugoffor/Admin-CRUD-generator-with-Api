<?php

namespace AdminCrud\CrudGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Crud extends Command
{
    protected $signature = 'make:crud {name}';
    protected $description = 'Generate CRUD based on an existing model with controller, requests, resource, and views extending layouts.admin';

    protected $enumFields = [];
    protected $translatableFields = [];
    protected $booleanFields = [];
    protected $fileFields = [];

    public function handle()
    {
        $name = $this->argument('name');

        $modelPath = app_path("Models/{$name}.php");
        if (!File::exists($modelPath)) {
            $this->error("Model {$name} does not exist!");
            return;
        }

        $fields = $this->getFillableFields($name);
        if (empty($fields)) {
            $this->error("No fillable fields found in {$name} model!");
            return;
        }

        $this->translatableFields = $this->getTranslatableFields($name);
        $this->booleanFields = $this->getBooleanFields($name);
        $this->fileFields = $this->getFileFields($name);

        $this->createLayout();
        $this->createResource($name, $fields);
        $this->createRequestFiles($name, $fields);
        $this->createController($name, $fields);
        $this->createViews($name, $fields);
        $this->updateRoutes($name);

        $this->info("CRUD for {$name} successfully generated!");
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function getFillableFields($name)
    {
        $modelClass = "App\\Models\\{$name}";
        if (!class_exists($modelClass)) {
            return [];
        }

        $model = new $modelClass();
        return $model->getFillable();
    }

    protected function getTranslatableFields($name)
    {
        $modelClass = "App\\Models\\{$name}";
        if (!class_exists($modelClass)) {
            return [];
        }

        $model = new $modelClass();
        $casts = $model->getCasts();
        $result = [];

        foreach ($casts as $field => $type) {
            if ($type === 'array') {
                $result[] = $field;
            }
        }

        return $result;
    }

    protected function getBooleanFields($name)
    {
        $modelClass = "App\\Models\\{$name}";
        if (!class_exists($modelClass)) {
            return [];
        }

        $model = new $modelClass();
        $casts = $model->getCasts();
        $result = [];

        foreach ($casts as $field => $type) {
            if ($type === 'boolean' || $type === 'bool') {
                $result[] = $field;
            }
        }

        return $result;
    }

    protected function getFileFields($name)
    {
        $modelClass = "App\\Models\\{$name}";
        if (!class_exists($modelClass)) {
            return [];
        }

        $model = new $modelClass();

        if (method_exists($model, 'getFileFields')) {
            $fileFields = $model->getFileFields();

            if (is_array($fileFields)) {
                return $fileFields;
            }
        }

        return [];
    }

    // ============================================================
    // LAYOUT
    // ============================================================

    protected function createLayout()
    {
        $layoutPath = resource_path('views/layouts');
        if (!File::exists($layoutPath)) {
            File::makeDirectory($layoutPath, 0755, true);
        }

        $adminLayoutPath = resource_path('views/layouts/admin.blade.php');
        if (File::exists($adminLayoutPath)) {
            return;
        }

        $adminLayoutTemplate = <<<'BLADE'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title')</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,300,100,500,700,900" rel="stylesheet" type="text/css">
    <link href="/backend/global_assets/css/icons/icomoon/styles.min.css" rel="stylesheet" type="text/css">
    <link href="/backend/assets/css/all.min.css" rel="stylesheet" type="text/css">
    <script src="/backend/global_assets/js/main/jquery.min.js"></script>
    <script src="/backend/global_assets/js/main/bootstrap.bundle.min.js"></script>
    <script src="/backend/global_assets/js/plugins/forms/selects/select2.min.js"></script>
    <script src="/backend/assets/js/app.js"></script>
    <script src="/backend/global_assets/js/demo_pages/form_select2.js"></script>
</head>
<body>
    <div class="navbar navbar-expand-lg navbar-dark navbar-static">
        <div class="navbar-brand text-center text-lg-left">
            <a href="/" class="d-inline-block d-flex align-items-center">
                <img src="/backend/admin_logo.webp" class="d-none d-sm-block" alt="" style="height:35px;margin-right:10px;">
            </a>
        </div>
        <ul class="navbar-nav flex-row order-1 order-lg-2 flex-1 flex-lg-0 justify-content-end align-items-center">
            <li class="nav-item nav-item-dropdown-lg dropdown dropdown-user h-100">
                <a href="#" class="navbar-nav-link navbar-nav-link-toggler dropdown-toggle d-inline-flex align-items-center h-100" data-toggle="dropdown">
                    <span class="d-none d-lg-inline-block">{{ auth()->user()->role ?? 'User' }}</span>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <form action="/" method="post">
                        @csrf
                        <button class="dropdown-item"><i class="icon-switch2"></i> <span>Logout</span></button>
                    </form>
                </div>
            </li>
        </ul>
    </div>
    <div class="page-content">
        <div class="sidebar sidebar-dark sidebar-main sidebar-expand-lg">
            <div class="sidebar-content">
                <div class="sidebar-section sidebar-user my-1">
                    <div class="sidebar-section-body">
                        <div class="media">
                            <div class="media-body">
                                <div class="font-weight-semibold">{{ auth()->user()->name ?? 'Guest' }}</div>
                                <div class="font-size-sm line-height-sm opacity-50">{{ auth()->user()->email ?? '' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sidebar-section">
                    <ul class="nav nav-sidebar" data-nav-type="accordion">
                        <li class="nav-item">
                            <a href="/" class="nav-link"><i class="icon-home4"></i><span>Dashboard</span></a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="content-wrapper">
            <div class="content-inner">
                <div class="page-header page-header-light">
                    <div class="page-header-content header-elements-lg-inline">
                        <div class="page-title d-flex">
                            <h4><span class="font-weight-semibold">@yield('title')</span></h4>
                        </div>
                    </div>
                </div>
                @yield('content')
                <div class="navbar navbar-expand-lg navbar-light border-bottom-0 border-top">
                    <div class="navbar-collapse collapse">
                        <ul class="navbar-nav ml-lg-auto">
                            <li class="nav-item">
                                <a href="https://uzinfocom.uz" target="_blank" class="navbar-nav-link font-weight-semibold">
                                    <span class="text-pink">uzinfocom</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
BLADE;

        File::put($adminLayoutPath, $adminLayoutTemplate);
    }

    // ============================================================
    // RESOURCE
    // ============================================================

    protected function createResource($name, $fields)
    {
        $resourcePath = app_path("Http/Resources/{$name}");
        if (!File::exists($resourcePath)) {
            File::makeDirectory($resourcePath, 0755, true);
        }

        $resourceFields = '';
        foreach ($fields as $field) {
            if (in_array($field, $this->translatableFields)) {
                $resourceFields .= "            '{$field}' => getLocale(\$this->{$field}),\n";
            } else {
                $resourceFields .= "            '{$field}' => \$this->{$field},\n";
            }
        }

        $template = <<<EOT
<?php

namespace App\Http\Resources\\{$name};

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

        File::put(app_path("Http/Resources/{$name}/{$name}Resource.php"), $template);
    }

    // ============================================================
    // REQUESTS
    // ============================================================

    protected function createRequestFiles($name, $fields)
    {
        $requestPath = app_path("Http/Requests/{$name}");
        if (!File::exists($requestPath)) {
            File::makeDirectory($requestPath, 0755, true);
        }

        $modelClass = "App\\Models\\{$name}";
        $modelInstance = new $modelClass();
        $tableName = $modelInstance->getTable();
        $enumFields = property_exists($modelInstance, 'enumValues') ? $modelInstance->enumValues : [];

        $validationRules = '';
        $translatableMerges = [];

        foreach ($fields as $field) {
            if (in_array($field, $this->translatableFields)) {
                $validationRules .= "            '{$field}' => 'required|array',\n";
                $translatableMerges[] = "validateTranslation('{$field}')";
                continue;
            }

            if (in_array($field, $this->fileFields)) {
                $validationRules .= "            '{$field}' => 'nullable|file|max:10240',\n";
                continue;
            }

            $columnType = Schema::getColumnType($tableName, $field);
            $rule = 'required';

            if (isset($enumFields[$field])) {
                $this->enumFields[$field] = [
                    'values' => $enumFields[$field]['values'],
                    'default' => $enumFields[$field]['default'] ?? null,
                ];
                $rule .= '|in:' . implode(',', $enumFields[$field]['values']);
            } elseif (Str::endsWith($field, '_id')) {
                $rule .= '|integer';
            } elseif (in_array($field, $this->booleanFields)) {
                $rule .= '|boolean';
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

            $validationRules .= "            '{$field}' => '{$rule}',\n";
        }

        $mergeBlock = '';
        if (!empty($translatableMerges)) {
            $mergeStr = implode(",\n                ", $translatableMerges);
            $mergeBlock = <<<EOT

        \$rules = array_merge(
            \$rules,
            {$mergeStr}
        );
EOT;
        }

        $messageLines = '';
        foreach ($fields as $field) {
            $fieldKey = strtolower(str_replace('_', ' ', $field));

            if (in_array($field, $this->translatableFields)) {
                $messageLines .= "            '{$field}.required'       => getTranslation('{$fieldKey} is required'),\n";
                $messageLines .= "            '{$field}.array'          => getTranslation('{$fieldKey} must be an array'),\n";
                $messageLines .= "            '{$field}.*.required'     => getTranslation('{$fieldKey} translation is required'),\n";
                $messageLines .= "            '{$field}.*.string'       => getTranslation('{$fieldKey} translation must be a string'),\n";
                continue;
            }

            if (in_array($field, $this->fileFields)) {
                $messageLines .= "            '{$field}.file'        => getTranslation('{$fieldKey} must be a file'),\n";
                $messageLines .= "            '{$field}.max'         => getTranslation('{$fieldKey} must not exceed 10 MB'),\n";
                continue;
            }

            $columnType = Schema::getColumnType($tableName, $field);

            $messageLines .= "            '{$field}.required'   => getTranslation('{$fieldKey} is required'),\n";

            if (in_array($field, $this->booleanFields) || $columnType === 'boolean') {
                $messageLines .= "            '{$field}.boolean'   => getTranslation('{$fieldKey} must be true or false'),\n";
            } elseif (Str::endsWith($field, '_id') || in_array($columnType, ['integer', 'bigint', 'smallint', 'tinyint', 'unsignedBigInteger'])) {
                $messageLines .= "            '{$field}.integer'   => getTranslation('{$fieldKey} must be an integer'),\n";
                if ($columnType === 'unsignedBigInteger') {
                    $messageLines .= "            '{$field}.min'       => getTranslation('{$fieldKey} must be at least 0'),\n";
                }
            } elseif (in_array($columnType, ['string', 'varchar'])) {
                $messageLines .= "            '{$field}.string'    => getTranslation('{$fieldKey} must be a string'),\n";
                $messageLines .= "            '{$field}.max'       => getTranslation('{$fieldKey} must not exceed 255 characters'),\n";
                if (Str::endsWith($field, 'email')) {
                    $messageLines .= "            '{$field}.email'     => getTranslation('{$fieldKey} must be a valid email'),\n";
                }
            } elseif ($columnType === 'text') {
                $messageLines .= "            '{$field}.string'    => getTranslation('{$fieldKey} must be a string'),\n";
            } elseif (in_array($columnType, ['decimal', 'float', 'double'])) {
                $messageLines .= "            '{$field}.numeric'   => getTranslation('{$fieldKey} must be a number'),\n";
            } elseif (in_array($columnType, ['date', 'datetime', 'timestamp'])) {
                $messageLines .= "            '{$field}.date'      => getTranslation('{$fieldKey} must be a valid date'),\n";
            }

            if (isset($enumFields[$field])) {
                $messageLines .= "            '{$field}.in'        => getTranslation('{$fieldKey} has an invalid value'),\n";
            }
        }

        foreach (['Store', 'Update'] as $type) {
            $template = <<<EOT
<?php

namespace App\Http\Requests\\{$name};

use Illuminate\Foundation\Http\FormRequest;

class {$type}{$name}Request extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        \$rules = [
{$validationRules}
        ];
{$mergeBlock}

        return \$rules;
    }

    public function messages()
    {
        return [
{$messageLines}
        ];
    }
}
EOT;

            File::put(app_path("Http/Requests/{$name}/{$type}{$name}Request.php"), $template);
        }
    }

    // ============================================================
    // CONTROLLER
    // ============================================================

    protected function createController($name, $fields)
    {
        $controllerPath = app_path("Http/Controllers/{$name}");
        if (!File::exists($controllerPath)) {
            File::makeDirectory($controllerPath, 0755, true);
        }

        $pluralName = Str::plural(strtolower($name));

        $searchLogic = '';
        foreach ($fields as $field) {
            if (in_array($field, $this->fileFields)) {
                continue;
            }

            if (in_array($field, $this->translatableFields)) {
                $searchLogic .= <<<EOT

        if (\$request->filled('{$field}')) {
            \$locale = app()->getLocale();
            \$search = '%' . \$request->input('{$field}') . '%';
            \$query->where(function (\$q) use (\$search, \$locale) {
                \$q->whereRaw("lower({$field}->>'{\$locale}') LIKE lower(?)", [\$search])
                  ->orWhereRaw("lower({$field}->>'default') LIKE lower(?)", [\$search]);
            });
        }
EOT;
            } else {
                $searchLogic .= <<<EOT

        if (\$request->filled('{$field}')) {
            \$query->where('{$field}', 'like', '%' . \$request->input('{$field}') . '%');
        }
EOT;
            }
        }

        $storeDefaults = '';
        foreach ($this->translatableFields as $tf) {
            $storeDefaults .= "        if (isset(\$data['{$tf}']) && is_array(\$data['{$tf}'])) {\n";
            $storeDefaults .= "            \$data['{$tf}']['default'] = reset(\$data['{$tf}']);\n";
            $storeDefaults .= "        }\n";
        }

        $storeFileUploadLogic = '';
        $updateFileUploadLogic = '';

        foreach ($this->fileFields as $fileField) {
            $storeFileUploadLogic .= <<<EOT
        if (\$request->hasFile('{$fileField}')) {
            \$data['{$fileField}'] = FileUploadService::uploadFile(\$request->file('{$fileField}'));
        }

EOT;

            $updateFileUploadLogic .= <<<EOT
        if (\$request->hasFile('{$fileField}')) {
            \$data['{$fileField}'] = FileUploadService::uploadFile(\$request->file('{$fileField}'));
        } else {
            unset(\$data['{$fileField}']);
        }

EOT;
        }

        $template = <<<EOT
<?php

namespace App\Http\Controllers\\{$name};

use App\Http\Controllers\Controller;
use App\Models\\{$name};
use App\Http\Requests\\{$name}\Store{$name}Request;
use App\Http\Requests\\{$name}\Update{$name}Request;
use App\Http\Resources\\{$name}\\{$name}Resource;
use App\Services\FileUploadService;
use Illuminate\Http\Request;

class {$name}Controller extends Controller
{
    public function index(Request \$request)
    {
        \$query = {$name}::query();
{$searchLogic}

        \$models = \$query->paginate(10)->withQueryString();
        return view('{$pluralName}.index', ['models' => \$models]);
    }

    public function create()
    {
        return view('{$pluralName}.create');
    }

    public function store(Store{$name}Request \$request)
    {
        \$data = \$request->validated();
{$storeDefaults}
{$storeFileUploadLogic}
        {$name}::create(\$data);

        return redirect()->route('{$pluralName}.index')
            ->with('notification', getTranslation('{$name} создан успешно'));
    }

    public function show(\$id)
    {
        \$model = {$name}::findOrFail(\$id);
        return view('{$pluralName}.show', ['model' => \$model]);
    }

    public function edit(\$id)
    {
        \$model = {$name}::findOrFail(\$id);
        return view('{$pluralName}.edit', ['model' => \$model]);
    }

    public function update(Update{$name}Request \$request, \$id)
    {
        \$model = {$name}::findOrFail(\$id);
        \$data  = \$request->validated();
{$storeDefaults}
{$updateFileUploadLogic}
        \$model->update(\$data);

        return redirect()->route('{$pluralName}.index')
            ->with('notification', getTranslation('{$name} обновлён успешно'));
    }

    public function destroy(\$id)
    {
        \$model = {$name}::findOrFail(\$id);
        \$model->delete();

        return redirect()->route('{$pluralName}.index')
            ->with('notification', getTranslation('{$name} удалён успешно'));
    }
}
EOT;

        File::put(app_path("Http/Controllers/{$name}/{$name}Controller.php"), $template);
    }

    // ============================================================
    // VIEWS
    // ============================================================

    protected function createViews($name, $fields)
    {
        $pluralName = Str::plural(strtolower($name));
        File::makeDirectory(resource_path("views/{$pluralName}"), 0755, true, true);

        $enumFields = $this->enumFields ?? [];
        $modelClass = "App\\Models\\{$name}";
        $modelInstance = new $modelClass();
        $tableName = $modelInstance->getTable();

        $this->createIndexView($name, $pluralName, $fields);
        $this->createFormView($name, $pluralName, $fields, $enumFields, $tableName, false);
        $this->createFormView($name, $pluralName, $fields, $enumFields, $tableName, true);
        $this->createShowView($name, $pluralName, $fields);
    }

    protected function createIndexView($name, $pluralName, $fields)
    {
        $tableHeaders = "                                <th class=\"text-center\" width=\"3%\">№</th>\n";
        $searchInputs = "                                <th class=\"text-center\"></th>\n";
        $tableRow = '';

        foreach ($fields as $field) {
            $fieldKey = strtolower(str_replace('_', ' ', $field));

            $tableHeaders .= "                                <th class=\"text-center\">{{ getTranslation('{$fieldKey}') }}</th>\n";

            if (in_array($field, $this->fileFields)) {
                $searchInputs .= "                                <th class=\"text-center\"></th>\n";
            } else {
                $searchInputs .= "                                <th class=\"text-center\">\n";
                $searchInputs .= "                                    <input type=\"text\" class=\"form-control\" name=\"{$field}\"\n";
                $searchInputs .= "                                           placeholder=\"{{ getTranslation('{$fieldKey}') }}\"\n";
                $searchInputs .= "                                           value=\"{{ old('{$field}', request('{$field}')) }}\">\n";
                $searchInputs .= "                                </th>\n";
            }

            if (in_array($field, $this->translatableFields)) {
                $tableRow .= "                            <td>{{ getLocale(\$model->{$field}) }}</td>\n";
            } elseif (in_array($field, $this->fileFields)) {
                $tableRow .= "                            <td>@if(\$model->{$field}) <a href=\"{{ asset(\$model->{$field}) }}\" target=\"_blank\">{{ getTranslation('Open file') }}</a> @else - @endif</td>\n";
            } elseif (in_array($field, $this->booleanFields)) {
                $tableRow .= "                            <td>{{ \$model->{$field} ? '1' : '0' }}</td>\n";
            } else {
                $tableRow .= "                            <td>{{ \$model->{$field} }}</td>\n";
            }
        }

        $template = <<<EOT
@extends('layouts.admin')
@section('title', getTranslation('{$name}'))
@section('content')
    <div class="content">
        <div class="row">
            <div class="col-xl-12">

                @if (session('notification'))
                    <div class="alert bg-teal text-white alert-rounded alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                        <span class="font-weight-semibold">{{ session('notification') }}</span>
                    </div>
                @endif

                <div class="card">
                    <div class="card-body d-lg-flex align-items-lg-center justify-content-lg-between flex-lg-wrap">
                        <div class="d-flex align-items-center mb-3 mb-lg-0"></div>
                        <div>
                            <a href="{{ route('{$pluralName}.create') }}" class="btn btn-teal">
                                <i class="icon-plus3 icon-1x mr-1"></i> {{ getTranslation('Добавить') }}
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table text-nowrap table-bordered">
                            <thead>
                                <tr>
{$tableHeaders}
                                    <th class="text-center">{{ getTranslation('Действия') }}</th>
                                </tr>
                                <form action="{{ route('{$pluralName}.index') }}" method="get">
                                    <tr>
{$searchInputs}
                                        <th class="text-center">
                                            <button class="btn btn-teal">{{ getTranslation('Поиск') }}</button>
                                        </th>
                                    </tr>
                                </form>
                            </thead>
                            <tbody>
                                @foreach (\$models as \$model)
                                    <tr>
                                        <td>{{ (\$models->currentPage() - 1) * \$models->perPage() + \$loop->iteration }}</td>
{$tableRow}
                                        <td>
                                            <div class="d-inline-flex gap-2">
                                                <a href="{{ route('{$pluralName}.show', \$model->id) }}" class="btn btn-outline-info">
                                                    <i class="icon-eye8"></i>
                                                </a>
                                                <a href="{{ route('{$pluralName}.edit', \$model->id) }}" class="btn btn-outline-success ml-2">
                                                    <i class="icon-pencil3"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger ml-2" data-toggle="modal" data-target="#delete_modal_{{ \$model->id }}">
                                                    <i class="icon-trash"></i>
                                                </button>
                                            </div>

                                            <div id="delete_modal_{{ \$model->id }}" class="modal fade" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered modal-sm">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                        </div>
                                                        <form action="{{ route('{$pluralName}.destroy', \$model->id) }}" method="post">
                                                            @csrf
                                                            @method('DELETE')
                                                            <div class="modal-body">
                                                                <h3 class="text-center">{{ getTranslation('Вы уверены, что хотите удалить?') }}</h3>
                                                            </div>
                                                            <div class="modal-footer d-flex justify-content-center pb-4">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ getTranslation('Закрыть') }}</button>
                                                                <button type="submit" class="btn btn-danger">{{ getTranslation('Подтвердить') }}</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{ \$models->links() }}
            </div>
        </div>
    </div>
@endsection
EOT;

        File::put(resource_path("views/{$pluralName}/index.blade.php"), $template);
    }

    protected function createFormView($name, $pluralName, $fields, $enumFields, $tableName, bool $isEdit)
    {
        $formFields = $this->buildFormFields($fields, $enumFields, $tableName, $isEdit);

        if ($isEdit) {
            $title = strtolower($name);
            $sectionTitle = "getTranslation('Редактировать {$title}')";
            $formAction = "{{ route('{$pluralName}.update', \$model->id) }}";
            $methodSpoofing = "\n                    @method('PUT')";
            $submitLabel = "{{ getTranslation('Обновить') }}";
            $fileName = 'edit';
        } else {
            $title = strtolower($name);
            $sectionTitle = "getTranslation('Создать {$title}')";
            $formAction = "{{ route('{$pluralName}.store') }}";
            $methodSpoofing = '';
            $submitLabel = "{{ getTranslation('Добавить') }}";
            $fileName = 'create';
        }

        $nameKey = strtolower($name);

        $template = <<<EOT
@extends('layouts.admin')
@section('title', {$sectionTitle})
@section('content')
    <div class="content">
        <div class="d-inline-flex gap-2">
            <a href="{{ route('{$pluralName}.index') }}" class="btn btn-outline-secondary">
                {{ getTranslation('Назад') }}
            </a>
        </div>

        <div class="card mt-2">
            <div class="card-body">
                <form action="{$formAction}" method="POST" enctype="multipart/form-data">
                    @csrf{$methodSpoofing}

                    <fieldset class="mb-3">
                        <legend class="text-uppercase font-size-sm font-weight-bold">
                            {{ getTranslation('{$nameKey}') }}
                        </legend>
                        <div class="form-group row">
                            <div class="card-body">

{$formFields}
                            </div>
                        </div>
                    </fieldset>

                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">{$submitLabel}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
EOT;

        File::put(resource_path("views/{$pluralName}/{$fileName}.blade.php"), $template);
    }

    protected function createShowView($name, $pluralName, $fields)
    {
        $showFields = '';

        foreach ($fields as $field) {
            $fieldKey = strtolower(str_replace('_', ' ', $field));

            if (in_array($field, $this->translatableFields)) {
                $showFields .= <<<EOT
                        <tr>
                            <th style="width:20%;vertical-align:top">{{ getTranslation('{$fieldKey}') }}</th>
                            <td>
                                @if(is_array(\$model->{$field}))
                                    <ul class="nav nav-tabs" id="show-tabs-{$field}">
                                        @foreach(getLanguage() as \$lang)
                                            <li class="nav-item">
                                                <a href="#show-{$field}-{{ \$lang->id }}"
                                                   class="nav-link {{ \$loop->first ? 'active' : '' }}"
                                                   data-toggle="tab">
                                                    {{ \$lang->name }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="tab-content border border-top-0 p-2">
                                        @foreach(getLanguage() as \$lang)
                                            <div class="tab-pane fade {{ \$loop->first ? 'show active' : '' }}"
                                                 id="show-{$field}-{{ \$lang->id }}">
                                                {!! nl2br(e(\$model->{$field}[\$lang->name] ?? \$model->{$field}['default'] ?? '')) !!}
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    {{ \$model->{$field} }}
                                @endif
                            </td>
                        </tr>

EOT;
            } elseif (in_array($field, $this->fileFields)) {
                $showFields .= <<<EOT
                        <tr>
                            <th style="width:20%">{{ getTranslation('{$fieldKey}') }}</th>
                            <td>
                                @if(\$model->{$field})
                                    <a href="{{ asset(\$model->{$field}) }}" target="_blank">
                                        {{ getTranslation('Open file') }}
                                    </a>

                                    @php
                                        \$extension = strtolower(pathinfo(\$model->{$field}, PATHINFO_EXTENSION));
                                    @endphp

                                    @if(in_array(\$extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                        <div class="mt-2">
                                            <img src="{{ asset(\$model->{$field}) }}" alt="{$field}" style="max-height:150px; border-radius:8px;">
                                        </div>
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                        </tr>

EOT;
            } elseif (in_array($field, $this->booleanFields)) {
                $showFields .= <<<EOT
                        <tr>
                            <th style="width:20%">{{ getTranslation('{$fieldKey}') }}</th>
                            <td>{{ \$model->{$field} ? getTranslation('Активный') : getTranslation('Неактивный') }}</td>
                        </tr>

EOT;
            } else {
                $showFields .= <<<EOT
                        <tr>
                            <th style="width:20%">{{ getTranslation('{$fieldKey}') }}</th>
                            <td>{{ \$model->{$field} }}</td>
                        </tr>

EOT;
            }
        }

        $nameKey = strtolower($name);

        $template = <<<EOT
@extends('layouts.admin')
@section('title', getTranslation('{$nameKey} детали'))
@section('content')
    <div class="content">
        <div class="d-inline-flex gap-2">
            <a href="{{ route('{$pluralName}.index') }}" class="btn btn-outline-secondary">
                {{ getTranslation('Назад') }}
            </a>
            <a href="{{ route('{$pluralName}.edit', \$model->id) }}" class="btn btn-outline-success ml-2">
                {{ getTranslation('Редактировать') }}
            </a>
        </div>

        <div class="card mt-2">
            <div class="card-body">
                <table class="table table-bordered">
                    <tbody>

{$showFields}
                        <tr>
                            <th style="width:20%">{{ getTranslation('Создан') }}</th>
                            <td>{{ \$model->created_at->format('d-m-Y, H:i') }}</td>
                        </tr>
                        <tr>
                            <th style="width:20%">{{ getTranslation('Обновлён') }}</th>
                            <td>{{ \$model->updated_at->format('d-m-Y, H:i') }}</td>
                        </tr>

                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
EOT;

        File::put(resource_path("views/{$pluralName}/show.blade.php"), $template);
    }

    // ============================================================
    // FORM FIELDS BUILDER
    // ============================================================

    protected function buildFormFields($fields, $enumFields, $tableName, bool $isEdit): string
    {
        $out = '';

        foreach ($fields as $field) {
            $fieldKey = strtolower(str_replace('_', ' ', $field));

            if (in_array($field, $this->translatableFields)) {
                $out .= $this->buildTranslatableField($field, $fieldKey, $isEdit);
                continue;
            }

            if (in_array($field, $this->fileFields)) {
                $out .= $this->buildFileField($field, $fieldKey, $isEdit);
                continue;
            }

            $columnType = Schema::getColumnType($tableName, $field);

            if (isset($enumFields[$field])) {
                $options = "                                        <option value=\"\">{{ getTranslation('Выберите {$fieldKey}') }}</option>\n";

                foreach ($enumFields[$field]['values'] as $value) {
                    $selectedAttr = $isEdit
                        ? "{{ old('{$field}', \$model->{$field}) == '{$value}' ? 'selected' : '' }}"
                        : (($value === ($enumFields[$field]['default'] ?? '')) ? 'selected' : '');

                    $options .= "                                        <option value=\"{$value}\" {$selectedAttr}>{$value}</option>\n";
                }

                $out .= <<<EOT
                                <div class="form-group">
                                    <label class="form-label">{{ getTranslation('{$fieldKey}') }}</label>
                                    <select name="{$field}" class="form-control">
{$options}
                                    </select>
                                    @error('{$field}')
                                        <p style="color:red">{{ \$message }}</p>
                                    @enderror
                                </div>

EOT;
                continue;
            }

            if (in_array($field, $this->booleanFields) || $columnType === 'boolean') {
                $out .= $this->buildBooleanField($field, $fieldKey, $isEdit);
                continue;
            }

            $inputType = 'text';
            if (Str::endsWith($field, 'email')) {
                $inputType = 'email';
            } elseif (in_array($columnType, ['integer', 'bigint', 'smallint', 'tinyint', 'unsignedBigInteger'])) {
                $inputType = 'number';
            } elseif (in_array($columnType, ['date', 'datetime', 'timestamp'])) {
                $inputType = 'date';
            }

            $valueAttr = $isEdit
                ? "{{ old('{$field}', \$model->{$field} ?? '') }}"
                : "{{ old('{$field}') }}";

            $out .= <<<EOT
                                <div class="form-group">
                                    <label class="col-form-label">{{ getTranslation('{$fieldKey}') }}</label>
                                    <input type="{$inputType}" class="form-control"
                                           name="{$field}"
                                           value="{$valueAttr}"
                                           placeholder="{{ getTranslation('{$fieldKey}') }}">
                                    @error('{$field}')
                                        <p style="color:red">{{ \$message }}</p>
                                    @enderror
                                </div>

EOT;
        }

        return $out;
    }

    protected function buildBooleanField(string $field, string $fieldKey, bool $isEdit): string
    {
        $fieldId = Str::slug($field, '_');

        if ($isEdit) {
            return <<<EOT
                                <div class="form-group">
                                    <label class="custom-control custom-switch custom-control-right">
                                        <input type="hidden" name="{$field}" value="0">
                                        <input type="checkbox"
                                               name="{$field}"
                                               class="custom-control-input"
                                               value="1"
                                               {{ old('{$field}', \$model->{$field} ?? 1) ? 'checked' : '' }}>
                                        <span class="custom-control-label">{{ getTranslation('{$fieldKey}') }}</span>
                                    </label>

                                    @error('{$field}')
                                        <p style="color:red">{{ \$message }}</p>
                                    @enderror
                                </div>

EOT;
        }

        return <<<EOT
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="hidden" name="{$field}" value="0">
                                        <input type="checkbox"
                                               name="{$field}"
                                               class="custom-control-input"
                                               id="{$fieldId}"
                                               value="1"
                                               {{ old('{$field}', 1) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="{$fieldId}">
                                            {{ getTranslation('{$fieldKey}') }}
                                        </label>
                                    </div>

                                    @error('{$field}')
                                        <p class="text-danger mt-1">{{ \$message }}</p>
                                    @enderror
                                </div>

EOT;
    }

    protected function buildFileField(string $field, string $fieldKey, bool $isEdit): string
    {
        $preview = '';

        if ($isEdit) {
            $preview = <<<EOT
                                    @if(!empty(\$model->{$field}))
                                        <div class="mb-2">
                                            <a href="{{ asset(\$model->{$field}) }}" target="_blank">
                                                {{ getTranslation('Current file') }}
                                            </a>
                                        </div>

                                        @php
                                            \$extension = strtolower(pathinfo(\$model->{$field}, PATHINFO_EXTENSION));
                                        @endphp

                                        @if(in_array(\$extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                            <div class="mb-2">
                                                <img src="{{ asset(\$model->{$field}) }}" alt="{$field}" style="max-height:120px; border-radius:8px;">
                                            </div>
                                        @endif
                                    @endif
EOT;
        }

        return <<<EOT
                                <div class="form-group">
                                    <label class="col-form-label">{{ getTranslation('{$fieldKey}') }}</label>
{$preview}
                                    <input type="file" class="form-control" name="{$field}">
                                    @error('{$field}')
                                        <p style="color:red">{{ \$message }}</p>
                                    @enderror
                                </div>

EOT;
    }

    protected function buildTranslatableField(string $field, string $fieldKey, bool $isEdit): string
    {
        if ($isEdit) {
            $valueAttr = "{{ old('{$field}.' . \$lang->name, is_array(\$model->{$field}) ? (\$model->{$field}[\$lang->name] ?? \$model->{$field}['default'] ?? '') : '') }}";
        } else {
            $valueAttr = "{{ old('{$field}.' . \$lang->name) }}";
        }

        return <<<EOT
                                <div class="form-group">
                                    <label class="form-label">{{ getTranslation('{$fieldKey}') }}</label>
                                    <ul class="nav nav-tabs mt-1" id="tabs-{$field}">
                                        @foreach (getLanguage() as \$lang)
                                            <li class="nav-item">
                                                <a href="#tab-{$field}-{{ \$lang->id }}"
                                                   class="nav-link {{ \$loop->first ? 'active' : '' }}"
                                                   data-toggle="tab">
                                                    {{ \$lang->name }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="tab-content border border-top-0 p-2 mb-1">
                                        @foreach (getLanguage() as \$lang)
                                            <div class="tab-pane fade {{ \$loop->first ? 'show active' : '' }}"
                                                 id="tab-{$field}-{{ \$lang->id }}">
                                                <input type="text"
                                                       class="form-control mt-1"
                                                       name="{$field}[{{ \$lang->name }}]"
                                                       value="{$valueAttr}"
                                                       placeholder="{{ \$lang->name }}">
                                                @error('{$field}.' . \$lang->name)
                                                    <p style="color:red">{{ \$message }}</p>
                                                @enderror
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

EOT;
    }

    // ============================================================
    // ROUTES
    // ============================================================

    protected function updateRoutes($name)
    {
        $pluralName = Str::plural(strtolower($name));
        $routeEntry = "Route::resource('{$pluralName}', App\\Http\\Controllers\\{$name}\\{$name}Controller::class);\n";

        File::append(base_path('routes/web.php'), $routeEntry);
    }
}