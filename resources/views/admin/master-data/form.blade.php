@extends('layouts.app')

@section('title', ($record ? 'Edit ' : 'Tambah ').$title)
@section('description', 'Lengkapi data '.$title.' sesuai aturan operasional sistem.')

@section('content')
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

        <form method="POST" action="{{ $record ? route($routeName.'.update', $record->getKey()) : route($routeName.'.store') }}" data-prevent-double-submit>
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
                    @endphp

                    <div class="admin-form-field {{ in_array($type, ['textarea', 'checkbox'], true) ? 'full-width' : '' }}">
                        @if ($type === 'checkbox')
                            <div class="admin-checkbox-row">
                                <input
                                    id="field-{{ $name }}"
                                    type="checkbox"
                                    name="{{ $name }}"
                                    value="1"
                                    @checked((bool) old($name, $field['default'] ?? false))
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

    @if (isset($bahanUnits))
        const bahanUnits = @json($bahanUnits);
        const bahanSelect = document.getElementById('field-bahan_id');
        const unitPreview = document.getElementById('resep-unit-preview');

        const syncUnitPreview = () => {
            if (!bahanSelect || !unitPreview) {
                return;
            }
            const unit = bahanUnits[bahanSelect.value];
            unitPreview.textContent = unit ? unit : 'satuan';
        };

        bahanSelect?.addEventListener('change', syncUnitPreview);
        syncUnitPreview();
    @endif
</script>
@endpush
