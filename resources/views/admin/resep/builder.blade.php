@extends('layouts.app')

@section('title', $isLockedVariant && $selectedVariant ? 'Atur Resep: '.($selectedVariant->menu?->nama_menu ?? 'Menu').' - '.$selectedVariant->nama_varian : 'Susun Resep Menu')
@section('description', 'Susun seluruh takaran bahan baku per 1 porsi varian menu untuk kalkulasi HPP dan potong stok otomatis di kasir.')

@section('content')
@php
    $selectedVarianId = (string) old('varian_id', $selectedVariant?->varian_id ?? '');
    $oldItems = old('items');
    $initialRows = [];

    if (is_array($oldItems)) {
        foreach ($oldItems as $row) {
            if (is_array($row)) {
                $initialRows[] = [
                    'bahan_id' => (string) ($row['bahan_id'] ?? ''),
                    'jumlah_bahan' => (string) ($row['jumlah_bahan'] ?? ''),
                ];
            }
        }
    } else {
        $initialRows = $initialItems;
    }

    $formAction = $isLockedVariant && $selectedVariant
        ? route('admin.resep-menu.sync', $selectedVariant)
        : route('admin.resep-menu.store');

    $initialSellingPrice = $selectedVariant
        ? (float) ($selectedVariant->menu?->harga_jual ?? 0) + (float) $selectedVariant->harga_tambahan
        : 0.0;
@endphp

<section class="page active admin-panel">
    <div class="admin-subnav">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('admin.resep-menu.index') }}">Kelola Resep Menu</a>
            <span aria-hidden="true">/</span>
            <span>{{ $isLockedVariant && $selectedVariant ? ($selectedVariant->menu?->nama_menu ?? 'Menu').' - '.$selectedVariant->nama_varian : 'Susun Resep' }}</span>
        </nav>

        <a class="admin-btn-outline admin-btn-sm" href="{{ route('admin.resep-menu.index') }}">
            <x-icon name="chevron-left" size="1em" />
            <span>Kembali</span>
        </a>
    </div>

    <x-admin.flash />

    @if ($variants->isEmpty())
        <div class="admin-card">
            <x-admin.empty-state
                icon="layers"
                title="Belum ada varian menu tersedia"
                description="Tambahkan Menu Produk beserta variannya terlebih dahulu sebelum menyusun komposisi resep."
                :actionUrl="route('admin.menu-produk.create')"
                actionLabel="Tambah Menu Produk"
            />
        </div>
    @elseif ($ingredients === [])
        <div class="admin-card">
            <x-admin.empty-state
                icon="package"
                title="Belum ada katalog bahan baku"
                description="Tambahkan bahan baku terlebih dahulu agar dapat dipilih sebagai penyusun resep menu."
                :actionUrl="route('admin.bahan-baku.create')"
                actionLabel="Tambah Bahan Baku"
            />
        </div>
    @else
        <div
            class="admin-recipe-layout"
            x-data="createRecipeState({
                inputName: 'items',
                ingredients: @js($ingredients),
                variants: @js($variantPayload),
                selectedVarianId: @js($selectedVarianId),
                isLockedVariant: @js($isLockedVariant),
                required: true,
                sellingPrice: @js($initialSellingPrice),
                initialRows: @js($initialRows),
                serverErrors: @js($errors->getMessages()),
            })"
        >
            <div class="admin-card admin-form-card">
                <div class="admin-form-header">
                    <h3>Komposisi Bahan Baku</h3>
                    <p>Tambahkan seluruh bahan baku penyusun 1 porsi varian ini. Stok cabang otomatis terpotong sesuai takaran saat transaksi dibayar.</p>
                </div>

                <form method="POST" action="{{ $formAction }}" data-prevent-double-submit>
                    @csrf
                    @if ($isLockedVariant && $selectedVariant)
                        @method('PUT')
                    @endif

                    <div class="admin-recipe-variant-box">
                        @if ($isLockedVariant && $selectedVariant)
                            <input type="hidden" name="varian_id" value="{{ $selectedVariant->varian_id }}">
                            <div class="admin-recipe-variant-meta">
                                <div>
                                    <span class="admin-stat-label">Menu & Varian Terpilih</span>
                                    <strong class="admin-recipe-variant-title">
                                        {{ $selectedVariant->menu?->nama_menu ?? '-' }} - {{ $selectedVariant->nama_varian }}
                                    </strong>
                                    <span class="admin-cell-sub">
                                        Kategori: {{ $selectedVariant->menu?->kategori?->nama_kategori ?? '-' }}
                                    </span>
                                </div>
                                <div class="admin-recipe-variant-price">
                                    <span class="admin-stat-label">Harga Jual</span>
                                    <strong class="tabular-nums">Rp{{ number_format($initialSellingPrice, 0, ',', '.') }}</strong>
                                </div>
                            </div>
                        @else
                            <div class="admin-form-field full-width">
                                <label for="builder-varian-id">
                                    Varian menu <span class="required-mark">*</span>
                                </label>
                                <select
                                    id="builder-varian-id"
                                    name="varian_id"
                                    x-model="selectedVarianId"
                                    @change="onVariantChange()"
                                    aria-invalid="{{ $errors->has('varian_id') ? 'true' : 'false' }}"
                                    required
                                >
                                    <option value="">Pilih varian menu</option>
                                    @foreach ($variantPayload as $v)
                                        <option value="{{ $v['varian_id'] }}" @selected($selectedVarianId === (string) $v['varian_id'])>
                                            {{ $v['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="admin-field-help">Memilih varian yang sudah memiliki resep akan memuat bahan yang ada untuk disesuaikan.</span>
                                @error('varian_id')<span class="admin-field-error">{{ $message }}</span>@enderror
                            </div>
                        @endif
                    </div>

                    <x-admin.recipe-repeater
                        inputName="items"
                        :ingredients="$ingredients"
                        :required="true"
                        addButtonLabel="Tambah Bahan Baku"
                    />

                    <div class="admin-form-actions">
                        <a class="admin-btn-outline" href="{{ route('admin.resep-menu.index') }}">Batal</a>
                        <button class="admin-btn-primary" type="submit">Simpan Komposisi Resep</button>
                    </div>
                </form>
            </div>

            <aside class="admin-sop-preview" aria-label="Pratinjau Takaran SOP">
                <div class="admin-card">
                    <div class="admin-sop-head">
                        <h3>
                            Takaran SOP ·
                            <span x-text="currentVariant() ? currentVariant().menu_name : 'Pilih Menu'"></span>
                        </h3>
                        <span
                            class="admin-badge is-info"
                            x-show="currentVariant()"
                            x-text="currentVariant()?.variant_name || ''"
                        ></span>
                    </div>

                    <p class="admin-sop-desc">
                        Formula racikan per 1 porsi. Bahan akan otomatis terpotong saat pesanan dibayar.
                    </p>

                    <div class="admin-sop-box">
                        <span class="admin-sop-box-title">RESEP BAHAN BAKU</span>

                        <template x-if="validRows().length > 0">
                            <div class="admin-sop-list">
                                <template x-for="(item, idx) in validRows()" :key="idx">
                                    <div class="admin-sop-row">
                                        <span x-text="item.ing.nama_bahan"></span>
                                        <strong class="tabular-nums" x-text="formatQty(item.qty) + ' ' + item.ing.satuan"></strong>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="validRows().length === 0">
                            <p class="admin-sop-empty">Pilih bahan baku dan isi takarannya di sebelah kiri untuk melihat pratinjau SOP.</p>
                        </template>
                    </div>

                    <dl class="admin-sop-metrics">
                        <div>
                            <dt>Total Estimasi HPP</dt>
                            <dd class="tabular-nums" x-text="formatRupiah(totalHpp())">Rp0</dd>
                        </div>
                        <div>
                            <dt>Harga Jual</dt>
                            <dd class="tabular-nums" x-text="formatRupiah(effectiveSellingPrice())">Rp0</dd>
                        </div>
                        <div :class="estimatedProfit() < 0 ? 'is-danger' : 'is-success'">
                            <dt>Estimasi Laba / Porsi</dt>
                            <dd class="tabular-nums">
                                <span x-text="formatRupiah(estimatedProfit())">Rp0</span>
                                <small x-show="effectiveSellingPrice() > 0" x-text="'(' + Math.round(estimatedMargin()) + '%)'"></small>
                            </dd>
                        </div>
                    </dl>

                    <div class="admin-sop-warning" x-show="effectiveSellingPrice() > 0 && totalHpp() > effectiveSellingPrice()">
                        <x-icon name="triangle-alert" size="1em" />
                        <span>Total HPP melebihi harga jual saat ini. Pertimbangkan evaluasi harga atau takaran.</span>
                    </div>
                </div>
            </aside>
        </div>
    @endif
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
