
@extends('layouts.app')

@section('content')
<div class="dc-mutation-page">

    {{-- FILTER PANEL --}}
    <section class="dashboard-card dc-mutation-filter">
        <div class="dc-mutation-filter-heading">
            <div class="dc-mutation-heading-icon">🗓️</div>
            <div>
                <h3>Filter Riwayat Mutasi</h3>
                <p>Pilih periode dan jenis mutasi untuk melihat pergerakan stok gudang DC.</p>
                <small>
                    Total {{ number_format($mutations->total(), 0, ',', '.') }} data mutasi ditemukan
                </small>
            </div>
        </div>

        <form method="POST" action="{{ route('dc.mutation.filter') }}" class="dc-mutation-filter-form">
    @csrf

            <div class="dc-mutation-field">
                <label for="keyword">Cari Bahan Baku</label>
                <input
                    type="text"
                    id="keyword"
                    name="keyword"
                    value="{{ $filters['keyword'] ?? '' }}"
                    placeholder="Masukkan nama bahan..."
                >
            </div>

            <div class="dc-mutation-field">
                <label for="jenis_mutasi">Jenis Mutasi</label>
                <select id="jenis_mutasi" name="jenis_mutasi">
                    <option value="">Semua Jenis</option>
                    <option value="penerimaan_dc" @selected(($filters['jenis_mutasi'] ?? '') === 'penerimaan_dc')>
                        Stok Masuk
                    </option>
                    <option value="pengiriman_dc" @selected(($filters['jenis_mutasi'] ?? '') === 'pengiriman_dc')>
                        Pengiriman ke Cabang
                    </option>
                    <option value="pembatalan_pengiriman_dc" @selected(($filters['jenis_mutasi'] ?? '') === 'pembatalan_pengiriman_dc')>
                        Pembatalan Pengiriman
                    </option>
                </select>
            </div>

            <div class="dc-mutation-field">
                <label for="tanggal_mulai">Dari Tanggal</label>
                <input
                    type="date"
                    id="tanggal_mulai"
                    name="tanggal_mulai"
                    value="{{ $filters['tanggal_mulai'] ?? '' }}"
                >
            </div>

            <div class="dc-mutation-field">
                <label for="tanggal_akhir">Sampai Tanggal</label>
                <input
                    type="date"
                    id="tanggal_akhir"
                    name="tanggal_akhir"
                    value="{{ $filters['tanggal_akhir'] ?? '' }}"
                >
            </div>

            <div class="dc-mutation-filter-actions">
                <button type="submit" class="primary-button">
                    Terapkan Filter →
                </button>

                <button
                    type="submit"
                    form="dc-mutation-reset-form"
                    class="outline-button"
                >
                    Reset Filter
                </button>
            </div>
        </form>
        <form
            id="dc-mutation-reset-form"
            method="POST"
            action="{{ route('dc.mutation.reset') }}"
            style="display: none;"
        >
            @csrf
        </form>
    </section>

    {{-- SUMMARY --}}
    <section class="dc-mutation-summary">
        <div class="dc-mutation-summary-card">
            <div class="dc-mutation-summary-top">
                <span>📦</span>
                <small>Total Data</small>
            </div>
            <strong>{{ number_format($summary['total'], 0, ',', '.') }}</strong>
            <p>Riwayat pergerakan stok</p>
        </div>

        <div class="dc-mutation-summary-card">
            <div class="dc-mutation-summary-top">
                <span>🟢</span>
                <small>Stok Masuk</small>
            </div>
            <strong>{{ number_format($summary['penerimaan'], 0, ',', '.') }}</strong>
            <p>Transaksi penambahan stok gudang DC</p>
        </div>

        <div class="dc-mutation-summary-card">
            <div class="dc-mutation-summary-top">
                <span>🚚</span>
                <small>Stok Keluar</small>
            </div>
            <strong>{{ number_format($summary['pengiriman'], 0, ',', '.') }}</strong>
            <p>Transaksi pengiriman bahan ke cabang</p>
        </div>

        <div class="dc-mutation-summary-card">
            <div class="dc-mutation-summary-top">
                <span>↩️</span>
                <small>Pembatalan</small>
            </div>
            <strong>{{ number_format($summary['pembatalan'], 0, ',', '.') }}</strong>
            <p>Transaksi pembatalan pengiriman</p>
        </div>
    </section>

    {{-- TABLE --}}
    <section class="dashboard-card dc-mutation-table-card">

        <div class="dc-mutation-table-heading">
            <div>
                <h3>Riwayat Pergerakan Stok</h3>
                <p>Catatan seluruh aktivitas stok masuk, pengiriman, dan pembatalan pengiriman DC.</p>
            </div>

            <span class="dc-mutation-total">
                {{ $mutations->count() }} dari {{ $mutations->total() }} data
            </span>
        </div>

        <div class="table-wrapper">
            <table class="table-fixed dc-mutation-table">
                <thead>
                    <tr>
                        <th style="width: 14%">Tanggal</th>
                        <th style="width: 19%">Bahan Baku</th>
                        <th style="width: 19%">Jenis Mutasi</th>
                        <th style="width: 15%; text-align: right">Jumlah</th>
                        <th style="width: 13%">Referensi</th>
                        <th style="width: 20%">Keterangan</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($mutations as $mutation)
                        @php
                            $type = $mutation->jenis_mutasi;

                            $label = match ($type) {
                                'penerimaan_dc' => 'Stok Masuk',
                                'pengiriman_dc' => 'Pengiriman ke Cabang',
                                'pembatalan_pengiriman_dc' => 'Pembatalan Pengiriman',
                                default => $type,
                            };

                            $isIncoming = in_array($type, [
                                'penerimaan_dc',
                                'pembatalan_pengiriman_dc',
                            ], true);
                        @endphp

                        <tr>
                            <td class="col-nowrap">
                                <strong>{{ $mutation->tanggal?->format('d M Y') ?? '-' }}</strong>
                                <span class="dc-mutation-secondary">
                                    {{ $mutation->tanggal?->format('H:i') ?? '-' }} WIB
                                </span>
                            </td>

                            <td>
                                <strong>{{ $mutation->bahan?->nama_bahan ?? 'Bahan tidak ditemukan' }}</strong>
                                <span class="dc-mutation-secondary">
                                    {{ $mutation->bahan?->satuan ?? '' }}
                                </span>
                            </td>

                            <td>
                                @if ($type === 'penerimaan_dc')
                                    <span class="status success">{{ $label }}</span>
                                @elseif ($type === 'pengiriman_dc')
                                    <span class="status info">{{ $label }}</span>
                                @else
                                    <span class="status warning">{{ $label }}</span>
                                @endif
                            </td>

                            <td class="col-nowrap" style="text-align: right">
                                <strong class="{{ $isIncoming ? 'positive' : 'danger-text' }}">
                                    {{ $isIncoming ? '+' : '-' }}{{ number_format((float) $mutation->jumlah, 3, ',', '.') }}
                                </strong>
                            </td>

                            <td class="col-nowrap">
                                @if ($mutation->referensi_id)
                                    <span class="dc-mutation-reference">
                                        SJ-{{ str_pad($mutation->referensi_id, 4, '0', STR_PAD_LEFT) }}
                                    </span>
                                @else
                                    <span class="dc-mutation-secondary">Manual</span>
                                @endif
                            </td>

                            <td>
                                <span class="dc-mutation-description">
                                    {{ $mutation->keterangan ?: 'Tidak ada keterangan' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="dc-mutation-empty">
                                    <span>📋</span>
                                    <strong>Belum Ada Riwayat Mutasi</strong>
                                    <p>Data akan muncul ketika terdapat penambahan stok atau pengiriman barang.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($mutations->hasPages())
            <div class="dc-mutation-pagination">
                {{ $mutations->links() }}
            </div>
        @endif
    </section>

</div>
@endsection
