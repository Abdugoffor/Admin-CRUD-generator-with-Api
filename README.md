# Laravel CRUD Generator (Admin + API + Auth)

Laravel loyihalarda **Admin CRUD**, **API CRUD**, **Auth**, va **Role Permission** tizimini tez yaratish uchun generator.

Generator quyidagilarni avtomatik yaratadi:

- Admin CRUD (Controller, Request, Views)
- API CRUD (Controller, Resource)
- Auth tizimi
- API Auth
- Select (enum) maydonlar
- Ko‘p tilli `json/jsonb` maydonlar
- Boolean maydonlar (switch)
- File upload maydonlari

---

# O‘rnatish

Composer orqali paketni o‘rnating:

```bash
composer require abdugoffor/admin-crud-generator-with-api:dev-main
```

---

# Ishlatish

### 1. Model va migratsiya yaratish

```bash
php artisan make:model Post -m
```

---

### 2. Migratsiyada maydonlarni yozish

```php
public function up()
{
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('description');
        $table->timestamps();
    });
}
```

---

### 3. Migratsiyani ishga tushirish

```bash
php artisan migrate
```

---

# Admin CRUD yaratish

```bash
php artisan make:crud Post
```

---

# API CRUD yaratish

```bash
php artisan make:api-crud Post
```

---

# Auth yaratish

### Admin Auth

```bash
php artisan make:auth
```

### API Auth

```bash
php artisan make:api-auth
```

---

# Select (Enum) maydonlar

Agar maydon **select** bo‘lib chiqishi kerak bo‘lsa model ichida `enumValues` yoziladi.

```php
public $enumValues = [
    'status' => [
        'values' => ['draft', 'published', 'archived'],
        'default' => 'draft',
    ],
];
```

Generator bu maydonni **select input** qilib chiqaradi.

---

# Ko‘p tilli maydonlar (JSON / JSONB)

Agar maydon ko‘p tilli bo‘lsa `casts` ichida `array` qilib yoziladi.

```php
protected $casts = [
    'title' => 'array',
    'description' => 'array',
];
```

Misol uchun bazada:

```json
{
  "uz": "Sarlavha",
  "ru": "Заголовок",
  "en": "Title"
}
```

---

# Boolean maydonlar

Agar maydon `true/false` bo‘lsa:

```php
protected $casts = [
    'is_active' => 'boolean',
];
```

Generator bu maydonni **switch / checkbox** qilib chiqaradi.

---

# File upload maydonlari

Agar modelda file upload bo‘lsa:

```php
protected $fileFields = ['photo'];

public function getFileFields(): array
{
    return $this->fileFields;
}
```

Shunda generator bu maydonni **file input** qilib yaratadi.

---

# To‘liq Model Namuna

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    protected $fillable = [
        'title',
        'description',
        'photo',
        'content',
        'video_link',
        'footer_text',
        'date',
        'is_active'
    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'content' => 'array',
        'footer_text' => 'array',
        'is_active' => 'boolean',
    ];

    protected $fileFields = ['photo'];

    public function getFileFields(): array
    {
        return $this->fileFields;
    }
}
```

---

# Loyihani ishga tushirish

```bash
php artisan serve
```

So‘ngra brauzerda oching:

```
http://127.0.0.1:8000/posts
```

CRUD tizimi ishlashni boshlaydi.

---

# Xulosa

Generator modelga qarab avtomatik ishlaydi:

- `enumValues` → select input
- `casts array` → ko‘p tilli maydon
- `casts boolean` → switch/checkbox
- `fileFields` → file upload input

Bu orqali Laravel admin va API CRUD tizimlarini juda tez yaratish mumkin.