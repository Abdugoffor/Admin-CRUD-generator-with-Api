<?php

namespace AdminCrud\CrudGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeApiAuthCommand extends Command
{
    protected $signature = 'make:api-auth';
    protected $description = 'Scaffold API authentication with registration, login, logout, and profile editing';

    public function handle()
    {
        $this->info('Scaffolding API authentication...');

        // Sanctum o‘rnatilganligini tekshirish
        if (!file_exists(base_path('vendor/laravel/sanctum'))) {
            $this->call('install:sanctum');
        }

        // Request sinflarini yaratish
        $this->createRequests();

        // Kontrolerlarni yaratish
        $this->createControllers();

        // API marshrutlarini qo‘shish
        $this->addRoutes();

        $this->info('API authentication scaffolding installed successfully.');
        $this->info('You can now use /api/register, /api/login, /api/logout, and /api/profile endpoints.');
    }

    protected function createRequests()
    {
        $requests = [
            'Api/Auth/RegisterRequest' => $this->getRegisterRequestStub(),
            'Api/Auth/LoginRequest' => $this->getLoginRequestStub(),
            'Api/Auth/ProfileRequest' => $this->getProfileRequestStub(),
        ];

        foreach ($requests as $path => $content) {
            $filePath = app_path("Http/Requests/{$path}.php");
            File::ensureDirectoryExists(dirname($filePath));
            File::put($filePath, $content);
        }
    }

    protected function createControllers()
    {
        $controllers = [
            'Api/Auth/RegisterController' => $this->getRegisterControllerStub(),
            'Api/Auth/LoginController' => $this->getLoginControllerStub(),
            'Api/Auth/ProfileController' => $this->getProfileControllerStub(),
        ];

        foreach ($controllers as $path => $content) {
            $filePath = app_path("Http/Controllers/{$path}.php");
            File::ensureDirectoryExists(dirname($filePath));
            File::put($filePath, $content);
        }
    }

    protected function addRoutes()
    {
        $routeContent = File::get(base_path('routes/api.php'));
        $routeContent .= "\n" . $this->getRouteStub();
        File::put(base_path('routes/api.php'), $routeContent);
    }

    protected function getRegisterRequestStub()
    {
        return '<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            "name" => ["required", "string", "max:255"],
            "email" => ["required", "string", "email", "max:255", "unique:users"],
            "password" => ["required", "string", "min:8", "confirmed"],
        ];
    }
}
';
    }

    protected function getLoginRequestStub()
    {
        return '<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            "email" => ["required", "email"],
            "password" => ["required"],
        ];
    }
}
';
    }

    protected function getProfileRequestStub()
    {
        return '<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ProfileRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            "name" => ["required", "string", "max:255"],
            "email" => ["required", "string", "email", "max:255", "unique:users,email," . Auth::id()],
            "password" => ["nullable", "string", "min:8", "confirmed"],
        ];
    }
}
';
    }

    protected function getRegisterControllerStub()
    {
        return '<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $data = $request->only(["name", "email", "password"]);
        $data["password"] = Hash::make($data["password"]);

        $user = User::create($data);
        $token = $user->createToken("auth_token")->plainTextToken;

        return response()->json([
            "user" => $user,
            "token" => $token,
        ], 201);
    }
}
';
    }

    protected function getLoginControllerStub()
    {
        return '<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->only("email", "password");

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken("auth_token")->plainTextToken;

            return response()->json([
                "user" => $user,
                "token" => $token,
            ]);
        }

        return response()->json([
            "error" => "The provided credentials do not match our records.",
        ], 401);
    }

    public function logout()
    {
        auth()->user()->tokens()->delete();

        return response()->json(["message" => "Logged out successfully"]);
    }
}
';
    }

    protected function getProfileControllerStub()
    {
        return '<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ProfileRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show()
    {
        return response()->json(auth()->user());
    }

    public function update(ProfileRequest $request)
    {
        $user = Auth::user();
        $data = $request->only(["name", "email"]);

        if ($request->filled("password")) {
            $data["password"] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            "message" => "Profile updated successfully",
            "user" => $user,
        ]);
    }
}
';
    }

    protected function getRouteStub()
    {
        return 'use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ProfileController;

Route::post("/register", [RegisterController::class, "register"]);
Route::post("/login", [LoginController::class, "login"]);

Route::middleware("auth:sanctum")->group(function () {
    Route::post("/logout", [LoginController::class, "logout"]);
    Route::get("/profile", [ProfileController::class, "show"]);
    Route::patch("/profile", [ProfileController::class, "update"]);
});
';
    }
}
