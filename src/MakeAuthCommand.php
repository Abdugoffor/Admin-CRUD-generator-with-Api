<?php

namespace AdminCrud\CrudGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeAuthCommand extends Command
{
    protected $signature = 'make:auth';
    protected $description = 'Scaffold web authentication with registration, login, logout, and profile editing';

    public function handle()
    {
        $this->info('Scaffolding web authentication...');

        // Request sinflarini yaratish
        $this->createRequests();

        // Kontrolerlarni yaratish
        $this->createControllers();

        // Blade shablonlarini yaratish
        $this->createViews();

        // CSS faylini yaratish
        File::ensureDirectoryExists(public_path('auth/css'));
        File::put(public_path('auth/css/style.css'), $this->getCssStub());

        // Marshrutlarni qo‘shish
        $this->addRoutes();

        $this->info('Web authentication scaffolding installed successfully.');
        $this->info('You can now access /register, /login, /profile, and /dashboard routes.');
    }

    protected function createRequests()
    {
        $requests = [
            'Auth/RegisterRequest' => $this->getRegisterRequestStub(),
            'Auth/LoginRequest' => $this->getLoginRequestStub(),
            'Auth/ProfileRequest' => $this->getProfileRequestStub(),
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
            'Auth/RegisterController' => $this->getRegisterControllerStub(),
            'Auth/LoginController' => $this->getLoginControllerStub(),
            'Auth/ProfileController' => $this->getProfileControllerStub(),
            'HomeController' => $this->getHomeControllerStub(),
        ];

        foreach ($controllers as $path => $content) {
            $filePath = app_path("Http/Controllers/{$path}.php");
            File::ensureDirectoryExists(dirname($filePath));
            File::put($filePath, $content);
        }
    }

    protected function createViews()
    {
        $views = [
            'auth/login.blade.php' => $this->getLoginViewStub(),
            'auth/register.blade.php' => $this->getRegisterViewStub(),
            'auth/profile.blade.php' => $this->getProfileViewStub(),
            'layouts/app.blade.php' => $this->getLayoutViewStub(),
            'home.blade.php' => $this->getHomeViewStub(),
        ];

        foreach ($views as $path => $content) {
            $filePath = resource_path("views/{$path}");
            File::ensureDirectoryExists(dirname($filePath));
            File::put($filePath, $content);
        }
    }

    protected function addRoutes()
    {
        $routeContent = File::get(base_path('routes/web.php'));
        $routeContent .= "\n" . $this->getRouteStub();
        File::put(base_path('routes/web.php'), $routeContent);
    }

    protected function getRegisterRequestStub()
    {
        return '<?php

namespace App\Http\Requests\Auth;

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

namespace App\Http\Requests\Auth;

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

namespace App\Http\Requests\Auth;

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

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view("auth.register");
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->only(["name", "email", "password"]);
        $data["password"] = Hash::make($data["password"]);

        $user = User::create($data);

        Auth::login($user);

        return redirect()->route("home");
    }
}
';
    }

    protected function getLoginControllerStub()
    {
        return '<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view("auth.login");
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only("email", "password");

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->intended(route("home"));
        }

        return back()->withErrors([
            "email" => "The provided credentials do not match our records.",
        ])->onlyInput("email");
    }

    public function logout()
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect("/login");
    }
}
';
    }

    protected function getProfileControllerStub()
    {
        return '<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ProfileRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function showProfileForm()
    {
        return view("auth.profile", ["user" => Auth::user()]);
    }

    public function update(ProfileRequest $request)
    {
        $user = Auth::user();
        $data = $request->only(["name", "email"]);

        if ($request->filled("password")) {
            $data["password"] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route("profile")->with("status", "Profile updated successfully.");
    }
}
';
    }

    protected function getHomeControllerStub()
    {
        return '<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        return view("home");
    }
}
';
    }

    protected function getLoginViewStub()
    {
        return '@extends("layouts.app")

@section("content")
    <div class="container">
        <h2>Sign in to your account</h2>
        <form action="{{ route(\'login\') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="{{ old(\'email\') }}">
                @error("email")
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password">
                @error("password")
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit">Sign in</button>
        </form>
        <p style="text-align: center; margin-top: 15px;">
            <a href="{{ route(\'register\') }}">Don\'t have an account? Register</a>
        </p>
    </div>
@endsection
';
    }

    protected function getRegisterViewStub()
    {
        return '@extends("layouts.app")

@section("content")
    <div class="container">
        <h2>Create your account</h2>
        <form action="{{ route(\'register\') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old(\'name\') }}">
                @error("name")
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="{{ old(\'email\') }}">
                @error("email")
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password">
                @error("password")
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation">
            </div>
            <button type="submit">Register</button>
        </form>
        <p style="text-align: center; margin-top: 15px;">
            <a href="{{ route(\'login\') }}">Already have an account? Sign in</a>
        </p>
    </div>
@endsection
';
    }

    protected function getProfileViewStub()
    {
        return '@extends("layouts.app")

@section("content")
    <div class="container">
        <h2>Edit your profile</h2>
        @if (session("status"))
            <div class="alert">{{ session("status") }}</div>
        @endif
        <form action="{{ route(\'profile.update\') }}" method="POST">
            @csrf
            @method("PATCH")
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old(\'name\', $user->name) }}">
                @error("name")
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="{{ old(\'email\', $user->email) }}">
                @error("email")
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="password">New Password (optional)</label>
                <input type="password" id="password" name="password">
                @error("password")
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirm New Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation">
            </div>
            <button type="submit">Update Profile</button>
        </form>
    </div>
@endsection
';
    }

    protected function getLayoutViewStub()
    {
        return '<!DOCTYPE html>
<html lang="{{ str_replace("_", "-", app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config("app.name", "Laravel") }}</title>
    <link href="{{ asset("auth/css/style.css") }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <nav>
            <a href="{{ route("home") }}" class="logo">{{ config("app.name") }}</a>
            @auth
                <div class="dropdown">
                    <button class="dropdown-toggle">{{ Auth::user()->name }}</button>
                    <div class="dropdown-menu">
                        <a href="{{ route("profile") }}">Profile</a>
                        <form method="POST" action="{{ route("logout") }}">
                            @csrf
                            <button type="submit" class="dropdown-item">Logout</button>
                        </form>
                    </div>
                </div>
            @else
                <div class="nav-links">
                    <a href="{{ route("login") }}">Login</a>
                    <a href="{{ route("register") }}">Register</a>
                </div>
            @endauth
        </nav>
    </header>
    <main>
        @yield("content")
    </main>
    <footer>
        <p>© {{ date("Y") }} {{ config("app.name") }}. All rights reserved.</p>
    </footer>
    <script>
        document.querySelectorAll(".dropdown-toggle").forEach(button => {
            button.addEventListener("click", () => {
                const menu = button.nextElementSibling;
                menu.style.display = menu.style.display === "block" ? "none" : "block";
            });
        });
        document.addEventListener("click", (event) => {
            if (!event.target.closest(".dropdown")) {
                document.querySelectorAll(".dropdown-menu").forEach(menu => {
                    menu.style.display = "none";
                });
            }
        });
    </script>
</body>
</html>
';
    }

    protected function getHomeViewStub()
    {
        return '@extends("layouts.app")

@section("content")
    <div class="container">
        <h2>Welcome to {{ config("app.name") }}</h2>
        <p>This is your dashboard.</p>
    </div>
@endsection
';
    }

    protected function getRouteStub()
    {
        return 'use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\HomeController;

Route::get("/login", [LoginController::class, "showLoginForm"])->name("login");
Route::post("/login", [LoginController::class, "login"]);
Route::post("/logout", [LoginController::class, "logout"])->name("logout");

Route::get("/register", [RegisterController::class, "showRegistrationForm"])->name("register");
Route::post("/register", [RegisterController::class, "register"]);

Route::middleware("auth")->group(function () {
    Route::get("/profile", [ProfileController::class, "showProfileForm"])->name("profile");
    Route::patch("/profile", [ProfileController::class, "update"])->name("profile.update");
    Route::get("/dashboard", [HomeController::class, "index"])->name("home");
});
';
    }

    protected function getCssStub()
    {
        return '/* Zamonaviy font va umumiy sozlamalar */
* {
    box-sizing: border-box;
}

html, body {
    font-family: "Poppins", sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f8f9fa;
    color: #333;
    line-height: 1.6;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    overflow-x: hidden;
}

/* Header dizayni */
header {
    background: #252b36;
    color: #fff;
    padding: 0;
}

nav {
    max-width: 1200px;
    margin: 0 auto;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

nav .logo {
    font-size: 1.5em;
    font-weight: 600;
    color: #fff;
    text-decoration: none;
}

.nav-links {
    display: flex;
    align-items: center;
}

.nav-links a {
    color: #fff;
    margin-left: 20px;
    font-size: 0.95em;
    font-weight: 400;
    transition: color 0.3s ease;
}

.nav-links a:hover {
    color: #a8b2d1;
}

/* Dropdown dizayni */
.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-toggle {
    background: none;
    border: none;
    color: #fff;
    font-size: 0.95em;
    font-weight: 400;
    cursor: pointer;
    padding: 10px 15px;
    border-radius: 6px;
    transition: background 0.3s ease;
}

.dropdown-toggle:hover {
    background: #2e3644;
}

.dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    background: #2e3644;
    min-width: 180px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    z-index: 1;
    border-radius: 6px;
    margin-top: 8px;
    overflow: hidden;
}

.dropdown-menu a, .dropdown-menu button {
    color: #fff;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
    background: none;
    border: none;
    width: 100%;
    text-align: left;
    font-size: 0.9em;
    cursor: pointer;
    transition: background 0.3s ease;
}

.dropdown-menu a:hover, .dropdown-menu button:hover {
    background: #252b36;
}

.dropdown-item {
    font-weight: 400;
}

/* Main content */
main {
    flex: 1;
}

.container {
    width: 100%;
    max-width: 600px;
    margin: 50px auto;
    padding: 30px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

h2 {
    text-align: center;
    margin-bottom: 25px;
    font-size: 1.8em;
    font-weight: 600;
    color: #252b36;
}

/* Form elementlari */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 0.95em;
    font-weight: 500;
    color: #252b36;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.95em;
    transition: border-color 0.3s ease;
}

.form-group input:focus {
    outline: none;
    border-color: #4f5b93;
    box-shadow: 0 0 5px rgba(79, 91, 147, 0.2);
}

.form-group .error {
    color: #dc3545;
    font-size: 0.85em;
    margin-top: 6px;
}

/* Tugmalar */
button {
    width: 100%;
    padding: 12px;
    background: #4f5b93;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 1em;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.3s ease;
}

button:hover {
    background: #3b4477;
}

/* Havolalar */
a {
    color: #4f5b93;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

a:hover {
    color: #3b4477;
}

/* Alert xabarlari */
.alert {
    color: #28a745;
    text-align: center;
    margin-bottom: 20px;
    font-size: 0.95em;
    font-weight: 500;
}

/* Footer dizayni */
footer {
    background: #252b36;
    color: #fff;
    text-align: center;
    padding: 20px;
    font-size: 0.9em;
    font-weight: 400;
}
';
    }
}
