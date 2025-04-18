<?php

namespace AdminCrud\CrudGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use App\Models\PermissionGroup;
use App\Models\Permission;

class SetupRolePermission extends Command
{
    protected $signature = 'make:role-permission';
    protected $description = 'RBAC tizimini modellar, migratsiyalar va ruxsatnomalar bilan sozlash';

    public function handle()
    {
        $this->info('RBAC tizimini sozlamoqda...');

        $this->createModels();
        $this->createMigrations();
        $this->runMigrations();
        $this->createControllers();
        $this->generatePermissionsFromRoutes();
        $this->createViews();
        $this->createLayout();
        $this->addRoutesToWeb();

        $this->info('RBAC tizimi muvaffaqiyatli sozlandi!');
    }

    protected function createModels()
    {
        $models = [
            'Role' => <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected \$fillable = ['name', 'is_active'];

    public function permissions()
    {
        return \$this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users()
    {
        return \$this->belongsToMany(User::class, 'user_roles');
    }
}
PHP,
            'PermissionGroup' => <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionGroup extends Model
{
    protected \$fillable = ['name', 'is_active'];

    public function permissions()
    {
        return \$this->hasMany(Permission::class);
    }
}
PHP,
            'Permission' => <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected \$fillable = ['name', 'key', 'permission_group_id', 'is_active'];

    public function group()
    {
        return \$this->belongsTo(PermissionGroup::class, 'permission_group_id');
    }

    public function roles()
    {
        return \$this->belongsToMany(Role::class, 'role_permissions');
    }
}
PHP,
            'RolePermission' => <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected \$fillable = ['role_id', 'permission_id'];
}
PHP,
            'UserRole' => <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    protected \$fillable = ['user_id', 'role_id'];
}
PHP,
        ];

        foreach ($models as $model => $content) {
            $path = app_path("Models/{$model}.php");
            if (!File::exists($path)) {
                File::put($path, $content);
                $this->info("{$model} modeli yaratildi.");
            } else {
                $this->info("{$model} modeli allaqachon mavjud.");
            }
        }
    }

    protected function createMigrations()
    {
        $migrations = [
            'create_roles_table' => <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRolesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint \$table) {
                \$table->id();
                \$table->string('name');
                \$table->boolean('is_active')->default(true);
                \$table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('roles');
    }
}
PHP,
            'create_permission_groups_table' => <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionGroupsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('permission_groups')) {
            Schema::create('permission_groups', function (Blueprint \$table) {
                \$table->id();
                \$table->string('name');
                \$table->boolean('is_active')->default(true);
                \$table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('permission_groups');
    }
}
PHP,
            'create_permissions_table' => <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint \$table) {
                \$table->id();
                \$table->string('name');
                \$table->string('key')->unique();
                \$table->foreignId('permission_group_id')->constrained()->onDelete('cascade');
                \$table->boolean('is_active')->default(true);
                \$table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('permissions');
    }
}
PHP,
            'create_role_permissions_table' => <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRolePermissionsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint \$table) {
                \$table->id();
                \$table->foreignId('role_id')->constrained()->onDelete('cascade');
                \$table->foreignId('permission_id')->constrained()->onDelete('cascade');
                \$table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('role_permissions');
    }
}
PHP,
            'create_user_roles_table' => <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserRolesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('user_roles')) {
            Schema::create('user_roles', function (Blueprint \$table) {
                \$table->id();
                \$table->foreignId('user_id')->constrained()->onDelete('cascade');
                \$table->foreignId('role_id')->constrained()->onDelete('cascade');
                \$table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('user_roles');
    }
}
PHP,
        ];

        $timestamp = now()->format('Y_m_d_His');
        foreach ($migrations as $name => $content) {
            $migrationName = preg_replace('/[^a-z0-9]/i', '_', $name);
            $existingMigrations = glob(database_path('migrations/*_' . $migrationName . '.php'));
            if (empty($existingMigrations)) {
                $fileName = database_path("migrations/{$timestamp}_{$name}.php");
                File::put($fileName, $content);
                $this->info("Migratsiya yaratildi: {$name}");
                $timestamp++;
            } else {
                $this->info("Migratsiya {$name} allaqachon mavjud.");
            }
        }
    }

    protected function runMigrations()
    {
        $this->info('Migratsiyalarni ishga tushirmoqda...');
        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->info(Artisan::output());
        } catch (\Exception $e) {
            $this->error('Migratsiya xatosi: ' . $e->getMessage());
            $this->info('Sozlashni migratsiyalarsiz davom ettirmoqda...');
        }
    }

    protected function createControllers()
    {
        $controllers = [
            'RoleController' => <<<PHP
<?php

namespace App\Http\Controllers\Rbac;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request \$request)
    {
        \$query = Role::query();

        if (\$request->filled('name')) {
            \$query->where('name', 'like', '%' . \$request->name . '%');
        }

        if (\$request->filled('is_active')) {
            \$query->where('is_active', \$request->is_active);
        }

        \$models = \$query->paginate(10)->withQueryString();

        return view('rbac.roles.index', compact('models'));
    }

    public function create()
    {
        \$permissionGroups = \App\Models\PermissionGroup::with('permissions')->where('is_active', true)->get();
        return view('rbac.roles.create', compact('permissionGroups'));
    }

    public function store(Request \$request)
    {
        \$request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
            'is_active' => 'required|boolean',
        ]);

        \$role = Role::create([
            'name' => \$request->name,
            'is_active' => \$request->is_active,
        ]);

        if (\$request->permissions) {
            \$role->permissions()->sync(\$request->permissions);
        }

        return redirect()->route('roles.index')->with('notification', 'Role created successfully.');
    }

    public function show(Role \$role)
    {
        \$model = \$role;
        return view('rbac.roles.show', compact('model'));
    }

    public function edit(Role \$role)
    {
        \$model = \$role;
        \$permissionGroups = \App\Models\PermissionGroup::with('permissions')->where('is_active', true)->get();
        return view('rbac.roles.edit', compact('model', 'permissionGroups'));
    }

    public function update(Request \$request, Role \$role)
    {
        \$request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
            'is_active' => 'required|boolean',
        ]);

        \$role->update([
            'name' => \$request->name,
            'is_active' => \$request->is_active,
        ]);

        \$role->permissions()->sync(\$request->permissions ?? []);

        return redirect()->route('roles.index')->with('notification', 'Role updated successfully.');
    }

    public function destroy(Role \$role)
    {
        \$role->delete();
        return redirect()->route('roles.index')->with('notification', 'Role deleted successfully.');
    }
}
PHP,
            'UserController' => <<<PHP
<?php

namespace App\Http\Controllers\Rbac;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request \$request)
    {
        \$query = User::with('roles');

        if (\$request->filled('name')) {
            \$query->where('name', 'like', '%' . \$request->name . '%');
        }

        if (\$request->filled('email')) {
            \$query->where('email', 'like', '%' . \$request->email . '%');
        }

        \$models = \$query->paginate(10)->withQueryString();

        return view('rbac.users.index', compact('models'));
    }

    public function assignRole(User \$user)
    {
        \$roles = Role::all();
        return view('rbac.users.assign-role', compact('user', 'roles'));
    }

    public function storeRole(Request \$request, User \$user)
    {
        \$request->validate([
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ]);

        \$user->roles()->sync(\$request->roles ?? []);

        return redirect()->route('users.index')->with('notification', 'Roles assigned successfully.');
    }
}
PHP,
            'PermissionGroupController' => <<<PHP
<?php

namespace App\Http\Controllers\Rbac;

use App\Http\Controllers\Controller;
use App\Models\PermissionGroup;
use Illuminate\Http\Request;

class PermissionGroupController extends Controller
{
    public function index(Request \$request)
    {
        \$query = PermissionGroup::query();

        if (\$request->filled('name')) {
            \$query->where('name', 'like', '%' . \$request->name . '%');
        }

        if (\$request->filled('is_active')) {
            \$query->where('is_active', \$request->is_active);
        }

        \$models = \$query->paginate(10)->withQueryString();

        return view('rbac.permission-groups.index', compact('models'));
    }

    public function create()
    {
        return view('rbac.permission-groups.create');
    }

    public function store(Request \$request)
    {
        \$request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        PermissionGroup::create([
            'name' => strtoupper(\$request->name),
            'is_active' => \$request->is_active,
        ]);

        return redirect()->route('permission-groups.index')->with('notification', 'Permission group created successfully.');
    }

    public function show(PermissionGroup \$permissionGroup)
    {
        \$model = \$permissionGroup;
        return view('rbac.permission-groups.show', compact('model'));
    }

    public function edit(PermissionGroup \$permissionGroup)
    {
        \$model = \$permissionGroup;
        return view('rbac.permission-groups.edit', compact('model'));
    }

    public function update(Request \$request, PermissionGroup \$permissionGroup)
    {
        \$request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        \$permissionGroup->update([
            'name' => strtoupper(\$request->name),
            'is_active' => \$request->is_active,
        ]);

        return redirect()->route('permission-groups.index')->with('notification', 'Permission group updated successfully.');
    }

    public function destroy(PermissionGroup \$permissionGroup)
    {
        \$permissionGroup->delete();
        return redirect()->route('permission-groups.index')->with('notification', 'Permission group deleted successfully.');
    }
}
PHP,
            'PermissionController' => <<<PHP
<?php

namespace App\Http\Controllers\Rbac;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\PermissionGroup;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request \$request)
    {
        \$query = Permission::with('group');

        if (\$request->filled('name')) {
            \$query->where('name', 'like', '%' . \$request->name . '%');
        }

        if (\$request->filled('is_active')) {
            \$query->where('is_active', \$request->is_active);
        }
        
        if (\$request->filled('permission_group_id')) {
            \$query->where('permission_group_id', \$request->permission_group_id);
        }

        \$models = \$query->paginate(10)->withQueryString();

        \$permissionGroups = PermissionGroup::where('is_active', true)->get();

        return view('rbac.permissions.index', compact('models'), compact('permissionGroups'));
    }

    public function create()
    {
        \$permissionGroups = PermissionGroup::where('is_active', true)->get();
        return view('rbac.permissions.create', compact('permissionGroups'));
    }

    public function store(Request \$request)
    {
        \$request->validate([
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:255|unique:permissions',
            'permission_group_id' => 'required|exists:permission_groups,id',
            'is_active' => 'required|boolean',
        ]);

        Permission::create([
            'name' => \$request->name,
            'key' => \$request->key,
            'permission_group_id' => \$request->permission_group_id,
            'is_active' => \$request->is_active,
        ]);

        return redirect()->route('permissions.index')->with('notification', 'Permission created successfully.');
    }

    public function show(Permission \$permission)
    {
        \$model = \$permission;
        return view('rbac.permissions.show', compact('model'));
    }

    public function edit(Permission \$permission)
    {
        \$model = \$permission;
        // \$permissionGroups = PermissionGroup::where('is_active', true)->get();
        return view('rbac.permissions.edit', compact('model'));
    }

    public function update(Request \$request, Permission \$permission)
    {
        \$request->validate([
            'name' => 'required|string|max:255',
            // 'key' => 'required|string|max:255|unique:permissions,key,' . \$permission->id,
            // 'permission_group_id' => 'required|exists:permission_groups,id',
            'is_active' => 'required|boolean',
        ]);

        \$permission->update([
            'name' => \$request->name,
            // 'key' => \$request->key,
            // 'permission_group_id' => \$request->permission_group_id,
            'is_active' => \$request->is_active,
        ]);

        return redirect()->route('permissions.index')->with('notification', 'Permission updated successfully.');
    }

    public function destroy(Permission \$permission)
    {
        \$permission->delete();
        return redirect()->route('permissions.index')->with('notification', 'Permission deleted successfully.');
    }
}
PHP,
        ];

        $controllerPath = app_path('Http/Controllers/Rbac');
        File::ensureDirectoryExists($controllerPath);

        foreach ($controllers as $name => $content) {
            $path = "{$controllerPath}/{$name}.php";
            if (!File::exists($path)) {
                File::put($path, $content);
                $this->info("{$name} kontrolleri yaratildi.");
            } else {
                $this->info("{$name} kontrolleri allaqachon mavjud.");
            }
        }
    }
    protected function generatePermissionsFromRoutes()
    {
        $this->info('Route-lardan ruxsatnomalarni generatsiya qilmoqda...');

        if (!Schema::hasTable('permission_groups') || !Schema::hasTable('permissions')) {
            $this->warn(string: 'Ruxsat jadvallari topilmadi. Ruxsat generatsiyasi o\'tkazib yuborildi.');
            return;
        }

        $routes = Route::getRoutes()->getRoutes();
        $groupedRoutes = [];

        foreach ($routes as $route) {
            $name = $route->getName();
            if ($name && strpos($name, '.') !== false) {
                [$prefix, $action] = explode('.', $name);
                $groupedRoutes[$prefix][] = $name;
            }
        }

        foreach ($groupedRoutes as $prefix => $routeNames) {
            $group = PermissionGroup::firstOrCreate(
                ['name' => strtoupper($prefix)],
                ['is_active' => true]
            );

            foreach ($routeNames as $routeName) {
                $nameParts = explode('.', $routeName);
                $permissionName = ucwords(str_replace('.', ' ', $routeName));
                Permission::firstOrCreate(
                    ['key' => $routeName],
                    [
                        'name' => $permissionName,
                        'permission_group_id' => $group->id,
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->info('Ruxsatnomalar muvaffaqiyatli generatsiya qilindi.');
    }
    
    protected function createLayout()
    {
        $layoutPath = resource_path('views/layouts/admin.blade.php');
        if (!File::exists($layoutPath)) {
            File::put($layoutPath, $this->getAdminLayoutContent());
            $this->info('Admin layout yaratildi.');
        } else {
            $this->info('Admin layout allaqachon mavjud.');
        }
    }

    protected function createViews()
    {
        $views = [
            'rbac/roles/index.blade.php' => $this->getRoleIndexView(),
            'rbac/roles/create.blade.php' => $this->getRoleCreateView(),
            'rbac/roles/edit.blade.php' => $this->getRoleEditView(),
            'rbac/roles/show.blade.php' => $this->getRoleShowView(),
            'rbac/users/index.blade.php' => $this->getUserIndexView(),
            'rbac/users/assign-role.blade.php' => $this->getUserAssignRoleView(),
            'rbac/permission-groups/index.blade.php' => $this->getPermissionGroupIndexView(),
            'rbac/permission-groups/create.blade.php' => $this->getPermissionGroupCreateView(),
            'rbac/permission-groups/edit.blade.php' => $this->getPermissionGroupEditView(),
            'rbac/permission-groups/show.blade.php' => $this->getPermissionGroupShowView(),
            'rbac/permissions/index.blade.php' => $this->getPermissionIndexView(),
            'rbac/permissions/create.blade.php' => $this->getPermissionCreateView(),
            'rbac/permissions/edit.blade.php' => $this->getPermissionEditView(),
            'rbac/permissions/show.blade.php' => $this->getPermissionShowView(),
        ];

        foreach ($views as $path => $content) {
            $fullPath = resource_path("views/{$path}");
            File::ensureDirectoryExists(dirname($fullPath));
            File::put($fullPath, $content);
            $this->info("View yaratildi: {$path}");
        }
    }

    protected function getAdminLayoutContent()
    {
        return <<<BLADE
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
        <div class="d-flex flex-1 d-lg-none">
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar-mobile">
                <i class="icon-paragraph-justify3"></i>
            </button>
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
        <div class="collapse navbar-collapse order-2 order-lg-1" id="navbar-mobile"></div>
        <ul class="navbar-nav flex-row order-1 order-lg-2 flex-1 flex-lg-0 justify-content-end align-items-center">
            <li class="nav-item nav-item-dropdown-lg dropdown dropdown-user h-100">
                <a href="#" class="navbar-nav-link navbar-nav-link-toggler dropdown-toggle d-inline-flex align-items-center h-100" data-toggle="dropdown">
                    <span class="d-none d-lg-inline-block" style="text-transform: capitalize;">{{ app()->getLocale() }}</span>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a href="/" class="dropdown-item {{ app()->getLocale() == 'ru' ? 'active' : '' }}">RU</a>
                </div>
            </li>
            <li class="nav-item nav-item-dropdown-lg dropdown dropdown-user h-100">
                <a href="#" class="navbar-nav-link navbar-nav-link-toggler dropdown-toggle d-inline-flex align-items-center h-100" data-toggle="dropdown">
                    <span class="d-none d-lg-inline-block">{{ auth()->user()->roles->first()->name ?? 'User' }}</span>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a href="/" class="dropdown-item"><i class="icon-user-plus"></i> My profile</a>
                    <form action="/logout" method="post">
                        @csrf
                        <button class="dropdown-item"><i class="icon-switch2"></i> Logout</button>
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
    }

    protected function getRoleIndexView()
    {
        return <<<BLADE
@extends('layouts.admin')
@section('title', 'Role')
@section('content')
<div class="content">
    <div class="row">
        <div class="col-xl-12">
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
                        <a href="{{ route('roles.create', [], false) }}" class="btn btn-teal">
                            <i class="icon-plus3 icon-1x mr-1"></i>Add Role
                        </a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table text-nowrap table-bordered">
                        <thead>
                            <tr>
                                <th class="text-center" width="3%">#</th>
                                <th class="text-center">Name</th>
                                <th class="text-center">Status</th>
                                <th class="text-center" width="5%">Actions</th>
                            </tr>
                            <form action="{{ route('roles.index', [], false) }}" method="get">
                                <tr>
                                    <th class="text-center"></th>
                                    <th class="text-center">
                                        <input type="text" class="form-control" name="name" placeholder="Name" value="{{ old('name', request('name')) }}">
                                    </th>
                                    <th class="text-center">
                                        <select class="form-control" name="is_active">
                                            <option value="">All</option>
                                            <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>Not Active</option>
                                        </select>
                                    </th>
                                    <th class="text-center">
                                        <button class="btn btn-teal">Search</button>
                                    </th>
                                </tr>
                            </form>
                        </thead>
                        <tbody>
                            @forelse (\$models as \$model)
                                <tr>
                                    <td>{{ (\$models->currentPage() - 1) * \$models->perPage() + \$loop->iteration }}</td>
                                    <td>{{ \$model->name }}</td>
                                    <td><span class="badge badge-{{ \$model->is_active ? 'primary' : 'danger' }}">
                                                {{ \$model->is_active ? 'Active' : 'Not Active' }}
                                            </span></td>
                                    <td>
                                        <div class="d-inline-flex gap-2">
                                            <a href="{{ route('roles.show', \$model->id, false) }}" class="btn btn-outline-info">
                                                <i class="icon-eye8"></i>
                                            </a>
                                            <a href="{{ route('roles.edit', \$model->id, false) }}" class="btn btn-outline-success ml-2">
                                                <i class="icon-pencil3"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger ml-2" data-toggle="modal" data-target="#modal_full{{ \$model->id }}">
                                                <i class="icon-trash"></i>
                                            </button>
                                            <div id="modal_full{{ \$model->id }}" class="modal fade" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered modal-sm">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <button type="button" class="close" data-dismiss="modal">×</button>
                                                        </div>
                                                        <form action="{{ route('roles.destroy', \$model->id, false) }}" method="post">
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
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center">No data found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if (isset(\$models) && \$models->hasPages())
                {{ \$models->links() }}
            @endif
        </div>
    </div>
</div>
@endsection
BLADE;
    }

    protected function getRoleCreateView()
    {
        return <<<BLADE
@extends('layouts.admin')
@section('title', 'Role')
@section('content')
<div class="content">
    <div class="d-inline-flex gap-2">
        <a href="{{ route('roles.index', [], false) }}" class="btn btn-outline-secondary">Back</a>
    </div>
    <div class="card mt-2">
        <div class="card-body">
            <form action="{{ route('roles.store', [], false) }}" method="POST">
                @csrf
                <fieldset class="mb-3">
                    <legend class="text-uppercase font-size-sm font-weight-bold">Role</legend>
                    <div class="form-group row">
                        <div class="card-body">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" value="{{ old('name') }}" placeholder="Role name">
                            @error('name')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
                            <label class="form-label mt-2">Permissions</label>
                            <div class="row">
                                @forelse (\$permissionGroups as \$group)
                                    <div class="col-md-12">
                                        <h5>{{ \$group->name }}</h5>
                                        <div class="row">
                                            @foreach (\$group->permissions as \$permission)
                                                <div class="col-md-4 mb-2">
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input" name="permissions[]" value="{{ \$permission->id }}"
                                                            id="permission_{{ \$permission->id }}">
                                                        <label class="form-check-label" for="permission_{{ \$permission->id }}">{{ \$permission->name }}</label>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <p>No permission groups found.</p>
                                @endforelse
                            </div>
                            @error('permissions')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
                            <div class="header-elements mt-3">
                                <label class="custom-control custom-switch custom-control-right">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" class="custom-control-input" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
                                    <span class="custom-control-label">Active</span>
                                </label>
                            </div>
                            @error('is_active')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
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
@endsection
BLADE;
    }

    protected function getRoleEditView()
    {
        return <<<BLADE
@extends('layouts.admin')
@section('title', 'Role')
@section('content')
<div class="content">
    <div class="d-inline-flex gap-2">
        <a href="{{ route('roles.index', [], false) }}" class="btn btn-outline-secondary">Back</a>
    </div>
    <div class="card mt-2">
        <div class="card-body">
            <form action="{{ route('roles.update', \$model->id, false) }}" method="POST">
                @csrf
                @method('PUT')
                <fieldset class="mb-3">
                    <legend class="text-uppercase font-size-sm font-weight-bold">Role</legend>
                    <div class="form-group row">
                        <div class="card-body">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" value="{{ old('name', \$model->name) }}" placeholder="Role name">
                            @error('name')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
                            <label class="form-label mt-2">Permissions</label>
                            <div class="row">
                                @forelse (\$permissionGroups as \$group)
                                    <div class="col-md-12">
                                        <h5>{{ \$group->name }}</h5>
                                        <div class="row">
                                            @foreach (\$group->permissions as \$permission)
                                                <div class="col-md-4 mb-2">
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input" name="permissions[]" value="{{ \$permission->id }}"
                                                            id="permission_{{ \$permission->id }}"
                                                            {{ \$model->permissions->contains(\$permission->id) ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="permission_{{ \$permission->id }}">{{ \$permission->name }}</label>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <p>No permission groups found.</p>
                                @endforelse
                            </div>
                            @error('permissions')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
                            <div class="header-elements mt-3">
                                <label class="custom-control custom-switch custom-control-right">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" class="custom-control-input" value="1" {{ old('is_active', \$model->is_active) ? 'checked' : '' }}>
                                    <span class="custom-control-label">Active</span>
                                </label>
                            </div>
                            @error('is_active')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
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
@endsection
BLADE;
    }

    protected function getRoleShowView()
    {
        return <<<BLADE
@extends('layouts.admin')
@section('title', 'Role')
@section('content')
<div class="content">
    <div class="d-inline-flex gap-2">
        <a href="{{ route('roles.index', [], false) }}" class="btn btn-outline-secondary">Back</a>
        <a href="{{ route('roles.edit', \$model->id, false) }}" class="btn btn-outline-secondary ml-2">Edit</a>
    </div>
    <div class="card mt-2">
        <div class="card-body">
            <table class="table text-nowrap table-bordered">
                <tbody>
                    <tr>
                        <th>Name</th>
                        <td>{{ \$model->name }}</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge badge-{{ \$model->is_active ? 'primary' : 'danger' }}">
                                                {{ \$model->is_active ? 'Active' : 'Not Active' }}
                                            </span>
                                        </td>
                    </tr>
                    <tr>
                        <th>Permissions</th>
                        <td>
                            @forelse (\$model->permissions as \$permission)
                                <span class="badge badge-info">{{ \$permission->name }}</span>
                            @empty
                                <span>No permissions assigned</span>
                            @endforelse
                        </td>
                    </tr>
                    <tr>
                        <th>Created</th>
                        <td>{{ \$model->created_at->format('d-m-Y, H:i') }}</td>
                    </tr>
                    <tr>
                        <th>Updated</th>
                        <td>{{ \$model->updated_at->format('d-m-Y, H:i') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
BLADE;
    }

    protected function getUserIndexView()
    {
        return <<<BLADE
@extends('layouts.admin')
@section('title', 'User')
@section('content')
<div class="content">
    <div class="row">
        <div class="col-xl-12">
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
                </div>
                <div class="table-responsive">
                    <table class="table text-nowrap table-bordered">
                        <thead>
                            <tr>
                                <th class="text-center" width="3%">#</th>
                                <th class="text-center">Name</th>
                                <th class="text-center">Email</th>
                                <th class="text-center">Roles</th>
                                <th class="text-center" width="5%">Actions</th>
                            </tr>
                            <form action="{{ route('users.index', [], false) }}" method="get">
                                <tr>
                                    <th class="text-center"></th>
                                    <th class="text-center">
                                        <input type="text" class="form-control" name="name" placeholder="Name"
                                            value="{{ old('name', request('name')) }}">
                                    </th>
                                    <th class="text-center">
                                        <input type="text" class="form-control" name="email" placeholder="Email"
                                            value="{{ old('email', request('email')) }}">
                                    </th>
                                    <th></th>
                                    <th class="text-center">
                                        <button class="btn btn-teal">Search</button>
                                    </th>
                                </tr>
                            </form>
                        </thead>
                        <tbody>
                            @forelse (\$models as \$model)
                                <tr>
                                    <td>{{ (\$models->currentPage() - 1) * \$models->perPage() + \$loop->iteration }}</td>
                                    <td>{{ \$model->name }}</td>
                                    <td>{{ \$model->email }}</td>
                                    <td>
                                            @foreach (\$model->roles as \$item)
                                                <span class="badge badge-primary">
                                                    {{ \$item->name }}
                                                </span>
                                            @endforeach
                                        </td>
                                    <td>
                                        <div class="d-inline-flex gap-2">
                                            <a href="{{ route('users.assign-role', \$model->id, false) }}" class="btn btn-outline-success">
                                                <i class="icon-user-plus"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center">No data found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if (isset(\$models) && \$models->hasPages())
                {{ \$models->links() }}
            @endif
        </div>
    </div>
</div>
@endsection
BLADE;
    }

    protected function getUserAssignRoleView()
    {
        return <<<BLADE
@extends('layouts.admin')
@section('title', 'User')
@section('content')
<div class="content">
    <div class="d-inline-flex gap-2">
        <a href="{{ route('users.index', [], false) }}" class="btn btn-outline-secondary">Back</a>
    </div>
    <div class="card mt-2">
        <div class="card-body">
            <form action="{{ route('users.assign-role', \$user->id, false) }}" method="POST">
                @csrf
                <fieldset class="mb-3">
                    <legend class="text-uppercase font-size-sm font-weight-bold">Assign Role to User</legend>
                    <div class="form-group row">
                        <div class="card-body">
                            <label class="form-label">User</label>
                            <input type="text" class="form-control" value="{{ \$user->name }}" disabled>
                            <label class="form-label">Roles</label>
                            <div class="form-group">
                                <select multiple="multiple" class="form-control select" name="roles[]" data-fouc>
                                    <optgroup label="Active Roles">
                                        @forelse (\$roles->where('is_active', true) as \$role)
                                            <option value="{{ \$role->id }}" {{ \$user->roles && \$user->roles->contains(\$role->id) ? 'selected' : '' }}>
                                                {{ \$role->name }}
                                            </option>
                                        @empty
                                            <option disabled>No active roles found</option>
                                        @endforelse
                                    </optgroup>
                                    <optgroup label="Not Active Roles">
                                        @forelse (\$roles->where('is_active', false) as \$role)
                                            <option value="{{ \$role->id }}" {{ \$user->roles && \$user->roles->contains(\$role->id) ? 'selected' : '' }}>
                                                {{ \$role->name }}
                                            </option>
                                        @empty
                                            <option disabled>No Not Active roles found</option>
                                        @endforelse
                                    </optgroup>
                                </select>
                            </div>
                            @error('roles')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
                        </div>
                    </div>
                </fieldset>
                <div class="text-right">
                    <button type="submit" class="btn btn-primary">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
BLADE;
    }

    protected function getPermissionGroupIndexView()
    {
        return <<<BLADE
@extends('layouts.admin')
@section('title', 'Permission Group')
@section('content')
<div class="content">
    <div class="row">
        <div class="col-xl-12">
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
                        
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table text-nowrap table-bordered">
                        <thead>
                            <tr>
                                <th class="text-center" width="3%">#</th>
                                <th class="text-center">Name</th>
                                <th class="text-center">Status</th>
                                <th class="text-center" width="5%">Actions</th>
                            </tr>
                            <form action="{{ route('permission-groups.index', [], false) }}" method="get">
                                <tr>
                                    <th class="text-center"></th>
                                    <th class="text-center">
                                        <input type="text" class="form-control" name="name" placeholder="Name" value="{{ old('name', request('name')) }}">
                                    </th>
                                    <th class="text-center">
                                        <select class="form-control" name="is_active">
                                            <option value="">All</option>
                                            <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>Not Active</option>
                                        </select>
                                    </th>
                                    <th class="text-center">
                                        <button class="btn btn-teal">Search</button>
                                    </th>
                                </tr>
                            </form>
                        </thead>
                        <tbody>
                            @forelse (\$models as \$model)
                                <tr>
                                    <td>{{ (\$models->currentPage() - 1) * \$models->perPage() + \$loop->iteration }}</td>
                                    <td>{{ \$model->name }}</td>
                                    <td><span class="badge badge-{{ \$model->is_active ? 'primary' : 'danger' }}">
                                                {{ \$model->is_active ? 'Active' : 'Not Active' }}
                                            </span></td>
                                    <td>
                                        <div class="d-inline-flex gap-2">
                                            <a href="{{ route('permission-groups.show', \$model->id, false) }}" class="btn btn-outline-info">
                                                <i class="icon-eye8"></i>
                                            </a>
                                            <a href="{{ route('permission-groups.edit', \$model->id, false) }}" class="btn btn-outline-success ml-2">
                                                <i class="icon-pencil3"></i>
                                            </a>
                                            
                                            <div id="modal_full{{ \$model->id }}" class="modal fade" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered modal-sm">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <button type="button" class="close" data-dismiss="modal">×</button>
                                                        </div>
                                                        <form action="{{ route('permission-groups.destroy', \$model->id, false) }}" method="post">
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
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center">No data found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if (isset(\$models) && \$models->hasPages())
                {{ \$models->links() }}
            @endif
        </div>
    </div>
</div>
@endsection
BLADE;
    }

    protected function getPermissionGroupCreateView()
    {
        return <<<BLADE
@extends('layouts.admin')
@section('title', 'Permission Group')
@section('content')
<div class="content">
    <div class="d-inline-flex gap-2">
        <a href="{{ route('permission-groups.index', [], false) }}" class="btn btn-outline-secondary">Back</a>
    </div>
    <div class="card mt-2">
        <div class="card-body">
            <form action="{{ route('permission-groups.store', [], false) }}" method="POST">
                @csrf
                <fieldset class="mb-3">
                    <legend class="text-uppercase font-size-sm font-weight-bold">Permission Group</legend>
                    <div class="form-group row">
                        <div class="card-body">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" value="{{ old('name') }}" placeholder="Permission group name">
                            @error('name')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
                            <div class="header-elements mt-3">
                                <label class="custom-control custom-switch custom-control-right">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" class="custom-control-input" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
                                    <span class="custom-control-label">Active</span>
                                </label>
                            </div>
                            @error('is_active')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
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
@endsection
BLADE;
    }

    protected function getPermissionGroupEditView()
    {
        return <<<BLADE
@extends('layouts.admin')
@section('title', 'Permission Group')
@section('content')
<div class="content">
    <div class="d-inline-flex gap-2">
        <a href="{{ route('permission-groups.index', [], false) }}" class="btn btn-outline-secondary">Back</a>
    </div>
    <div class="card mt-2">
        <div class="card-body">
            <form action="{{ route('permission-groups.update', \$model->id, false) }}" method="POST">
                @csrf
                @method('PUT')
                <fieldset class="mb-3">
                    <legend class="text-uppercase font-size-sm font-weight-bold">Permission Group</legend>
                    <div class="form-group row">
                        <div class="card-body">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" value="{{ old('name', \$model->name) }}" placeholder="Permission group name">
                            @error('name')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
                            <div class="header-elements mt-3">
                                <label class="custom-control custom-switch custom-control-right">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" class="custom-control-input" value="1" {{ old('is_active', \$model->is_active) ? 'checked' : '' }}>
                                    <span class="custom-control-label">Active</span>
                                </label>
                            </div>
                            @error('is_active')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
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
@endsection
BLADE;
    }

    protected function getPermissionGroupShowView()
    {
        return <<<BLADE
@extends('layouts.admin')
@section('title', 'Permission Group')
@section('content')
<div class="content">
    <div class="d-inline-flex gap-2">
        <a href="{{ route('permission-groups.index', [], false) }}" class="btn btn-outline-secondary">Back</a>
        <a href="{{ route('permission-groups.edit', \$model->id, false) }}" class="btn btn-outline-secondary ml-2">Edit</a>
    </div>
    <div class="card mt-2">
        <div class="card-body">
            <table class="table text-nowrap table-bordered">
                <tbody>
                    <tr>
                        <th>Name</th>
                        <td>{{ \$model->name }}</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><span class="badge badge-{{ \$model->is_active ? 'primary' : 'danger' }}">
                                                {{ \$model->is_active ? 'Active' : 'Not Active' }}
                                            </span></td>
                    </tr>
                    <tr>
                        <th>Permissions</th>
                        <td>
                            @forelse (\$model->permissions as \$permission)
                                <span class="badge badge-info">{{ \$permission->name }}</span>
                            @empty
                                <span>No permissions assigned</span>
                            @endforelse
                        </td>
                    </tr>
                    <tr>
                        <th>Created</th>
                        <td>{{ \$model->created_at->format('d-m-Y, H:i') }}</td>
                    </tr>
                    <tr>
                        <th>Updated</th>
                        <td>{{ \$model->updated_at->format('d-m-Y, H:i') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
BLADE;
    }

    protected function getPermissionIndexView()
    {
        return <<<BLADE
@extends('layouts.admin')
@section('title', 'Permission')
@section('content')
<div class="content">
    <div class="row">
        <div class="col-xl-12">
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
                        
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table text-nowrap table-bordered">
                        <thead>
                            <tr>
                                <th class="text-center" width="3%">#</th>
                                <th class="text-center">Name</th>
                                <th class="text-center">Key</th>
                                <th class="text-center">Group</th>
                                <th class="text-center">Status</th>
                                <th class="text-center" width="5%">Actions</th>
                            </tr>
                            <form action="{{ route('permissions.index', [], false) }}" method="get">
                                <tr>
                                    <th class="text-center"></th>
                                    <th class="text-center">
                                        <input type="text" class="form-control" name="name" placeholder="Name" value="{{ old('name', request('name')) }}">
                                    </th>
                                    <th class="text-center"></th>
                                    <th class="text-center">
                                            <select class="form-control" name="permission_group_id">
                                                <option value="">All</option>
                                                @foreach (\$permissionGroups as \$permissionGroup)
                                                    <option value="{{ \$permissionGroup->id }}"
                                                        {{ request('permission_group_id') == \$permissionGroup->id ? 'selected' : '' }}>
                                                        {{ \$permissionGroup->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                    </th>
                                    <th class="text-center">
                                        <select class="form-control" name="is_active">
                                            <option value="">All</option>
                                            <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>Not Active</option>
                                        </select>
                                    </th>
                                    <th class="text-center">
                                        <button class="btn btn-teal">Search</button>
                                    </th>
                                </tr>
                            </form>
                        </thead>
                        <tbody>
                            @forelse (\$models as \$model)
                                <tr>
                                    <td>{{ (\$models->currentPage() - 1) * \$models->perPage() + \$loop->iteration }}</td>
                                    <td>{{ \$model->name }}</td>
                                    <td>{{ \$model->key }}</td>
                                    <td>{{ \$model->group->name }}</td>
                                    <td><span class="badge badge-{{ \$model->is_active ? 'primary' : 'danger' }}">
                                                {{ \$model->is_active ? 'Active' : 'Not Active' }}
                                            </span></td>
                                    <td>
                                        <div class="d-inline-flex gap-2">
                                            <a href="{{ route('permissions.show', \$model->id, false) }}" class="btn btn-outline-info">
                                                <i class="icon-eye8"></i>
                                            </a>
                                            <a href="{{ route('permissions.edit', \$model->id, false) }}" class="btn btn-outline-success ml-2">
                                                <i class="icon-pencil3"></i>
                                            </a>
                                            
                                            <div id="modal_full{{ \$model->id }}" class="modal fade" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered modal-sm">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <button type="button" class="close" data-dismiss="modal">×</button>
                                                        </div>
                                                        <form action="{{ route('permissions.destroy', \$model->id, false) }}" method="post">
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
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">No data found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if (isset(\$models) && \$models->hasPages())
                {{ \$models->links() }}
            @endif
        </div>
    </div>
</div>
@endsection
BLADE;
    }

    protected function getPermissionCreateView()
    {
        return <<<BLADE
@extends('layouts.admin')
@section('title', 'Permission')
@section('content')
<div class="content">
    <div class="d-inline-flex gap-2">
        <a href="{{ route('permissions.index', [], false) }}" class="btn btn-outline-secondary">Back</a>
    </div>
    <div class="card mt-2">
        <div class="card-body">
            <form action="{{ route('permissions.store', [], false) }}" method="POST">
                @csrf
                <fieldset class="mb-3">
                    <legend class="text-uppercase font-size-sm font-weight-bold">Permission</legend>
                    <div class="form-group row">
                        <div class="card-body">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" value="{{ old('name') }}" placeholder="Permission name">
                            @error('name')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
                            <label class="form-label">Permission Group</label>
                            
                            <div class="header-elements mt-3">
                                <label class="custom-control custom-switch custom-control-right">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" class="custom-control-input" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
                                    <span class="custom-control-label">Active</span>
                                </label>
                            </div>
                            @error('is_active')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
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
@endsection
BLADE;
    }

    protected function getPermissionEditView()
    {
        return <<<BLADE
@extends('layouts.admin')
@section('title', 'Permission')
@section('content')
<div class="content">
    <div class="d-inline-flex gap-2">
        <a href="{{ route('permissions.index', [], false) }}" class="btn btn-outline-secondary">Back</a>
    </div>
    <div class="card mt-2">
        <div class="card-body">
            <form action="{{ route('permissions.update', \$model->id, false) }}" method="POST">
                @csrf
                @method('PUT')
                <fieldset class="mb-3">
                    <legend class="text-uppercase font-size-sm font-weight-bold">Permission</legend>
                    <div class="form-group row">
                        <div class="card-body">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" value="{{ old('name', \$model->name) }}" placeholder="Permission name">
                            @error('name')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
                            <div class="header-elements mt-3">
                                <label class="custom-control custom-switch custom-control-right">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" class="custom-control-input" value="1" {{ old('is_active', \$model->is_active) ? 'checked' : '' }}>
                                    <span class="custom-control-label">Active</span>
                                </label>
                            </div>
                            @error('is_active')
                                <p style="color: red;">{{ \$message }}</p>
                            @enderror
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
@endsection
BLADE;
    }

    protected function getPermissionShowView()
    {
        return <<<BLADE
@extends('layouts.admin')
@section('title', 'Permission')
@section('content')
<div class="content">
    <div class="d-inline-flex gap-2">
        <a href="{{ route('permissions.index', [], false) }}" class="btn btn-outline-secondary">Back</a>
        <a href="{{ route('permissions.edit', \$model->id, false) }}" class="btn btn-outline-secondary ml-2">Edit</a>
    </div>
    <div class="card mt-2">
        <div class="card-body">
            <table class="table text-nowrap table-bordered">
                <tbody>
                    <tr>
                        <th>Name</th>
                        <td>{{ \$model->name }}</td>
                    </tr>
                    <tr>
                        <th>Key</th>
                        <td>{{ \$model->key }}</td>
                    </tr>
                    <tr>
                        <th>Permission Group</th>
                        <td>{{ \$model->group->name }}</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><span class="badge badge-{{ \$model->is_active ? 'primary' : 'danger' }}">
                                                {{ \$model->is_active ? 'Active' : 'Not Active' }}
                                            </span></td>
                    </tr>
                    <tr>
                        <th>Created</th>
                        <td>{{ \$model->created_at->format('d-m-Y, H:i') }}</td>
                    </tr>
                    <tr>
                        <th>Updated</th>
                        <td>{{ \$model->updated_at->format('d-m-Y, H:i') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
BLADE;
    }
    protected function addRoutesToWeb()
    {
        $webFilePath = base_path('routes/web.php');
        $routeContent = <<<PHP
Route::prefix('rbac')->group(function () {
    // Role
    Route::resource('roles', \App\Http\Controllers\Rbac\RoleController::class);

    // User
    Route::get('users', [\App\Http\Controllers\Rbac\UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/assign-role', [\App\Http\Controllers\Rbac\UserController::class, 'assignRole'])->name('users.assign-role');
    Route::post('users/{user}/assign-role', [\App\Http\Controllers\Rbac\UserController::class, 'storeRole'])->name('users.assign-role');

    // PermissionGroup
    Route::resource('permission-groups', \App\Http\Controllers\Rbac\PermissionGroupController::class);

    // Permission 
    Route::resource('permissions', \App\Http\Controllers\Rbac\PermissionController::class);
});
PHP;

        // web.php faylini o'qish
        $currentContent = File::exists($webFilePath) ? File::get($webFilePath) : '<?php';

        // Agar RBAC routelari allaqachon mavjud bo'lsa, qo'shmaslik
        if (strpos($currentContent, "Route::prefix('rbac')") !== false) {
            $this->info('RBAC routelari allaqachon web.php faylida mavjud.');
            return;
        }

        // Yangi routelarni qo'shish uchun faylni yangilash
        $newContent = rtrim($currentContent, "\n") . "\n\n" . $routeContent . "\n";

        // Faylga yozish
        File::put($webFilePath, $newContent);
        $this->info('RBAC routelari web.php fayliga muvaffaqiyatli qo\'shildi.');
    }
}
