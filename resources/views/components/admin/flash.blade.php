@if (session('success'))
    <div class="admin-flash is-success" role="status" x-data="{ open: true }" x-show="open">
        <div class="admin-flash-body">
            <x-icon name="circle-check" size="1.15em" class="admin-flash-icon" />
            <div class="admin-flash-content">
                <p>{{ session('success') }}</p>
            </div>
        </div>
        <div class="admin-flash-actions">
            @if (session('success_action_url') && session('success_action_label'))
                <a class="admin-btn-outline admin-btn-sm" href="{{ session('success_action_url') }}">
                    {{ session('success_action_label') }}
                </a>
            @endif
            <button
                type="button"
                class="admin-flash-close"
                @click="open = false"
                aria-label="Tutup pemberitahuan"
            >
                <x-icon name="x" size="1em" />
            </button>
        </div>
    </div>
@endif

@if ($errors->any())
    <div class="admin-flash is-error" role="alert" x-data="{ open: true }" x-show="open">
        <div class="admin-flash-body">
            <x-icon name="circle-alert" size="1.15em" class="admin-flash-icon" />
            <div class="admin-flash-content">
                @if ($errors->count() === 1)
                    <p>{{ $errors->first() }}</p>
                @else
                    <p><strong>Periksa kembali isian berikut:</strong></p>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        <button
            type="button"
            class="admin-flash-close"
            @click="open = false"
            aria-label="Tutup pesan kesalahan"
        >
            <x-icon name="x" size="1em" />
        </button>
    </div>
@endif
