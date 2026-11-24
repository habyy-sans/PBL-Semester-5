<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

abstract class MasterDataController extends Controller
{
    protected string $modelClass;

    protected string $title;

    protected string $description = '';

    protected string $routeName;

    /** @var array<int, array<string, mixed>> */
    protected array $fields = [];

    /** @var array<int, array<string, mixed>> */
    protected array $columns = [];

    /** @var array<int, string> */
    protected array $searchable = [];

    /** @var array<string, string> */
    protected array $searchRelations = [];

    /** @var array<string, array<string, mixed>> */
    protected array $filters = [];

    /** @var array<int, string> */
    protected array $sortable = [];

    /** @var array<int, string> */
    protected array $with = [];

    /** @var array<int, string> */
    protected array $withCount = [];

    /** @var array<int, string> */
    protected array $dependents = [];

    /** @var array<string, string> */
    protected array $dependentLabels = [];

    /** @var array<int, array<string, mixed>> */
    protected array $compositeUnique = [];

    public function index(Request $request): View
    {
        $modelClass = $this->modelClass;
        $model = new $modelClass;
        $query = $modelClass::query()
            ->with($this->with)
            ->withCount($this->withCount);

        $queryKeyword = $request->query('q');
        $keyword = is_string($queryKeyword) ? trim($queryKeyword) : '';

        if ($keyword !== '' && ($this->searchable !== [] || $this->searchRelations !== [])) {
            $pattern = '%'.mb_strtolower($keyword).'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                foreach ($this->searchable as $column) {
                    $wrappedColumn = $builder->getQuery()->getGrammar()->wrap($column);
                    $builder->orWhereRaw("LOWER({$wrappedColumn}) LIKE ?", [$pattern]);
                }

                foreach ($this->searchRelations as $relation => $column) {
                    $builder->orWhereHas($relation, function (Builder $related) use ($column, $pattern): void {
                        $wrappedColumn = $related->getQuery()->getGrammar()->wrap($column);
                        $related->whereRaw("LOWER({$wrappedColumn}) LIKE ?", [$pattern]);
                    });
                }
            });
        }

        $activeFilters = [];
        foreach ($this->filters as $key => $filter) {
            $value = $request->query($key);

            if (! is_string($value) || $value === '') {
                continue;
            }

            $filterType = $filter['type'] ?? (isset($filter['distinct']) ? 'string' : 'int');

            if ($filterType === 'int' && ! ctype_digit($value)) {
                continue;
            }

            $resolved = $filterType === 'int' ? (int) $value : $value;
            $query->where($filter['column'], $resolved);
            $activeFilters[$key] = $value;
        }

        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (is_string($sort) && in_array($sort, $this->sortable, true)) {
            $query->orderBy($sort, $direction)->orderBy($model->getKeyName());
        } else {
            $sort = null;
            $query->orderBy($model->getKeyName());
        }

        $records = $query->paginate(12)->withQueryString();

        return view('admin.master-data.index', array_merge([
            'title' => $this->title,
            'description' => $this->description !== '' ? $this->description : 'Cari, filter, dan perbarui data '.$this->title.'.',
            'routeName' => $this->routeName,
            'columns' => $this->columns,
            'filters' => $this->filterOptions(),
            'activeFilters' => $activeFilters,
            'records' => $records,
            'keyword' => $keyword,
            'sort' => $sort,
            'direction' => $direction,
            'sortable' => $this->sortable,
            'hasActiveFilters' => $keyword !== '' || $activeFilters !== [] || $sort !== null,
            'createQuery' => $this->createQueryParams($request),
        ], $this->extraIndexData($request)));
    }

    public function create(Request $request): View
    {
        return $this->formView(null, $this->prefillFromQuery($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);

        return DB::transaction(function () use ($validated, $request): RedirectResponse {
            $this->assertUniqueConstraints($validated);

            $modelClass = $this->modelClass;
            $record = $modelClass::query()->create($this->persistableData($validated));

            $customRedirect = $this->afterStore($record, $request, $validated);
            if ($customRedirect !== null) {
                return $customRedirect;
            }

            return redirect()
                ->route($this->routeName.'.index')
                ->with('success', $this->title.' berhasil ditambahkan.');
        });
    }

    public function edit(int $record): View
    {
        return $this->formView($this->findRecord($record));
    }

    public function update(Request $request, int $record): RedirectResponse
    {
        $model = $this->findRecord($record);
        $validated = $this->validatedData($request, $model);

        return DB::transaction(function () use ($model, $validated): RedirectResponse {
            $this->assertUniqueConstraints($validated, $model);
            $model->update($this->persistableData($validated, $model));

            return redirect()
                ->route($this->routeName.'.index')
                ->with('success', $this->title.' berhasil diperbarui.');
        });
    }

    public function destroy(int $record): RedirectResponse
    {
        try {
            return DB::transaction(function () use ($record): RedirectResponse {
                $model = $this->findRecord($record);
                $reasons = $this->blockingReasons($model);

                if ($reasons !== []) {
                    return back()->withErrors([
                        'delete' => $this->title.' tidak dapat dihapus karena masih terkait dengan: '.implode(', ', $reasons).'.',
                    ]);
                }

                $this->beforeDelete($model);
                $model->delete();

                return redirect()
                    ->route($this->routeName.'.index')
                    ->with('success', $this->title.' berhasil dihapus.');
            });
        } catch (QueryException) {
            return back()->withErrors([
                'delete' => $this->title.' masih digunakan pada data lain sehingga tidak dapat dihapus.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $prefill
     */
    protected function formView(?Model $record = null, array $prefill = []): View
    {
        return view('admin.master-data.form', array_merge([
            'title' => $this->title,
            'routeName' => $this->routeName,
            'fields' => $this->formFields($record !== null),
            'record' => $record,
            'prefill' => $prefill,
        ], $this->extraFormData($record)));
    }

    protected function findRecord(int $key): Model
    {
        $modelClass = $this->modelClass;

        return $modelClass::query()->findOrFail($key);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, ?Model $record = null): array
    {
        $isEdit = $record !== null;
        $rules = [];
        $attributes = [];
        $trimmed = [];

        foreach ($this->fields as $field) {
            if (($field['createOnly'] ?? false) && $isEdit) {
                continue;
            }

            $name = $field['name'];
            $type = $field['type'] ?? 'text';
            $attributes[$name] = mb_strtolower((string) ($field['label'] ?? $name));

            if ($type === 'checkbox') {
                $trimmed[$name] = $request->boolean($name);
                $rules[$name] = ['nullable', 'boolean'];

                continue;
            }

            $raw = $request->input($name);
            $trimmed[$name] = is_string($raw) ? trim($raw) : $raw;

            $fieldRules = [
                ($field['required'] ?? false) ? 'required' : 'nullable',
                match ($type) {
                    'number' => 'numeric',
                    'integer', 'select' => 'integer',
                    default => 'string',
                },
            ];

            if (! in_array($type, ['number', 'integer', 'select'], true)) {
                $fieldRules[] = 'max:'.($field['max'] ?? 255);
            } elseif (isset($field['max'])) {
                $fieldRules[] = 'max:'.$field['max'];
            }

            if (isset($field['min'])) {
                $fieldRules[] = 'min:'.$field['min'];
            }

            if (isset($field['gt'])) {
                $fieldRules[] = 'gt:'.$field['gt'];
            }

            if (isset($field['exists'])) {
                $fieldRules[] = Rule::exists($field['exists']['table'], $field['exists']['column']);
            }

            if ($field['unique'] ?? false) {
                $model = new ($this->modelClass);
                $fieldRules[] = Rule::unique($model->getTable(), $name)
                    ->ignore($record?->getKey(), $model->getKeyName());
            }

            $rules[$name] = $fieldRules;
        }

        return Validator::make($trimmed, $rules, [], $attributes)->validate();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function assertUniqueConstraints(array $data, ?Model $record = null): void
    {
        $modelClass = $this->modelClass;
        $model = new $modelClass;
        $keyName = $model->getKeyName();

        foreach ($this->fields as $field) {
            if (! ($field['uniqueCaseInsensitive'] ?? false)) {
                continue;
            }

            $name = $field['name'];
            $value = $data[$name] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $wrapped = $model->newQuery()->getQuery()->getGrammar()->wrap($name);
            $exists = $modelClass::query()
                ->whereRaw("LOWER({$wrapped}) = ?", [mb_strtolower($value)])
                ->when($record !== null, fn (Builder $query) => $query->where($keyName, '!=', $record->getKey()))
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    $name => ($field['label'] ?? $name).' sudah terdaftar.',
                ]);
            }
        }

        foreach ($this->compositeUnique as $rule) {
            $query = $modelClass::query();
            $caseInsensitive = $rule['caseInsensitive'] ?? [];
            $missing = false;

            foreach ($rule['columns'] as $column) {
                $value = $data[$column] ?? null;
                if ($value === null || $value === '') {
                    $missing = true;
                    break;
                }

                if (in_array($column, $caseInsensitive, true)) {
                    $wrapped = $query->getQuery()->getGrammar()->wrap($column);
                    $query->whereRaw("LOWER({$wrapped}) = ?", [mb_strtolower((string) $value)]);
                } else {
                    $query->where($column, $value);
                }
            }

            if ($missing) {
                continue;
            }

            if ($record !== null) {
                $query->where($keyName, '!=', $record->getKey());
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    $rule['errorField'] => $rule['message'],
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function persistableData(array $validated, ?Model $record = null): array
    {
        $isEdit = $record !== null;
        $result = [];

        foreach ($this->fields as $field) {
            if (($field['virtual'] ?? false) || (($field['createOnly'] ?? false) && $isEdit)) {
                continue;
            }

            $name = $field['name'];
            if (array_key_exists($name, $validated)) {
                $result[$name] = $validated[$name];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function afterStore(Model $record, Request $request, array $validated): ?RedirectResponse
    {
        return null;
    }

    protected function hasBlockingDependency(Model $model, string $relation): bool
    {
        return $model->{$relation}()->exists();
    }

    protected function beforeDelete(Model $model): void {}

    /**
     * @return array<int, string>
     */
    protected function blockingReasons(Model $model): array
    {
        $reasons = [];

        foreach ($this->dependents as $relation) {
            if ($this->hasBlockingDependency($model, $relation)) {
                $reasons[] = $this->dependentLabels[$relation] ?? str($relation)->snake(' ')->toString();
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraIndexData(Request $request): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraFormData(?Model $record = null): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formFields(bool $isEdit = false): array
    {
        $visibleFields = array_values(array_filter(
            $this->fields,
            fn (array $field): bool => ! ($isEdit && ($field['createOnly'] ?? false)),
        ));

        return array_map(function (array $field): array {
            if (! isset($field['optionsModel'])) {
                return $field;
            }

            $modelClass = $field['optionsModel'];
            $with = $field['optionsWith'] ?? [];
            $query = $modelClass::query()->with($with);

            if ($with === []) {
                $options = $query
                    ->orderBy($field['optionLabel'])
                    ->get([$field['optionValue'], $field['optionLabel']]);
            } else {
                // Muat model lengkap beserta relasi agar foreign key untuk eager loading tidak hilang.
                $options = $query->get()->sortBy(function (Model $option) use ($field): string {
                    $prefix = isset($field['optionLabelPrefix'])
                        ? (string) data_get($option, $field['optionLabelPrefix'])
                        : '';
                    $label = (string) $option->getAttribute($field['optionLabel']);

                    return mb_strtolower(trim($prefix.' '.$label));
                })->values();
            }

            $field['options'] = $options;

            return $field;
        }, $visibleFields);
    }

    /**
     * @return array<string, array{label: string, options: array<int, array{value: mixed, label: string}>}>
     */
    private function filterOptions(): array
    {
        $options = [];

        foreach ($this->filters as $key => $filter) {
            if (isset($filter['distinct'])) {
                $modelClass = $filter['model'];
                $values = $modelClass::query()
                    ->whereNotNull($filter['distinct'])
                    ->where($filter['distinct'], '!=', '')
                    ->distinct()
                    ->orderBy($filter['distinct'])
                    ->pluck($filter['distinct']);

                $options[$key] = [
                    'label' => $filter['label'],
                    'options' => $values->map(fn ($value) => ['value' => $value, 'label' => $value])->all(),
                ];

                continue;
            }

            $modelClass = $filter['model'];
            $with = $filter['optionsWith'] ?? [];
            $query = $modelClass::query()->with($with);

            if ($with === []) {
                $items = $query
                    ->orderBy($filter['optionLabel'])
                    ->get([$filter['optionValue'], $filter['optionLabel']]);
            } else {
                $items = $query->get()->sortBy(function (Model $option) use ($filter): string {
                    $prefix = isset($filter['optionLabelPrefix'])
                        ? (string) data_get($option, $filter['optionLabelPrefix'])
                        : '';
                    $label = (string) $option->getAttribute($filter['optionLabel']);

                    return mb_strtolower(trim($prefix.' '.$label));
                })->values();
            }

            $options[$key] = [
                'label' => $filter['label'],
                'options' => $items
                    ->map(function (Model $option) use ($filter): array {
                        $prefix = isset($filter['optionLabelPrefix'])
                            ? (string) data_get($option, $filter['optionLabelPrefix'])
                            : '';
                        $label = (string) $option->getAttribute($filter['optionLabel']);

                        return [
                            'value' => $option->getAttribute($filter['optionValue']),
                            'label' => $prefix !== '' ? $prefix.' - '.$label : $label,
                        ];
                    })
                    ->all(),
            ];
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function prefillFromQuery(Request $request): array
    {
        $prefill = [];

        foreach ($this->fields as $field) {
            if (($field['type'] ?? 'text') !== 'select') {
                continue;
            }

            $val = $request->query($field['name']);
            if (is_string($val) && ctype_digit($val)) {
                $prefill[$field['name']] = $val;
            }
        }

        return $prefill;
    }

    /**
     * @return array<string, string>
     */
    private function createQueryParams(Request $request): array
    {
        return $this->prefillFromQuery($request);
    }
}
