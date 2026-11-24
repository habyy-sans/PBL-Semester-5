@extends('layouts.app')

@section('title', ($record ? 'Edit ' : 'Tambah ').$title)
@section('description', 'Lengkapi data '.$title.' sesuai aturan operasional sistem.')

@section('content')
@php
    $hasOptionalRecipe = isset($recipeIngredients);
    $oldRecipeItems = old('recipe_items');
    $initialRecipeRows = [];

    if (is_array($oldRecipeItems)) {
        foreach ($oldRecipeItems as $row) {
            if (is_array($row)) {
                $initialRecipeRows[] = [
                    'bahan_id' => (string) ($row['bahan_id'] ?? ''),
                    'jumlah_bahan' => (string) ($row['jumlah_bahan'] ?? ''),
                ];
            }
        }
    } elseif ($hasOptionalRecipe) {
        $initialRecipeRows = [['bahan_id' => '', 'jumlah_bahan' => '']];
    }

    $initialDefaultVariant = $errors->any()
        ? (bool) old('buat_varian_default')
        : (bool) old('buat_varian_default', true);
    $initialSellingPrice = (float) old('harga_jual', $record?->harga_jual ?? 0);
@endphp
<section class="page active admin-panel">
    <div class="admin-subnav">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route($routeName.'.index') }}">Kelola {{ $title }}</a>
            <span aria-hidden="true">/</span>
            <span>{{ $record ? 'Edit Data' : 'Tambah Data' }}</span>
        </nav>

        <a class="admin-btn-outline admin-btn-sm" href="{{ route($routeName.'.index') }}">
            <x-icon name="chevron-left" size="1em" />
            <span>Kembali</span>
        </a>
    </div>

    <x-admin.flash />

    <div class="admin-card admin-form-card">
        <div class="admin-form-header">
            <h3>{{ $record ? 'Perbarui '.$title : 'Tambah '.$title.' Baru' }}</h3>
            <p>Kolom bertanda <span class="required-mark">*</span> wajib diisi.</p>
        </div>

        <form
            method="POST"
            action="{{ $record ? route($routeName.'.update', $record->getKey()) : route($routeName.'.store') }}"
            data-prevent-double-submit
            @if ($hasOptionalRecipe)
                x-data="{
                    ...createRecipeState({
                        inputName: 'recipe_items',
                        ingredients: @js($recipeIngredients),
                        required: false,
                        isEnabled: @js($initialDefaultVariant),
                        sellingPrice: @js($initialSellingPrice),
                        initialRows: @js($initialRecipeRows),
                        serverErrors: @js($errors->getMessages()),
                    }),
                }"
            @endif
        >
            @csrf
            @if ($record)
                @method('PUT')
            @endif

            <div class="admin-form-grid">
                @foreach ($fields as $field)
                    @php
                        $name = $field['name'];
                        $type = $field['type'] ?? 'text';
                        $defaultVal = $prefill[$name] ?? ($field['default'] ?? '');
                        $value = old($name, $record?->{$name} ?? $defaultVal);
                        $hasError = $errors->has($name);
                        $checkedState = $errors->any()
                            ? (bool) old($name)
                            : (bool) old($name, $field['default'] ?? false);
                    @endphp

                    <div class="admin-form-field {{ in_array($type, ['textarea', 'checkbox'], true) ? 'full-width' : '' }}">
                        @if ($type === 'checkbox')
                            <div class="admin-checkbox-row">
                                <input
                                    id="field-{{ $name }}"
                                    type="checkbox"
                                    name="{{ $name }}"
                                    value="1"
                                    @if ($hasOptionalRecipe && $name === 'buat_varian_default')
                                        x-model="isEnabled"
                                    @endif
                                    @checked($checkedState)
                                >
                                <div>
                                    <label class="admin-checkbox-label" for="field-{{ $name }}">{{ $field['label'] }}</label>
                                    @if (! empty($field['help']))
                                        <p class="admin-field-help">{{ $field['help'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @else
                            <label for="field-{{ $name }}">
                                {{ $field['label'] }}
                                @if ($field['required'] ?? false)
                                    <span class="required-mark">*</span>
                                @endif
                            </label>

                            @if ($type === 'select')
                                <select
                                    id="field-{{ $name }}"
                                    name="{{ $name }}"
                                    aria-invalid="{{ $hasError ? 'true' : 'false' }}"
                                    @required($field['required'] ?? false)
                                >
                                    <option value="">Pilih {{ strtolower($field['label']) }}</option>
                                    @foreach ($field['options'] as $option)
                                        @php
                                            $optValue = $option->{$field['optionValue']};
                                            $prefix = isset($field['optionLabelPrefix']) ? data_get($option, $field['optionLabelPrefix']) : null;
                                            $optLabel = ($prefix ? $prefix.' - ' : '').$option->{$field['optionLabel']};
                                        @endphp
                                        <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>
                                            {{ $optLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            @elseif ($type === 'textarea')
                                <textarea
                                    id="field-{{ $name }}"
                                    name="{{ $name }}"
                                    rows="4"
                                    maxlength="{{ $field['max'] ?? 2000 }}"
                                    placeholder="{{ $field['placeholder'] ?? '' }}"
                                    aria-invalid="{{ $hasError ? 'true' : 'false' }}"
                                    @required($field['required'] ?? false)
                                >{{ $value }}</textarea>
                            @elseif ($name === 'jumlah_bahan' && isset($bahanUnits))
                                <div class="admin-input-with-unit">
                                    <input
                                        id="field-{{ $name }}"
                                        type="number"
                                        name="{{ $name }}"
                                        value="{{ $value }}"
                                        min="{{ $field['min'] ?? 0 }}"
                                        @if (isset($field['max'])) max="{{ $field['max'] }}" @endif
                                        step="{{ $field['step'] ?? 'any' }}"
                                        aria-invalid="{{ $hasError ? 'true' : 'false' }}"
                                        @required($field['required'] ?? false)
                                    >
                                    <span class="admin-unit-chip" id="resep-unit-preview">satuan</span>
                                </div>
                            @else
                                <input
                                    id="field-{{ $name }}"
                                    type="{{ $type === 'integer' ? 'number' : $type }}"
                                    name="{{ $name }}"
                                    value="{{ $value }}"
                                    @if (in_array($type, ['number', 'integer'], true))
                                        min="{{ $field['min'] ?? 0 }}"
                                        @if (isset($field['max'])) max="{{ $field['max'] }}" @endif
                                        step="{{ $type === 'integer' ? '1' : ($field['step'] ?? 'any') }}"
                                    @else
                                        maxlength="{{ $field['max'] ?? 255 }}"
                                    @endif
                                    @if ($hasOptionalRecipe && $name === 'harga_jual')
                                        @input="sellingPrice = parseFloat($event.target.value) || 0"
                                    @endif
                                    @if (! empty($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif
                                    aria-invalid="{{ $hasError ? 'true' : 'false' }}"
                                    @required($field['required'] ?? false)
                                >
                            @endif

                            @if (! empty($field['help']))
                                <span class="admin-field-help">{{ $field['help'] }}</span>
                            @endif
                        @endif

                        @error($name)<span class="admin-field-error">{{ $message }}</span>@enderror
                    </div>
                @endforeach
            </div>

            @if ($hasOptionalRecipe)
                <div class="admin-optional-recipe" x-show="isEnabled" x-cloak>
                    <div class="admin-optional-recipe-head">
                        <div>
                            <h4>Resep Awal Varian "Regular" (Opsional)</h4>
                            <p>Langsung tentukan seluruh bahan baku per porsi di sini, atau kosongkan bila ingin diisi nanti melalui halaman Resep Menu.</p>
                        </div>

                        <div class="admin-optional-recipe-summary tabular-nums" x-show="validRows().length > 0">
                            <span>Est. HPP: <strong x-text="formatRupiah(totalHpp())">Rp0</strong></span>
                            <span x-show="sellingPrice > 0">
                                · Margin: <strong x-text="Math.round(estimatedMargin()) + '%'">0%</strong>
                            </span>
                        </div>
                    </div>

                    @if ($recipeIngredients === [])
                        <p class="admin-field-help">Belum ada katalog bahan baku. Anda tetap dapat menyimpan menu sekarang dan mengatur resep nanti.</p>
                    @else
                        <x-admin.recipe-repeater
                            inputName="recipe_items"
                            :ingredients="$recipeIngredients"
                            :required="false"
                            addButtonLabel="Tambah Bahan Baku"
                            emptyText="Belum ada bahan baku dipilih (menu akan disimpan tanpa resep)."
                        />
                    @endif
                </div>
            @endif

            <div class="admin-form-actions">
                <a class="admin-btn-outline" href="{{ route($routeName.'.index') }}">Batal</a>
                <button class="admin-btn-primary" type="submit">{{ $record ? 'Simpan Perubahan' : 'Simpan Data' }}</button>
            </div>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-prevent-double-submit]').forEach((form) => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
            }
        });
    });
</script>
@endpush
