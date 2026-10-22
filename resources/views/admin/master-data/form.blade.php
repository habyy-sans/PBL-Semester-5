@extends('layouts.app')

@section('title', ($record ? 'Edit ' : 'Tambah ').$title)
@section('description', 'Lengkapi data '.$title.' dengan informasi yang valid.')

@section('content')
<section class="page active admin-panel">
    <div class="admin-page-heading">
        <div>
            <h1>{{ $record ? 'Edit' : 'Tambah' }} {{ $title }}</h1>
            <p>Pastikan data yang disimpan sesuai dengan kebutuhan operasional.</p>
        </div>
        <a class="outline-button" href="{{ route($routeName.'.index') }}">Kembali</a>
    </div>

    <div class="page-card admin-form-card">
        @if ($errors->any())
            <div class="admin-notice error" role="alert">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ $record ? route($routeName.'.update', $record->getKey()) : route($routeName.'.store') }}">
            @csrf
            @if ($record) @method('PUT') @endif
            <div class="admin-form-grid">
                @foreach ($fields as $field)
                    @php($value = old($field['name'], $record?->{$field['name']} ?? ''))
                    <div class="admin-form-field {{ ($field['type'] ?? '') === 'textarea' ? 'full-width' : '' }}">
                        <label for="field-{{ $field['name'] }}">{{ $field['label'] }} @if ($field['required'] ?? false)<span class="required-mark">*</span>@endif</label>
                        @if (($field['type'] ?? 'text') === 'select')
                            <select id="field-{{ $field['name'] }}" name="{{ $field['name'] }}" @required($field['required'] ?? false)>
                                <option value="">Pilih {{ strtolower($field['label']) }}</option>
                                @foreach ($field['options'] as $option)
                                    <option value="{{ $option->{$field['optionValue']} }}" @selected((string) $value === (string) $option->{$field['optionValue']})>{{ isset($field['optionLabelPrefix']) ? data_get($option, $field['optionLabelPrefix']).' — ' : '' }}{{ $option->{$field['optionLabel']} }}</option>
                                @endforeach
                            </select>
                        @elseif (($field['type'] ?? 'text') === 'textarea')
                            <textarea id="field-{{ $field['name'] }}" name="{{ $field['name'] }}" rows="4" maxlength="{{ $field['max'] ?? 255 }}" @required($field['required'] ?? false)>{{ $value }}</textarea>
                        @else
                            <input
                                id="field-{{ $field['name'] }}"
                                type="{{ $field['type'] ?? 'text' }}"
                                name="{{ $field['name'] }}"
                                value="{{ $value }}"
                                @if (($field['type'] ?? '') === 'number') min="{{ $field['min'] ?? 0 }}" step="{{ $field['step'] ?? 'any' }}" @endif
                                maxlength="{{ $field['max'] ?? 255 }}"
                                @required($field['required'] ?? false)
                            >
                        @endif
                        @error($field['name'])<span class="admin-field-error">{{ $message }}</span>@enderror
                    </div>
                @endforeach
            </div>
            <div class="admin-form-actions">
                <a class="outline-button" href="{{ route($routeName.'.index') }}">Batal</a>
                <button class="primary-button" type="submit">{{ $record ? 'Simpan Perubahan' : 'Simpan Data' }}</button>
            </div>
        </form>
    </div>
</section>
@endsection
