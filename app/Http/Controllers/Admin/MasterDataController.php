<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

abstract class MasterDataController extends Controller
{
    protected string $modelClass;

    protected string $title;

    protected string $routeName;

    protected array $fields = [];

    protected array $columns = [];

    protected array $searchable = [];

    protected array $searchRelations = [];

    protected array $filters = [];

    protected array $sortable = [];

    protected array $with = [];

    protected array $withCount = [];

    protected array $dependents = [];

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
            $query->where(function (Builder $builder) use ($keyword): void {
                foreach ($this->searchable as $column) {
                    $wrappedColumn = $builder->getQuery()->getGrammar()->wrap($column);
                    $builder->orWhereRaw(
                        "LOWER({$wrappedColumn}) LIKE ?",
                        ['%'.mb_strtolower($keyword).'%'],
                    );
                }

                foreach ($this->searchRelations as $relation => $column) {
                    $builder->orWhereHas($relation, function (Builder $related) use ($column, $keyword): void {
                        $wrappedColumn = $related->getQuery()->getGrammar()->wrap($column);
                        $related->whereRaw(
                            "LOWER({$wrappedColumn}) LIKE ?",
                            ['%'.mb_strtolower($keyword).'%'],
                        );
                    });
                }
            });
        }

        foreach ($this->filters as $key => $filter) {
            $value = $request->query($key);

            if (is_string($value) && $value !== '') {
                $query->where($filter['column'], $value);
            }
        }

        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if ($sort !== null && in_array($sort, $this->sortable, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy($model->getKeyName());
        }

        $records = $query->paginate(12)->withQueryString();

        return view('admin.master-data.index', [
            'title' => $this->title,
            'routeName' => $this->routeName,
            'columns' => $this->columns,
            'filters' => $this->filterOptions(),
            'records' => $records,
            'keyword' => $keyword,
            'sort' => $sort,
            'direction' => $direction,
            'sortable' => $this->sortable,
        ]);
    }

    public function create(): View
    {
        return $this->formView();
    }

    public function store(Request $request): RedirectResponse
    {
        $modelClass = $this->modelClass;
        $modelClass::query()->create($this->validatedData($request));

        return redirect()
            ->route($this->routeName.'.index')
            ->with('success', $this->title.' berhasil ditambahkan.');
    }

    public function edit(int $record): View
    {
        return $this->formView($this->findRecord($record));
    }

    public function update(Request $request, int $record): RedirectResponse
    {
        $model = $this->findRecord($record);
        $model->update($this->validatedData($request, $model));

        return redirect()
            ->route($this->routeName.'.index')
            ->with('success', $this->title.' berhasil diperbarui.');
    }

    public function destroy(int $record): RedirectResponse
    {
        $model = $this->findRecord($record);

        foreach ($this->dependents as $relation) {
            if ($model->{$relation}()->exists()) {
                return back()->withErrors([
                    'delete' => 'Data masih digunakan dan tidak dapat dihapus.',
                ]);
            }
        }

        $model->delete();

        return redirect()
            ->route($this->routeName.'.index')
            ->with('success', $this->title.' berhasil dihapus.');
    }

    private function formView(?Model $record = null): View
    {
        return view('admin.master-data.form', [
            'title' => $this->title,
            'routeName' => $this->routeName,
            'fields' => $this->formFields(),
            'record' => $record,
        ]);
    }

    private function findRecord(int $key): Model
    {
        $modelClass = $this->modelClass;

        return $modelClass::query()->findOrFail($key);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?Model $record = null): array
    {
        $rules = [];

        foreach ($this->fields as $field) {
            $name = $field['name'];
            $rules[$name] = [
                ($field['required'] ?? false) ? 'required' : 'nullable',
                match ($field['type'] ?? 'text') {
                    'number' => 'numeric',
                    'select' => 'integer',
                    default => 'string',
                },
                'max:'.($field['max'] ?? 255),
            ];

            if (isset($field['min'])) {
                $rules[$name][] = 'min:'.$field['min'];
            }

            if (isset($field['exists'])) {
                $rules[$name][] = Rule::exists($field['exists']['table'], $field['exists']['column']);
            }

            if ($field['unique'] ?? false) {
                $model = new ($this->modelClass);
                $rules[$name][] = Rule::unique($model->getTable(), $name)
                    ->ignore($record?->getKey(), $model->getKeyName());
            }
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formFields(): array
    {
        return array_map(function (array $field): array {
            if (! isset($field['optionsModel'])) {
                return $field;
            }

            $modelClass = $field['optionsModel'];
            $field['options'] = $modelClass::query()
                ->with($field['optionsWith'] ?? [])
                ->orderBy($field['optionLabel'])
                ->get([$field['optionValue'], $field['optionLabel']]);

            return $field;
        }, $this->fields);
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
            $options[$key] = [
                'label' => $filter['label'],
                'options' => $modelClass::query()
                    ->orderBy($filter['optionLabel'])
                    ->get([$filter['optionValue'], $filter['optionLabel']])
                    ->map(fn (Model $option) => [
                        'value' => $option->getAttribute($filter['optionValue']),
                        'label' => $option->getAttribute($filter['optionLabel']),
                    ])
                    ->all(),
            ];
        }

        return $options;
    }
}
