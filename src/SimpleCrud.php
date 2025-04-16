<?php

namespace AdminCrud\CrudGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SimpleCrud extends Command
{
    protected $signature   = 'make:simple-crud {name}';
    protected $description = 'Generate CRUD based on an existing model with controller, requests, resource, and views extending layouts.admin';

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

        $this->createLayout();
        $this->createResource($name, $fields);
        $this->createRequestFiles($name, $fields);
        $this->createController($name, $fields);
        $this->createViews($name, $fields);
        $this->updateRoutes($name);

        $this->info("CRUD for {$name} successfully generated!");
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

    protected function createLayout()
    {
        $layoutPath = resource_path('views/layouts');
        if (! File::exists($layoutPath)) {
            File::makeDirectory($layoutPath, 0755, true);
        }

        $adminLayoutPath = resource_path('views/layouts/admin.blade.php');
        if (! File::exists($adminLayoutPath)) {
            $adminLayoutTemplate = <<<EOT
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title')</title>

    <!-- Global stylesheets -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,300,100,500,700,900" rel="stylesheet" type="text/css">
    <link href="/backend/global_assets/css/icons/icomoon/styles.min.css" rel="stylesheet" type="text/css">
    <link href="/backend/assets/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- /global stylesheets -->

    <!-- Core JS files -->
    <script src="/backend/global_assets/js/main/jquery.min.js"></script>
    <script src="/backend/global_assets/js/main/bootstrap.bundle.min.js"></script>
    <!-- /core JS files -->

    <script src="/backend/assets/js/app.js"></script>
</head>
<body>
    <!-- Main navbar -->
    <div class="navbar navbar-expand-lg navbar-dark navbar-static">
        <div class="d-flex flex-1 d-lg-none">
            <button class="navbar-toggler sidebar-mobile-main-toggle" type="button">
                <i class="icon-transmission"></i>
            </button>
        </div>

        <div class="navbar-brand text-center text-lg-left">
            <a href="/" target="_blank" class="d-inline-block d-flex align-items-center">
                <img src="/backend/admin_logo.webp" class="d-none d-sm-block" alt="" style="height: 35px; margin-right: 10px;">
                <span style="color: white; font-size: 14px;">International Chess Federation</span>
            </a>
        </div>

        <ul class="navbar-nav flex-row order-1 order-lg-2 flex-1 flex-lg-0 justify-content-end align-items-center">

            <li class="nav-item nav-item-dropdown-lg dropdown dropdown-user h-100">
                <a href="#"
                    class="navbar-nav-link navbar-nav-link-toggler dropdown-toggle d-inline-flex align-items-center h-100"
                    data-toggle="dropdown">
                    <span class="d-none d-lg-inline-block"
                        style="text-transform: capitalize; !important">{{ app()->getLocale() }}</span>
                </a>

                <div class="dropdown-menu dropdown-menu-right">
                    <a href="#" class="dropdown-item">
                        RU
                    </a>
                </div>
            </li>

            <li class="nav-item nav-item-dropdown-lg dropdown dropdown-user h-100">
                <a href="#"
                    class="navbar-nav-link navbar-nav-link-toggler dropdown-toggle d-inline-flex align-items-center h-100"
                    data-toggle="dropdown">
                    <span class="d-none d-lg-inline-block">{{ auth()->user()->role ?? 'User' }}</span>
                </a>

                <div class="dropdown-menu dropdown-menu-right">
                    <a href="/" class="dropdown-item"><i class="icon-user-plus"></i>
                        Profile
                    </a>
                    <form action="/" method="post">
                        @csrf
                        <button class="dropdown-item">
                            <i class="icon-switch2"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </div>
    <!-- /main navbar -->

    <!-- Page content -->
    <div class="page-content">
        <!-- Main sidebar -->
        <div class="sidebar sidebar-dark sidebar-main sidebar-expand-lg">
            <div class="sidebar-content">
                <!-- User menu -->
                <div class="sidebar-section sidebar-user my-1">
                    <div class="sidebar-section-body">
                        <div class="media">
                            <a href="/" target="_blank" class="mr-3">
                                <img src="https://cdn-icons-png.flaticon.com/512/8664/8664801.png" class="rounded-circle" alt="">
                            </a>
                            <div class="media-body">
                                <div class="font-weight-semibold">{{ auth()->user()->name ?? 'Guest' }}</div>
                                <div class="font-size-sm line-height-sm opacity-50" title="{{ auth()->user()->email ?? '' }}">
                                    {{ Str::limit(auth()->user()->email ?? '', 15) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /user menu -->

                <!-- Main navigation -->
                <div class="sidebar-section">
                    <ul class="nav nav-sidebar" data-nav-type="accordion">
                        <li class="nav-item">
                            <a href="/" class="nav-link">
                                <i class="icon-home4"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <!-- /main navigation -->
            </div>
        </div>
        <!-- /main sidebar -->

        <!-- Main content -->
        <div class="content-wrapper">
            <!-- Inner content -->
            <div class="content-inner">
                <!-- Page header -->
                <div class="page-header page-header-light">
                    <div class="page-header-content header-elements-lg-inline">
                        <div class="page-title d-flex">
                            <h4>
                                <span class="font-weight-semibold">@yield('title')</span>
                            </h4>
                        </div>
                    </div>
                </div>
                <!-- /page header -->

                @yield('content')

                <!-- Footer -->
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
                <!-- /footer -->
            </div>
            <!-- /inner content -->
        </div>
        <!-- /main content -->
    </div>
    <!-- /page content -->
</body>
</html>
EOT;
            File::put($adminLayoutPath, $adminLayoutTemplate);
        }
    }

    protected function createResource($name, $fields)
    {
        $resourcePath = app_path("Http/Resources/{$name}");
        if (! File::exists($resourcePath)) {
            File::makeDirectory($resourcePath, 0755, true);
        }

        $resourceFields = '';
        foreach ($fields as $field) {
            $resourceFields .= "            '{$field}' => \$this->{$field},\n";
        }

        $resourceTemplate = <<<EOT
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
        File::put(app_path("Http/Resources/{$name}/{$name}Resource.php"), $resourceTemplate);
    }

    protected function createRequestFiles($name, $fields)
    {
        $requestPath = app_path("Http/Requests/{$name}");
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

namespace App\Http\Requests\\{$name};

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
        File::put(app_path("Http/Requests/{$name}/Store{$name}Request.php"), $storeRequestTemplate);

        $updateRequestTemplate = <<<EOT
<?php

namespace App\Http\Requests\\{$name};

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
        File::put(app_path("Http/Requests/{$name}/Update{$name}Request.php"), $updateRequestTemplate);
    }

    protected function createController($name, $fields)
    {
        $controllerPath = app_path("Http/Controllers/{$name}");
        if (! File::exists($controllerPath)) {
            File::makeDirectory($controllerPath, 0755, true);
        }

        $pluralName  = Str::plural(strtolower($name));
        $searchLogic = '';
        foreach ($fields as $field) {
            $searchLogic .= "        if (\$request->filled('{$field}')) {\n";
            $searchLogic .= "            \$query->where('{$field}', 'like', '%' . \$request->input('{$field}') . '%');\n";
            $searchLogic .= "        }\n";
        }

        $controllerTemplate = <<<EOT
<?php

namespace App\Http\Controllers\\{$name};

use App\Http\Controllers\Controller;
use App\Models\\{$name};
use App\Http\Requests\\{$name}\Store{$name}Request;
use App\Http\Requests\\{$name}\Update{$name}Request;
use App\Http\Resources\\{$name}\\{$name}Resource;
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
        \$model = {$name}::create(\$request->validated());
        return redirect()->route('{$pluralName}.index')->with('notification', '{$name} created successfully!');
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
        \$model->update(\$request->validated());
        return redirect()->route('{$pluralName}.index')->with('notification', '{$name} updated successfully!');
    }

    public function destroy(\$id)
    {
        \$model = {$name}::findOrFail(\$id);
        \$model->delete();
        return redirect()->route('{$pluralName}.index')->with('notification', '{$name} deleted successfully!');
    }
}
EOT;
        File::put(app_path("Http/Controllers/{$name}/{$name}Controller.php"), $controllerTemplate);
    }

    protected function createViews($name, $fields)
    {
        $pluralName = Str::plural(strtolower($name));
        File::makeDirectory(resource_path("views/{$pluralName}"), 0755, true, true);

        $enumFields = $this->enumFields ?? [];

        // Model jadval nomini olish
        $modelClass    = "App\\Models\\{$name}";
        $modelInstance = new $modelClass();
        $tableName     = $modelInstance->getTable();

        // index.blade.php
        $tableHeaders = "                                <th class=\"text-center\" width=\"3%\">№</th>\n";
        $searchInputs = "                                <th class=\"text-center\"></th>\n";
        $tableRow     = '';
        foreach ($fields as $field) {
            $fieldFormatted = ucwords(str_replace('_', ' ', $field));
            $tableHeaders .= "                                <th class=\"text-center\">{$fieldFormatted}</th>\n";
            $searchInputs .= "                                <th class=\"text-center\">\n";
            $searchInputs .= "                                    <input type=\"text\" class=\"form-control\" name=\"{$field}\" placeholder=\"{$fieldFormatted}\" value=\"{{ old('{$field}', request('{$field}')) }}\">\n";
            $searchInputs .= "                                </th>\n";
            $tableRow .= "                            <td>{{ \$model->{$field} }}</td>\n";
        }

        $indexTemplate = <<<EOT
@extends('layouts.admin')
@section('title', '{$name}')
@section('content')
    <!-- Content area -->
    <div class="content">
        <!-- Dashboard content -->
        <div class="row">
            <div class="col-xl-12">
                <!-- Support tickets -->
                @if (session('notification'))
                    <div class="alert bg-teal text-white alert-rounded alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert"><span>×</span></button>
                        <span class="font-weight-semibold">{{ session('notification') }}</span>
                    </div>
                @endif
                <div class="card">
                    <div class="card-body d-lg-flex align-items-lg-center justify-content-lg-between flex-lg-wrap">
                        <div class="d-flex align-items-center mb-3 mb-lg-0">
                            <div class="ml-3"></div>
                        </div>
                        <div>
                            <a href="{{ route('{$pluralName}.create', [], false) }}" class="btn btn-teal">
                                <i class="icon-plus3 icon-1x mr-1"></i>Add {$name}
                            </a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table text-nowrap table-bordered">
                            <thead>
                                <tr>
{$tableHeaders}
                                    <th class="text-center" width="5%">Actions</th>
                                </tr>
                                <form action="{{ route('{$pluralName}.index', [], false) }}" method="get">
                                    <tr>
{$searchInputs}
                                        <th class="text-center">
                                            <button class="btn btn-teal">Search</button>
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
                                                <a href="{{ route('{$pluralName}.show', \$model->id, false) }}" class="btn btn-outline-info">
                                                    <i class="icon-eye8"></i>
                                                </a>
                                                <a href="{{ route('{$pluralName}.edit', \$model->id, false) }}" class="btn btn-outline-success ml-2">
                                                    <i class="icon-pencil3"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger ml-2" data-toggle="modal" data-target="#modal_full{{ \$model->id }}">
                                                    <i class="icon-trash"></i>
                                                </button>
                                                <!-- Full width modal -->
                                                <div id="modal_full{{ \$model->id }}" class="modal fade" tabindex="-1">
                                                    <div class="modal-dialog modal-dialog-centered modal-sm">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <button type="button" class="close" data-dismiss="modal">×</button>
                                                            </div>
                                                            <form action="{{ route('{$pluralName}.destroy', \$model->id, false) }}" method="post">
                                                                @csrf
                                                                @method('DELETE')
                                                                <div class="modal-body">
                                                                    <div class="row">
                                                                        <div class="col-12">
                                                                            <h3 class="text-center">Are you sure you want to delete?</h3>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer d-flex justify-content-center pb-4">
                                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                    <button type="submit" class="btn btn-danger">Confirm</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- /full width modal -->
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- /support tickets -->
                {{ \$models->links() }}
            </div>
        </div>
        <!-- /dashboard content -->
    </div>
    <!-- /content area -->
@endsection
EOT;
        File::put(resource_path("views/{$pluralName}/index.blade.php"), $indexTemplate);

        // create.blade.php
        $formFields = '';
        foreach ($fields as $field) {
            $fieldFormatted = ucwords(str_replace('_', ' ', $field));
            $columnType = Schema::getColumnType($tableName, $field); // Har bir maydon uchun columnType aniqlanadi
            if (isset($enumFields[$field])) {
                $options = '';
                foreach ($enumFields[$field]['values'] as $value) {
                    $selected = ($value === ($enumFields[$field]['default'] ?? '')) ? ' selected' : '';
                    $options .= "<option value=\"{$value}\"{$selected}>{$value}</option>\n";
                }
                $formFields .= <<<EOT
                                <label class="form-label">{$fieldFormatted}</label>
                                <select name="{$field}" class="form-control">
                                    <option value="">Select {$fieldFormatted}</option>
{$options}
                                </select>
                                @error('{$field}')
                                    <p style="color: red;">{{ \$message }}</p>
                                @enderror
EOT;
            } else {
                $inputType = 'text';
                if (Str::endsWith($field, 'email')) {
                    $inputType = 'email';
                } elseif (in_array($columnType, ['integer', 'bigint', 'smallint', 'tinyint', 'unsignedBigInteger'])) {
                    $inputType = 'number';
                } elseif ($columnType === 'boolean') {
                    $formFields .= <<<EOT
                                <div class="header-elements mt-3">
                                    <label class="custom-control custom-switch custom-control-right">
                                        <input type="hidden" name="{$field}" value="0">
                                        <input type="checkbox" name="{$field}" class="custom-control-input" value="1" {{ old('{$field}') == '1' ? 'checked' : '' }}>
                                        <span class="custom-control-label">{$fieldFormatted}</span>
                                    </label>
                                </div>
                                @error('{$field}')
                                    <p style="color: red;">{{ \$message }}</p>
                                @enderror
EOT;
                    continue;
                }
                $formFields .= <<<EOT
                                <label class="form-label">{$fieldFormatted}</label>
                                <input type="{$inputType}" class="form-control" name="{$field}" value="{{ old('{$field}') }}" placeholder="{$fieldFormatted}">
                                @error('{$field}')
                                    <p style="color: red;">{{ \$message }}</p>
                                @enderror
EOT;
            }
        }

        $createTemplate = <<<EOT
@extends('layouts.admin')
@section('title', '{$name}')
@section('content')
    <!-- Content area -->
    <div class="content">
        <div class="d-inline-flex gap-2">
            <a href="{{ route('{$pluralName}.index', [], false) }}" class="btn btn-outline-secondary">Back</a>
        </div>
        <div class="card mt-2">
            <div class="card-body">
                <form action="{{ route('{$pluralName}.store', [], false) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <fieldset class="mb-3">
                        <legend class="text-uppercase font-size-sm font-weight-bold">{$name}</legend>
                        <div class="form-group row">
                            <div class="card-body">
{$formFields}
                            </div>
                        </div>
                    </fieldset>
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /content area -->
@endsection
EOT;
        File::put(resource_path("views/{$pluralName}/create.blade.php"), $createTemplate);

        // edit.blade.php
        $editFormFields = '';
        foreach ($fields as $field) {
            $fieldFormatted = ucwords(str_replace('_', ' ', $field));
            $columnType = Schema::getColumnType($tableName, $field); // Har bir maydon uchun columnType aniqlanadi
            if (isset($enumFields[$field])) {
                $options = '';
                foreach ($enumFields[$field]['values'] as $value) {
                    $options .= "<option value=\"{$value}\" {{ old('{$field}', \$model->{$field}) == '{$value}' ? 'selected' : '' }}>{$value}</option>\n";
                }
                $editFormFields .= <<<EOT
                                <label class="form-label">{$fieldFormatted}</label>
                                <select name="{$field}" class="form-control">
                                    <option value="">Select {$fieldFormatted}</option>
{$options}
                                </select>
                                @error('{$field}')
                                    <p style="color: red;">{{ \$message }}</p>
                                @enderror
EOT;
            } else {
                $inputType = 'text';
                if (Str::endsWith($field, 'email')) {
                    $inputType = 'email';
                } elseif (in_array($columnType, ['integer', 'bigint', 'smallint', 'tinyint', 'unsignedBigInteger'])) {
                    $inputType = 'number';
                } elseif ($columnType === 'boolean') {
                    $editFormFields .= <<<EOT
                                <div class="header-elements mt-3">
                                    <label class="custom-control custom-switch custom-control-right">
                                        <input type="hidden" name="{$field}" value="0">
                                        <input type="checkbox" name="{$field}" class="custom-control-input" value="1" {{ old('{$field}', \$model->{$field}) == '1' ? 'checked' : '' }}>
                                        <span class="custom-control-label">{$fieldFormatted}</span>
                                    </label>
                                </div>
                                @error('{$field}')
                                    <p style="color: red;">{{ \$message }}</p>
                                @enderror
EOT;
                    continue;
                }
                $editFormFields .= <<<EOT
                                <label class="col-form-label col-lg-2">{$fieldFormatted}</label>
                                <input type="{$inputType}" class="form-control" name="{$field}" value="{{ old('{$field}', \$model->{$field} ?? '') }}" placeholder="{$fieldFormatted}">
                                @error('{$field}')
                                    <p style="color: red;">{{ \$message }}</p>
                                @enderror
EOT;
            }
        }

        $editTemplate = <<<EOT
@extends('layouts.admin')
@section('title', '{$name}')
@section('content')
    <!-- Content area -->
    <div class="content">
        <div class="d-inline-flex gap-2">
            <a href="{{ route('{$pluralName}.index', [], false) }}" class="btn btn-outline-secondary">Back</a>
        </div>
        <div class="card mt-2">
            <div class="card-body">
                <form action="{{ route('{$pluralName}.update', \$model->id, false) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <fieldset class="mb-3">
                        <legend class="text-uppercase font-size-sm font-weight-bold">{$name}</legend>
                        <div class="form-group row">
                            <div class="card-body">
{$editFormFields}
                            </div>
                        </div>
                    </fieldset>
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /content area -->
@endsection
EOT;
        File::put(resource_path("views/{$pluralName}/edit.blade.php"), $editTemplate);

        // show.blade.php
        $showFields = '';
        foreach ($fields as $field) {
            $fieldFormatted = ucwords(str_replace('_', ' ', $field));
            $showFields .= <<<EOT
                        <tr>
                            <th>{$fieldFormatted}</th>
                            <td>{{ \$model->{$field} }}</td>
                        </tr>
EOT;
        }

        $showTemplate = <<<EOT
@extends('layouts.admin')
@section('title', '{$name}')
@section('content')
    <!-- Content area -->
    <div class="content">
        <div class="d-inline-flex gap-2">
            <a href="{{ route('{$pluralName}.index', [], false) }}" class="btn btn-outline-secondary">Back</a>
            <a href="{{ route('{$pluralName}.edit', \$model->id, false) }}" class="btn btn-outline-secondary ml-2">Edit</a>
        </div>
        <div class="card mt-2">
            <div class="card-body">
                <table class="table text-nowrap table-bordered">
                    <tbody>
{$showFields}
                        <tr>
                            <th>Created</th>
                            <th>{{ \$model->created_at->format('d-m-Y, H:i') }}</th>
                        </tr>
                        <tr>
                            <th>Updated</th>
                            <th>{{ \$model->updated_at->format('d-m-Y, H:i') }}</th>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- /content area -->
@endsection
EOT;
        File::put(resource_path("views/{$pluralName}/show.blade.php"), $showTemplate);
    }

    protected function updateRoutes($name)
    {
        $pluralName = Str::plural(strtolower($name));
        $routeEntry = "Route::resource('{$pluralName}', App\\Http\\Controllers\\{$name}\\{$name}Controller::class);\n";
        File::append(base_path('routes/web.php'), $routeEntry);
    }
}
