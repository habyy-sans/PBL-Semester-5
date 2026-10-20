<?php

namespace App\Services;

use App\Models\BahanBaku;
use App\Models\MenuProduk;
use App\Models\StokCabang;
use App\Models\StockMovement;
use App\Models\SuratJalan;
use App\Models\TransaksiPenjualan;
use App\Models\VarianMenu;
use App\Models\WasteLog;

/**
 * Service aggregate laporan bersama (dashboard manager, laporan cabang,
 * laporan admin) yang menghitung dari transaksi_penjualan + detail_transaksi
 * + resep_menu + waste_log + stock_movements.
 *
 * Semua metode menerima ?int $cabangId: null = seluruh cabang.
 */
class ReportService
{
    /** @var array<int, float>|null */
    private ?array $unitCosts = null;

    /** @var array<int, float>|null */
    private ?array $portionCosts = null;

    /**
     * Biaya per satuan dasar per bahan = harga_beli / isi_per_kemasan.
     *
     * @return array<int, float>
     */
    public function unitCosts(): array
    {
        if ($this->unitCosts !== null) {
            return $this->unitCosts;
        }

        return $this->unitCosts = BahanBaku::query()
            ->get()
            ->mapWithKeys(fn ($bahan) => [
                $bahan->bahan_id => (float) $bahan->harga_beli / max((float) $bahan->isi_per_kemasan, 1),
            ])
            ->all();
    }

    /**
     * HPP per porsi per varian menu = Σ (jumlah_bahan resep × biaya satuan).
     *
     * @return array<int, float>
     */
    public function portionCosts(): array
    {
        if ($this->portionCosts !== null) {
            return $this->portionCosts;
        }

        $unitCosts = $this->unitCosts();
        $costs = [];

        VarianMenu::query()->with('resep')->get()->each(function ($varian) use (&$costs, $unitCosts) {
            $cost = 0.0;

            foreach ($varian->resep as $resep) {
                $cost += (float) $resep->jumlah_bahan * ($unitCosts[$resep->bahan_id] ?? 0.0);
            }

            $costs[$varian->varian_id] = $cost;
        });

        return $this->portionCosts = $costs;
    }

    /**
     * Estimasi laba rugi per hari dari transaksi berhasil + waste.
     *
     * @return array<int, array{date: string, count: int, omzet: float, hpp: float, waste: float, waste_count: int, laba: float, margin: float}>
     */
    public function dailyProfit(?int $cabangId, ?string $from = null, ?string $to = null): array
    {
        $portionCosts = $this->portionCosts();

        $perHari = [];

        TransaksiPenjualan::query()
            ->select(['transaksi_id', 'cabang_id', 'tanggal'])
            ->where('status', 'berhasil')
            ->when($cabangId !== null, fn ($query) => $query->where('cabang_id', $cabangId))
            ->when($from !== null, fn ($query) => $query->whereDate('tanggal', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('tanggal', '<=', $to))
            ->with(['detail.varian'])
            ->get()
            ->each(function ($trx) use (&$perHari, $portionCosts) {
                $date = $trx->tanggal->toDateString();

                $omzet = 0.0;
                $hpp = 0.0;

                foreach ($trx->detail as $line) {
                    $omzet += (float) $line->subtotal;
                    $hpp += (int) $line->qty * ($portionCosts[$line->varian_id] ?? 0.0);
                }

                $row = $perHari[$date] ?? ['count' => 0, 'omzet' => 0.0, 'hpp' => 0.0, 'waste' => 0.0, 'waste_count' => 0];

                $row['count'] += 1;
                $row['omzet'] += $omzet;
                $row['hpp'] += $hpp;

                $perHari[$date] = $row;
            });

        $unitCosts = $this->unitCosts();

        WasteLog::query()
            ->when($cabangId !== null, fn ($query) => $query->where('cabang_id', $cabangId))
            ->when($from !== null, fn ($query) => $query->whereDate('tanggal', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('tanggal', '<=', $to))
            ->get(['waste_id', 'bahan_id', 'qty', 'tanggal'])
            ->each(function ($waste) use (&$perHari, $unitCosts) {
                $date = $waste->tanggal->toDateString();

                $row = $perHari[$date] ?? ['count' => 0, 'omzet' => 0.0, 'hpp' => 0.0, 'waste' => 0.0, 'waste_count' => 0];

                $row['waste'] += (float) $waste->qty * ($unitCosts[$waste->bahan_id] ?? 0.0);
                $row['waste_count'] += 1;

                $perHari[$date] = $row;
            });

        ksort($perHari);

        return collect($perHari)
            ->map(function ($row, $date) {
                $laba = $row['omzet'] - $row['hpp'] - $row['waste'];

                return [
                    'date' => $date,
                    'count' => $row['count'],
                    'omzet' => $row['omzet'],
                    'hpp' => $row['hpp'],
                    'waste' => $row['waste'],
                    'waste_count' => $row['waste_count'],
                    'laba' => $laba,
                    'margin' => $row['omzet'] > 0 ? $laba / $row['omzet'] * 100 : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Penjualan per menu (dari detail transaksi + resep), diurutkan qty desc.
     *
     * @return array<int, array{menu_id: int, name: string, qty: int, omzet: float, hpp: float, laba: float}>
     */
    public function menuSales(?int $cabangId, ?string $from = null, ?string $to = null): array
    {
        $portionCosts = $this->portionCosts();

        $grouped = TransaksiPenjualan::query()
            ->select(['transaksi_id', 'cabang_id', 'tanggal'])
            ->where('status', 'berhasil')
            ->when($cabangId !== null, fn ($query) => $query->where('cabang_id', $cabangId))
            ->when($from !== null, fn ($query) => $query->whereDate('tanggal', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('tanggal', '<=', $to))
            ->with(['detail.varian'])
            ->get()
            ->flatMap(fn ($trx) => $trx->detail)
            ->groupBy(fn ($line) => $line->varian?->menu_id)
            ->filter(fn ($group, $menuId) => $menuId !== null);

        if ($grouped->isEmpty()) {
            return [];
        }

        $names = MenuProduk::query()
            ->whereIn('menu_id', $grouped->keys())
            ->pluck('nama_menu', 'menu_id');

        return $grouped
            ->map(function ($group, $menuId) use ($portionCosts, $names) {
                $qty = (int) $group->sum('qty');
                $omzet = (float) $group->sum('subtotal');
                $hpp = $group->sum(fn ($line) => (int) $line->qty * ($portionCosts[$line->varian_id] ?? 0.0));

                return [
                    'menu_id' => (int) $menuId,
                    'name' => $names[$menuId] ?? '-',
                    'qty' => $qty,
                    'omzet' => $omzet,
                    'hpp' => (float) $hpp,
                    'laba' => $omzet - (float) $hpp,
                ];
            })
            ->sortByDesc('qty')
            ->values()
            ->all();
    }

    /**
     * Mutasi stok per bahan (satuan dasar): masuk / keluar / waste.
     *
     * @return array<int, array{bahan_id: int, name: string, masuk: float, keluar: float, waste: float}>
     */
    public function stockMutations(?int $cabangId, ?string $from = null, ?string $to = null): array
    {
        $grouped = StockMovement::query()
            ->when($cabangId !== null, fn ($query) => $query->where('cabang_id', $cabangId))
            ->when($from !== null, fn ($query) => $query->whereDate('tanggal', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('tanggal', '<=', $to))
            ->get(['bahan_id', 'jenis_mutasi', 'jumlah'])
            ->groupBy('bahan_id');

        if ($grouped->isEmpty()) {
            return [];
        }

        $names = BahanBaku::query()
            ->whereIn('bahan_id', $grouped->keys())
            ->pluck('nama_bahan', 'bahan_id');

        return $grouped
            ->map(function ($group, $bahanId) use ($names) {
                $sum = fn (string $jenis) => (float) $group
                    ->where('jenis_mutasi', $jenis)
                    ->sum('jumlah');

                return [
                    'bahan_id' => (int) $bahanId,
                    'name' => $names[$bahanId] ?? '-',
                    'masuk' => $sum('penerimaan_cabang'),
                    'keluar' => $sum('penjualan'),
                    'waste' => $sum('waste'),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Ringkasan dashboard manager untuk 1 cabang:
     * omzet/hpp/waste hari ini, delta vs kemarin, stok menipis, pesanan DC.
     *
     * @return array<string, mixed>
     */
    public function branchSummary(int $cabangId): array
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $daily = collect($this->dailyProfit($cabangId, $yesterday, $today))->keyBy('date');

        $todayRow = $daily->get($today);
        $yesterdayRow = $daily->get($yesterday);

        $omzet = $todayRow['omzet'] ?? 0.0;
        $hpp = $todayRow['hpp'] ?? 0.0;
        $waste = $todayRow['waste'] ?? 0.0;
        $prevOmzet = $yesterdayRow['omzet'] ?? 0.0;

        $low = StokCabang::query()
            ->where('cabang_id', $cabangId)
            ->whereColumn('jumlah_stok', '<=', 'stok_minimum')
            ->get(['bahan_id', 'jumlah_stok', 'stok_minimum']);

        return [
            'date' => $today,
            'omzet' => $omzet,
            'hpp' => $hpp,
            'waste' => $waste,
            'waste_count' => $todayRow['waste_count'] ?? 0,
            'laba' => $omzet - $hpp - $waste,
            'delta_pct' => $prevOmzet > 0 ? ($omzet - $prevOmzet) / $prevOmzet * 100 : null,
            'low_count' => $low->count(),
            'low_habis' => $low->filter(fn ($stok) => (float) $stok->jumlah_stok <= 0)->count(),
            'dc_pending' => SuratJalan::query()
                ->where('cabang_id', $cabangId)
                ->whereIn('status', ['disiapkan', 'dikirim'])
                ->count(),
            'dc_sent' => SuratJalan::query()
                ->where('cabang_id', $cabangId)
                ->where('status', 'dikirim')
                ->count(),
        ];
    }
}
