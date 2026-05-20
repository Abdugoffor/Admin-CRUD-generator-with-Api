<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MakeApiCrud extends Command
{
    protected $signature = 'make:api-crud {name : Model nomi (masalan: Language)} {--force : Mavjud fayllarni qayta yozish}';

    protected $description = 'Mavjud model asosida modulli API CRUD (Controller/Service/Repository/Request/Response) generatsiya qiladi; route -> routes/api.php, binding -> AppServiceProvider';

    protected array $translatable = [];
    protected array $booleans = [];
    protected array $files = [];
    protected array $enums = [];
    protected string $table = '';
    protected bool $tableExists = false;

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));

        $modelClass = "App\\Models\\{$name}";

        if (!class_exists($modelClass)) {
            $this->error("Model {$modelClass} topilmadi. Avval model va migration yarating.");
            return self::FAILURE;
        }

        $model = new $modelClass();

        $fields = array_values($model->getFillable());

        if (empty($fields)) {
            $this->error("{$name} modelida \$fillable maydonlari yo'q.");
            return self::FAILURE;
        }

        $this->table = $model->getTable();

        $this->tableExists = Schema::hasTable($this->table);

        if (!$this->tableExists) {
            $this->warn("'{$this->table}' jadvali mavjud emas — validatsiya qoidalari taxminiy bo'ladi. Aniqlik uchun `php artisan migrate` ni bajaring.");
        }

        $this->translatable = $this->detectTranslatable($model);

        $this->booleans = $this->detectBooleans($model);

        $this->files = $this->detectFiles($model);

        $this->enums = $this->detectEnums($model);

        $module = "{$name}Service";

        $base = app_path("Modules/{$module}");

        $ns = "App\\Modules\\{$module}";

        $plural = Str::plural(Str::snake($name));

        $tokens = [
            '{{Model}}' => $name,
            '{{module_ns}}' => $ns,
            '{{module}}' => $module,
            '{{plural}}' => $plural,
            '{{var}}' => Str::camel($name),
        ];

        $targets = [
            "Controller/{$name}Controller.php" => $this->controllerStub(),
            "Service/{$name}Service.php" => $this->serviceStub($fields),
            "Repository/{$name}RepositoryInterface.php" => $this->repositoryInterfaceStub(),
            "Repository/{$name}Repository.php" => $this->repositoryStub($fields),
            "Request/Store{$name}Request.php" => $this->requestStub($fields, false),
            "Request/Update{$name}Request.php" => $this->requestStub($fields, true),
            "Response/{$name}Resource.php" => $this->resourceStub($fields),
        ];

        foreach ($this->enums as $field => $meta) {
            $enumName = Str::studly($field) . 'Enum';
            $targets["Enum/{$enumName}.php"] = $this->enumStub($enumName, $meta['values']);
        }

        foreach ($targets as $relative => $content) {
            
            $path = $base . '/' . $relative;
            File::ensureDirectoryExists(dirname($path));

            if (File::exists($path) && !$this->option('force')) {
                $this->line("  <fg=yellow>o'tkazib yuborildi</> {$relative} (mavjud, --force ishlating)");
                continue;
            }

            File::put($path, strtr($content, $tokens));
            $this->line("  <fg=green>yaratildi</>     Modules/{$module}/{$relative}");
        }

        $this->registerBinding($ns, $name);
        $this->registerRoutes($ns, $name, $plural);

        $this->newLine();
        $this->info("API CRUD '{$name}' uchun tayyor.");
        $this->line("Endpoint'lar:  /api/{$plural}  (apiResource — routes/api.php)");
        $this->line('Keyingi qadam: `php artisan optimize:clear` so\'ng `php artisan route:list --path=api`');

        if (!empty($this->files)) {
            $this->line('Fayl maydonlari: ' . implode(', ', $this->files) . ' — App\\Services\\FileUploadService orqali public/uploaded/ ga yuklanadi (storage:link shart emas).');
        }

        return self::SUCCESS;
    }

    // ============================================================
    // MAYDONLARNI ANIQLASH
    // ============================================================

    protected function detectTranslatable($model): array
    {
        if (property_exists($model, 'translatable') && is_array($model->translatable)) {
            return array_values($model->translatable);
        }

        $result = [];
        foreach ($model->getCasts() as $field => $type) {
            if (
                in_array($type, ['array', 'json', 'collection'], true)
                && in_array($field, $model->getFillable(), true)
            ) {
                $result[] = $field;
            }
        }

        return $result;
    }

    protected function detectBooleans($model): array
    {
        $result = [];
        foreach ($model->getCasts() as $field => $type) {
            if (in_array($type, ['boolean', 'bool'], true)) {
                $result[] = $field;
            }
        }

        return $result;
    }

    protected function detectFiles($model): array
    {
        if (method_exists($model, 'getFileFields')) {
            $fields = $model->getFileFields();
            if (is_array($fields)) {
                return array_values($fields);
            }
        }

        return [];
    }

    /**
     * @return array<string, array{values: string[], default: ?string}>
     */
    protected function detectEnums($model): array
    {
        $result = [];

        if (property_exists($model, 'enumValues') && is_array($model->enumValues)) {
            foreach ($model->enumValues as $field => $meta) {
                $result[$field] = [
                    'values' => array_values($meta['values'] ?? []),
                    'default' => $meta['default'] ?? null,
                ];
            }
        }

        // Native PHP enum cast'lari (casts: ['status' => StatusEnum::class])
        foreach ($model->getCasts() as $field => $type) {
            if (is_string($type) && enum_exists($type) && !isset($result[$field])) {
                $cases = $type::cases();
                $result[$field] = [
                    'values' => array_map(fn($c) => $c->value ?? $c->name, $cases),
                    'default' => null,
                ];
            }
        }

        return $result;
    }

    protected ?array $columns = null;

    protected function columnType(string $field): string
    {
        if (!$this->tableExists) {
            return Str::endsWith($field, '_id') ? 'integer' : 'string';
        }

        try {
            return Schema::getColumnType($this->table, $field);
        } catch (\Throwable) {
            return 'string';
        }
    }

    protected function isNullable(string $field): bool
    {
        if (!$this->tableExists) {
            return false;
        }

        if ($this->columns === null) {
            try {
                $this->columns = collect(Schema::getColumns($this->table))
                    ->keyBy('name')
                    ->all();
            } catch (\Throwable) {
                $this->columns = [];
            }
        }

        return (bool) ($this->columns[$field]['nullable'] ?? false);
    }

    /**
     * Maydonning "borligi" qoidasi:
     *   - Update  -> doimo 'sometimes' (qisman yangilash)
     *   - Store   -> ustun nullable bo'lsa 'nullable', aks holda 'required'
     */
    protected function presence(string $field, bool $forUpdate): string
    {
        if ($forUpdate) {
            return 'sometimes';
        }

        return $this->isNullable($field) ? 'nullable' : 'required';
    }

    // ============================================================
    // VALIDATSIYA QOIDALARI
    // ============================================================

    protected function buildRules(array $fields, bool $forUpdate): string
    {
        $lines = [];

        foreach ($fields as $field) {
            // Ko'p tilli (JSON array) maydon: faqat 'array' qoidasi,
            // har til bo'yicha tekshiruv validateTranslation() da (merge).
            if (in_array($field, $this->translatable, true)) {
                $presence = $forUpdate ? 'sometimes' : 'required';
                $lines[] = "            '{$field}' => '{$presence}|array',";
                continue;
            }

            if (in_array($field, $this->files, true)) {
                $presence = $this->presence($field, $forUpdate);
                $lines[] = "            '{$field}' => '{$presence}|file|max:10240',";
                continue;
            }

            $rule = $this->presence($field, $forUpdate);

            if (isset($this->enums[$field])) {
                $rule .= '|in:' . implode(',', $this->enums[$field]['values']);
            } elseif (in_array($field, $this->booleans, true)) {
                $rule .= '|boolean';
            } elseif (Str::endsWith($field, '_id')) {
                $rule .= '|integer';
            } else {
                switch ($this->columnType($field)) {
                    case 'int':
                    case 'integer':
                    case 'bigint':
                    case 'smallint':
                    case 'tinyint':
                        $rule .= '|integer';
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
                    case 'text':
                    case 'longtext':
                        $rule .= '|string';
                        break;
                    default:
                        $rule .= '|string|max:255';
                        if (Str::endsWith($field, 'email')) {
                            $rule .= '|email';
                        }
                        break;
                }
            }

            $lines[] = "            '{$field}' => '{$rule}',";
        }

        return implode("\n", $lines);
    }

    /**
     * Ko'p tilli maydonlar uchun array_merge(validateTranslation(...)) bloki.
     * Bo'sh string qaytsa — model'da tarjima maydoni yo'q.
     */
    protected function buildTranslationMerge(bool $forUpdate): string
    {
        if (empty($this->translatable)) {
            return '';
        }

        $calls = [];
        foreach ($this->translatable as $tf) {
            $calls[] = $forUpdate
                ? "            validateTranslation('{$tf}', false),"
                : "            validateTranslation('{$tf}'),";
        }

        $calls = implode("\n", $calls);

        return <<<PHP

        \$rules = array_merge(
            \$rules,
{$calls}
        );
PHP;
    }

    protected function buildMessages(array $fields): string
    {
        $groups = [];

        foreach ($fields as $field) {
            $label = strtolower(str_replace('_', ' ', $field));
            $lines = [];

            if (in_array($field, $this->translatable, true)) {
                $lines[] = "            '{$field}.required'   => getTranslation('{$label} is required'),";
                $lines[] = "            '{$field}.array'      => getTranslation('{$label} must be an array'),";
                $lines[] = "            '{$field}.*.required' => getTranslation('{$label} translation is required'),";
                $lines[] = "            '{$field}.*.string'   => getTranslation('{$label} translation must be a string'),";
                $groups[] = implode("\n", $lines);
                continue;
            }

            $required = !$this->isNullable($field);

            if (in_array($field, $this->files, true)) {
                if ($required) {
                    $lines[] = "            '{$field}.required' => getTranslation('{$label} is required'),";
                }
                $lines[] = "            '{$field}.file'     => getTranslation('{$label} must be a file'),";
                $lines[] = "            '{$field}.max'      => getTranslation('{$label} must not exceed 10 MB'),";
                $groups[] = implode("\n", $lines);
                continue;
            }

            if ($required) {
                $lines[] = "            '{$field}.required' => getTranslation('{$label} is required'),";
            }

            if (isset($this->enums[$field])) {
                $lines[] = "            '{$field}.in'       => getTranslation('{$label} has an invalid value'),";
            } elseif (in_array($field, $this->booleans, true)) {
                $lines[] = "            '{$field}.boolean'  => getTranslation('{$label} must be true or false'),";
            } elseif (Str::endsWith($field, '_id') || in_array($this->columnType($field), ['int', 'integer', 'bigint', 'smallint', 'tinyint'], true)) {
                $lines[] = "            '{$field}.integer'  => getTranslation('{$label} must be an integer'),";
            } elseif (in_array($this->columnType($field), ['decimal', 'float', 'double'], true)) {
                $lines[] = "            '{$field}.numeric'  => getTranslation('{$label} must be a number'),";
            } elseif (in_array($this->columnType($field), ['date', 'datetime', 'timestamp'], true)) {
                $lines[] = "            '{$field}.date'     => getTranslation('{$label} must be a valid date'),";
            } else {
                $lines[] = "            '{$field}.string'   => getTranslation('{$label} must be a string'),";
                if (!in_array($this->columnType($field), ['text', 'longtext'], true)) {
                    $lines[] = "            '{$field}.max'      => getTranslation('{$label} must not exceed 255 characters'),";
                }
            }

            if ($lines !== []) {
                $groups[] = implode("\n", $lines);
            }
        }

        return implode("\n\n", $groups);
    }

    // ============================================================
    // RESOURCE / REPOSITORY / SERVICE BO'LAKLARI
    // ============================================================

    protected function buildResourceFields(array $fields): string
    {
        $lines = ["            'id' => \$this->id,"];

        foreach ($fields as $field) {
            if (in_array($field, $this->translatable, true)) {
                $lines[] = "            '{$field}' => getLocale(\$this->{$field}),";
            } elseif (in_array($field, $this->booleans, true)) {
                $lines[] = "            '{$field}' => (bool) \$this->{$field},";
            } elseif (in_array($field, $this->files, true)) {
                $lines[] = "            '{$field}' => \$this->{$field} ? url(\$this->{$field}) : null,";
            } else {
                $lines[] = "            '{$field}' => \$this->{$field},";
            }
        }

        // Sanalarni chiroyli formatlaymiz (null bo'lsa null qaytadi).
        $lines[] = '';
        $lines[] = "            'created_at' => formatDatetime(\$this->created_at),";
        $lines[] = "            'updated_at' => formatDatetime(\$this->updated_at),";

        return implode("\n", $lines);
    }

    protected function buildSearchFilters(array $fields): string
    {
        $lines = [];
        foreach ($fields as $field) {
            if (in_array($field, $this->files, true)) {
                continue; // fayl maydonlari bo'yicha filtr yo'q
            }

            if (in_array($field, $this->translatable, true)) {
                $lines[] = <<<PHP
        if (filled(\$filters['{$field}'] ?? null)) {
            \$this->applyTranslatableFilter(\$query, '{$field}', \$filters['{$field}']);
        }
PHP;
                continue;
            }

            // Tip bo'yicha aniq yoki LIKE filtr
            $exact = isset($this->enums[$field])
                || in_array($field, $this->booleans, true)
                || Str::endsWith($field, '_id')
                || in_array($this->columnType($field), [
                    'int',
                    'integer',
                    'bigint',
                    'smallint',
                    'tinyint',
                    'decimal',
                    'float',
                    'double',
                    'boolean',
                    'date',
                    'datetime',
                    'timestamp',
                ], true);

            if (in_array($field, $this->booleans, true)) {
                $lines[] = <<<PHP
        if (array_key_exists('{$field}', \$filters) && \$filters['{$field}'] !== '' && \$filters['{$field}'] !== null) {
            \$query->where('{$field}', filter_var(\$filters['{$field}'], FILTER_VALIDATE_BOOLEAN));
        }
PHP;
            } elseif ($exact) {
                $lines[] = <<<PHP
        if (filled(\$filters['{$field}'] ?? null)) {
            \$query->where('{$field}', \$filters['{$field}']);
        }
PHP;
            } else {
                $lines[] = <<<PHP
        if (filled(\$filters['{$field}'] ?? null)) {
            \$query->where('{$field}', 'like', '%'.\$filters['{$field}'].'%');
        }
PHP;
            }
        }

        return implode("\n\n", $lines);
    }

    /**
     * Sort qilish mumkin bo'lgan ustunlar (fillable skalyarlar + id + vaqtlar).
     * Fayl va tarjima maydonlari chiqarib tashlanadi.
     */
    protected function buildSortableList(array $fields): string
    {
        $sortable = ['id'];

        foreach ($fields as $field) {
            if (
                in_array($field, $this->files, true)
                || in_array($field, $this->translatable, true)
            ) {
                continue;
            }
            $sortable[] = $field;
        }

        $sortable[] = 'created_at';
        $sortable[] = 'updated_at';

        return "'" . implode("', '", array_values(array_unique($sortable))) . "'";
    }

    /**
     * Ko'p tilli maydonlar uchun 'default' kalitini o'rnatish bloki.
     *
     * Create va Update bir xil ishlaydi: kelgan massivning birinchi
     * qiymati 'default' bo'ladi (frontend qaysi tilni birinchi yuborsa).
     *
     *   if (isset($data['title']) && is_array($data['title'])) {
     *       $data['title']['default'] = reset($data['title']);
     *   }
     */
    protected function buildTranslatableDefaults(bool $forUpdate): string
    {
        if (empty($this->translatable)) {
            return '';
        }

        $lines = [];
        foreach ($this->translatable as $tf) {
            $lines[] = <<<PHP
        if (isset(\$data['{$tf}']) && is_array(\$data['{$tf}'])) {
            \$data['{$tf}']['default'] = reset(\$data['{$tf}']);
        }
PHP;
        }

        return "\n" . implode("\n\n", $lines) . "\n";
    }

    protected function buildFileHandling(bool $forUpdate): string
    {
        if (empty($this->files)) {
            return '';
        }

        $lines = [];
        foreach ($this->files as $f) {
            if ($forUpdate) {
                $lines[] = <<<PHP
        if (isset(\$data['{$f}']) && \$data['{$f}'] instanceof \\Illuminate\\Http\\UploadedFile) {
            \$data['{$f}'] = FileUploadService::uploadFile(\$data['{$f}']);
        } else {
            unset(\$data['{$f}']);
        }
PHP;
            } else {
                $lines[] = <<<PHP
        if (isset(\$data['{$f}']) && \$data['{$f}'] instanceof \\Illuminate\\Http\\UploadedFile) {
            \$data['{$f}'] = FileUploadService::uploadFile(\$data['{$f}']);
        }
PHP;
            }
        }

        return "\n" . implode("\n\n", $lines) . "\n";
    }

    // ============================================================
    // STUB'LAR
    // ============================================================

    protected function controllerStub(): string
    {
        return <<<'STUB'
<?php

namespace {{module_ns}}\Controller;

use App\Http\Controllers\Controller;
use {{module_ns}}\Request\Store{{Model}}Request;
use {{module_ns}}\Request\Update{{Model}}Request;
use {{module_ns}}\Response\{{Model}}Resource;
use {{module_ns}}\Service\{{Model}}Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class {{Model}}Controller extends Controller
{
    private {{Model}}Service $service;

    public function __construct({{Model}}Service $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResource
    {
        $paginator = $this->service->list($request->all());

        return {{Model}}Resource::collection($paginator);
    }

    public function store(Store{{Model}}Request $request): JsonResponse
    {
        $model = $this->service->create($request->validated());

        return (new {{Model}}Resource($model))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id): JsonResource
    {
        $model = $this->service->find($id);

        return new {{Model}}Resource($model);
    }

    public function update(Update{{Model}}Request $request, int $id): JsonResource
    {
        $model = $this->service->update($id, $request->validated());

        return new {{Model}}Resource($model);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['message' => '{{Model}} deleted']);
    }
}
STUB;
    }

    protected function serviceStub(array $fields): string
    {
        $defaultsCreate = $this->buildTranslatableDefaults(false);
        $defaultsUpdate = $this->buildTranslatableDefaults(true);
        $fileStore = $this->buildFileHandling(false);
        $fileUpd = $this->buildFileHandling(true);
        $sortable = $this->buildSortableList($fields);

        $useFile = empty($this->files)
            ? ''
            : "use App\\Services\\FileUploadService;\n";

        return strtr(<<<'STUB'
<?php

namespace {{module_ns}}\Service;

use {{module_ns}}\Repository\{{Model}}RepositoryInterface;
use App\Models\{{Model}};
{{use_file}}use Illuminate\Contracts\Pagination\CursorPaginator;

class {{Model}}Service
{
    private const SORTABLE = [{{sortable}}];

    private {{Model}}RepositoryInterface $repository;

    public function __construct({{Model}}RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function list(array $params): CursorPaginator
    {
        $sortColumn = $params['column'] ?? null;

        if (! in_array($sortColumn, self::SORTABLE, true)) {
            $sortColumn = 'id';
        }

        $sortDirection = strtolower((string) ($params['sort'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $perPage = (int) ($params['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100));

        return $this->repository->paginate($params, $sortColumn, $sortDirection, $perPage);
    }

    public function find(int $id): {{Model}}
    {
        return $this->repository->find($id);
    }

    public function create(array $data): {{Model}}
    {{{defaults_create}}{{file_store}}
        return $this->repository->create($data);
    }

    public function update(int $id, array $data): {{Model}}
    {
        $model = $this->find($id);
{{defaults_update}}{{file_update}}
        return $this->repository->update($model, $data);
    }

    public function delete(int $id): void
    {
        $model = $this->find($id);

        $this->repository->delete($model);
    }
}
STUB, [
            '{{defaults_create}}' => $defaultsCreate,
            '{{defaults_update}}' => $defaultsUpdate,
            '{{file_store}}' => $fileStore,
            '{{file_update}}' => $fileUpd,
            '{{use_file}}' => $useFile,
            '{{sortable}}' => $sortable,
        ]);
    }

    protected function repositoryInterfaceStub(): string
    {
        return <<<'STUB'
<?php

namespace {{module_ns}}\Repository;

use App\Models\{{Model}};
use Illuminate\Contracts\Pagination\CursorPaginator;

interface {{Model}}RepositoryInterface
{
    public function paginate(array $filters, string $sort, string $direction, int $perPage): CursorPaginator;

    public function find(int $id): {{Model}};

    public function create(array $data): {{Model}};

    public function update({{Model}} $model, array $data): {{Model}};

    public function delete({{Model}} $model): void;
}
STUB;
    }

    protected function repositoryStub(array $fields): string
    {
        $filters = $this->buildSearchFilters($fields);

        // Ko'p tilli filtr mantig'i bitta private metodda (DRY/SRP) —
        // har bir tarjima maydoni uchun bloklarni takrorlamaymiz.
        $translatableFilter = empty($this->translatable) ? '' : <<<'PHP'


    private function applyTranslatableFilter($query, string $column, string $value): void
    {
        $locale = app()->getLocale();
        $needle = '%'.$value.'%';

        $query->where(function ($q) use ($column, $needle, $locale) {
            $q->where("{$column}->{$locale}", 'like', $needle)
              ->orWhere("{$column}->default", 'like', $needle);
        });
    }
PHP;

        return strtr(<<<'STUB'
<?php

namespace {{module_ns}}\Repository;

use App\Models\{{Model}};
use Illuminate\Contracts\Pagination\CursorPaginator;

class {{Model}}Repository implements {{Model}}RepositoryInterface
{
    private {{Model}} $model;

    public function __construct({{Model}} $model)
    {
        $this->model = $model;
    }

    public function paginate(array $filters, string $sort, string $direction, int $perPage): CursorPaginator
    {
        $query = $this->model->newQuery();

{{filters}}

        return $query
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->cursorPaginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): {{Model}}
    {
        return $this->model->newQuery()->findOrFail($id);
    }

    public function create(array $data): {{Model}}
    {
        return $this->model->newQuery()->create($data);
    }

    public function update({{Model}} $model, array $data): {{Model}}
    {
        $model->update($data);

        return $model->refresh();
    }

    public function delete({{Model}} $model): void
    {
        $model->delete();
    }{{translatable_filter}}
}
STUB, [
            '{{filters}}' => $filters,
            '{{translatable_filter}}' => $translatableFilter,
        ]);
    }

    protected function requestStub(array $fields, bool $forUpdate): string
    {
        $type = $forUpdate ? 'Update' : 'Store';
        $rules = $this->buildRules($fields, $forUpdate);
        $merge = $this->buildTranslationMerge($forUpdate);
        $messages = $this->buildMessages($fields);

        return strtr(<<<'STUB'
<?php

namespace {{module_ns}}\Request;

use App\Support\BaseFormRequest;

class {{type}}{{Model}}Request extends BaseFormRequest
{
    public function rules(): array
    {
        $rules = [
{{rules}}
        ];
{{merge}}

        return $rules;
    }

    public function messages(): array
    {
        return [
{{messages}}
        ];
    }
}
STUB, [
            '{{type}}' => $type,
            '{{rules}}' => $rules,
            '{{merge}}' => $merge,
            '{{messages}}' => $messages,
        ]);
    }

    protected function resourceStub(array $fields): string
    {
        $body = $this->buildResourceFields($fields);

        return strtr(<<<'STUB'
<?php

namespace {{module_ns}}\Response;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class {{Model}}Resource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
{{body}}
        ];
    }
}
STUB, ['{{body}}' => $body]);
    }

    protected function enumStub(string $enumName, array $values): string
    {
        $cases = [];
        foreach ($values as $value) {
            $const = Str::upper(Str::snake(Str::slug((string) $value, '_')));
            $const = preg_replace('/[^A-Z0-9_]/', '', $const) ?: 'VALUE';
            if (preg_match('/^\d/', $const)) {
                $const = 'V_' . $const;
            }
            $cases[] = "    case {$const} = '{$value}';";
        }

        return strtr(<<<'STUB'
<?php

namespace {{module_ns}}\Enum;

enum {{enum}}: string
{
{{cases}}

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
STUB, [
            '{{enum}}' => $enumName,
            '{{cases}}' => implode("\n", $cases),
        ]);
    }

    // ============================================================
    // ASOSIY PROVIDER'GA BINDING (idempotent)
    // ============================================================

    /**
     * Repository interfeysini implementatsiyaga App\Providers\
     * AppServiceProvider::register() ichida bog'laydi. Modul ichida
     * alohida ServiceProvider yaratilmaydi.
     */
    protected function registerBinding(string $ns, string $name): void
    {
        $path = app_path('Providers/AppServiceProvider.php');
        $contents = File::get($path);

        $interface = "{$ns}\\Repository\\{$name}RepositoryInterface";
        $impl = "{$ns}\\Repository\\{$name}Repository";

        if (str_contains($contents, "{$interface}::class")) {
            $this->line("  <fg=yellow>o'tkazib yuborildi</> AppServiceProvider (allaqachon bog'langan)");
            return;
        }

        $binding = "        \$this->app->bind(\n"
            . "            \\{$interface}::class,\n"
            . "            \\{$impl}::class\n"
            . "        );\n";

        // register() ochilish qavsidan keyin qo'shamiz, bo'sh '//' ni tozalaymiz.
        $updated = preg_replace(
            '/(public function register\(\): void\s*\{\R)(\s*\/\/\R)?/',
            '$1' . $binding,
            $contents,
            1
        );

        if ($updated === null || $updated === $contents) {
            $this->warn("AppServiceProvider ni avtomatik yangilab bo'lmadi. Qo'lda qo'shing: \$this->app->bind(\\{$interface}::class, \\{$impl}::class);");
            return;
        }

        File::put($path, $updated);
        $this->line('  <fg=green>yangilandi</>   app/Providers/AppServiceProvider.php');
    }

    // ============================================================
    // ROUTE'NI routes/api.php GA QO'SHISH (idempotent)
    // ============================================================

    /**
     * apiResource route'ini routes/api.php oxiriga qo'shadi. Laravel
     * routes/api.php ga 'api' prefiks va middleware'ni o'zi qo'llaydi,
     * shuning uchun bu yerda guruh/prefiks shart emas.
     */
    protected function registerRoutes(string $ns, string $name, string $plural): void
    {
        $path = base_path('routes/api.php');
        $contents = File::get($path);

        $controllerFqcn = "{$ns}\\Controller\\{$name}Controller";

        if (str_contains($contents, $controllerFqcn)) {
            $this->line("  <fg=yellow>o'tkazib yuborildi</> routes/api.php (allaqachon ro'yxatda)");
            return;
        }

        $line = "\nRoute::apiResource('{$plural}', \\{$controllerFqcn}::class);\n";

        File::append($path, $line);
        $this->line('  <fg=green>yangilandi</>   routes/api.php');
    }
}
