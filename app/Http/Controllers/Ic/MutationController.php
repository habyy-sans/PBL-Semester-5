<?php

namespace App\Http\Controllers\Ic;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Services\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MutationController extends Controller
{
    /** Batas baris stock_movements yang dimuat (terbaru dulu). */
    private const LIMIT = 3000;

    /** Hari pada riwayat dihitung menurut waktu lokal cabang. */
    private const TIMEZONE = 'Asia/Jakarta';

    public function __construct(
        private readonly CatalogService $catalog,
    ) {}

    /**
     * Halaman Histori Mutasi Stok (IC).
     *
     * Data dari tabel stock_movements milik cabang IC yang login, dikirim ke
     * view dengan format yang sama persis seperti data demo lama
     * (type masuk/keluar/waste, lines bertanda +/-).
     */
    public function index(Request $request): View
    {
        $cabangId = $request->user()->cabang_id;

        return view('ic.mutasi.index', [
            'items' => $this->catalog->items($cabangId),
            'mutations' => $this->mutations($cabangId),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mutations(?int $cabangId): array
    {
        if ($cabangId === null) {
            return [];
        }

        $rows = StockMovement::query()
            ->where('cabang_id', $cabangId)
            ->orderByDesc('tanggal')
            ->orderByDesc('movement_id')
            ->limit(self::LIMIT)
            ->get();

        return $rows
            ->groupBy(fn (StockMovement $movement) => $this->groupKey($movement))
            ->map(fn (Collection $group, string $key) => $this->entry($key, $group))
            ->filter(fn (array $entry) => $entry['lines'] !== [])
            ->values()
            ->all();
    }

    /**
     * Pengelompokan satu catatan di halaman:
     * - waste              : 1 laporan waste (kelompok_id sama)
     * - penerimaan_cabang  : 1 surat jalan
     * - penjualan          : digabung per hari
     * - lainnya            : 1 baris = 1 catatan
     */
    private function groupKey(StockMovement $movement): string
    {
        return match ($movement->jenis_mutasi) {
            'waste' => 'waste-'.($movement->kelompok_id ?? 'x'.$movement->movement_id),
            'penerimaan_cabang' => 'masuk-'.($movement->referensi_id ?? 'x'.$movement->movement_id),
            'penjualan' => 'jual-'.$this->localTime($movement)->format('Y-m-d'),
            default => 'lain-'.$movement->movement_id,
        };
    }

    /**
     * @param  Collection<int, StockMovement>  $group  urut terbaru dulu
     * @return array<string, mixed>
     */
    private function entry(string $key, Collection $group): array
    {
        /** @var StockMovement $first */
        $first = $group->first();
        $time = $this->localTime($first);
        $isIncoming = $first->jenis_mutasi === 'penerimaan_cabang';

        // Bahan yang sama dalam satu catatan dijumlahkan.
        $lines = $group
            ->groupBy('bahan_id')
            ->map(function (Collection $rows, $bahanId) use ($isIncoming) {
                $total = (float) $rows->sum(fn (StockMovement $row) => (float) $row->jumlah);

                return [
                    'item' => (string) $bahanId,
                    'amount' => $isIncoming ? $total : -$total,
                ];
            })
            ->filter(fn (array $line) => $line['amount'] != 0.0)
            ->values()
            ->all();

        return [
            'id' => $key,
            'date' => $time->format('Y-m-d'),
            'time' => $time->format('H:i'),
            'type' => match ($first->jenis_mutasi) {
                'penerimaan_cabang' => 'masuk',
                'waste' => 'waste',
                default => 'keluar',
            },
            'ref' => $this->reference($first, $group),
            'lines' => $lines,
        ];
    }

    /**
     * @param  Collection<int, StockMovement>  $group
     */
    private function reference(StockMovement $first, Collection $group): string
    {
        switch ($first->jenis_mutasi) {
            case 'penjualan':
                $count = $group->pluck('referensi_id')->filter()->unique()->count();

                return 'Penjualan · '.($count > 0 ? $count : $group->count()).' transaksi';

            case 'waste':
                $notes = $group->pluck('keterangan')->filter()->unique()->values();

                return match (true) {
                    $notes->count() === 1 => (string) $notes->first(),
                    $notes->count() > 1 => 'Waste · '.$notes->count().' alasan berbeda',
                    default => 'Waste',
                };

            case 'penerimaan_cabang':
                return $first->keterangan
                    ?: ($first->referensi_id ? sprintf('Penerimaan #SJ-%03d', $first->referensi_id) : 'Penerimaan');

            default:
                return $first->keterangan ?: (string) $first->jenis_mutasi;
        }
    }

    private function localTime(StockMovement $movement)
    {
        return $movement->tanggal->copy()->timezone(self::TIMEZONE);
    }
}