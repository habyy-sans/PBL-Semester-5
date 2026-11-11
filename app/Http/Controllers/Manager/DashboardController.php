<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\DraftInstruksi;
use App\Models\NotifikasiAlert;
use App\Models\StockMovement;
use App\Models\StokCabang;
use App\Models\SuratJalan;
use App\Models\TransaksiPenjualan;
use App\Models\WasteLog;
use App\Services\CatalogService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly ReportService $report,
    ) {}

    /**
     * Dashboard Manager — window.KOPSYNC diisi dari database dengan bentuk
     * yang sama persis seperti data demo lama supaya dashboard.js tidak berubah.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $cabangId = (int) $user->cabang_id;
        $cabang = Cabang::query()->find($cabangId);

        $namaCabang = $cabang->nama_cabang ?? 'Cabang';
        $parts = explode(' · ', $namaCabang);

        return view('manager.dashboard.index', [
            'branchName' => count($parts) > 1 ? $parts[0] : $namaCabang,
            'storeName' => $namaCabang,
            'payload' => [
                'items' => $this->catalog->items($cabangId),
                'menus' => $this->catalog->menus(),
                'transactions' => $this->transactions($cabangId),
                'mutations' => $this->mutations($cabangId),
                'shipments' => $this->shipments($cabangId),
                'summary' => $this->report->branchSummary($cabangId),
                'draftNo' => $this->latestDraftNo($cabangId),
            ],
        ]);
    }

    /**
     * Halaman Alert — data restock (stok di bawah minimum) + mutasi waste
     * dari database, plus no. draft instruksi terbaru.
     */
    public function alert(Request $request): View
    {
        $cabangId = (int) $request->user()->cabang_id;
        $requestedStockId = $request->integer('stok_cabang_id');
        $focusStockKey = $requestedStockId > 0
            ? StokCabang::query()
                ->where('stok_cabang_id', $requestedStockId)
                ->where('cabang_id', $cabangId)
                ->value('bahan_id')
            : null;
        $requestedWasteId = $request->integer('waste_id');
        $focusWasteGroup = $requestedWasteId > 0
            ? WasteLog::query()
                ->where('waste_id', $requestedWasteId)
                ->where('cabang_id', $cabangId)
                ->value('kelompok_id')
            : null;

        $pendingDraft = DraftInstruksi::query()
            ->where('cabang_id', $cabangId)
            ->whereIn('status', ['menunggu_persetujuan', 'menunggu'])
            ->orderByDesc('draft_id')
            ->get();

        $wasteAlerts = NotifikasiAlert::query()
            ->where('cabang_id', $cabangId)
            ->where('tipe', 'waste')
            ->get()
            ->mapWithKeys(function ($alert) {
                preg_match(
                    '/\[kelompok_id=([^\]]+)\]/',
                    $alert->pesan,
                    $matches
                );

                return ! empty($matches[1])
                    ? [$matches[1] => [
                        'alert_id' => $alert->alert_id,
                        'status_baca' => (bool) $alert->status_baca,
                    ]]
                    : [];
            });

        return view('manager.alert.index', [
            'items' => $this->catalog->items($cabangId),
            'mutations' => $this->mutations($cabangId),
            'draftNo' => $pendingDraft->first()
                ? sprintf('#RS-%04d', $pendingDraft->first()->draft_id)
                : null,
            'draftCount' => $pendingDraft->count(),
            'wasteAlerts' => $wasteAlerts,
            'focusStockKey' => $focusStockKey,
            'focusWasteGroup' => $focusWasteGroup,
        ]);
    }

    public function evaluateWaste(Request $request, int $alertId): JsonResponse
    {
        $alert = NotifikasiAlert::query()
            ->where('alert_id', $alertId)
            ->where('cabang_id', $request->user()->cabang_id)
            ->where('tipe', 'waste')
            ->firstOrFail();

        $alert->update([
            'status_baca' => true,
        ]);

        return response()->json([
            'message' => 'Alert waste berhasil ditandai sudah dievaluasi.',
        ]);
    }

    private function latestDraftNo(int $cabangId): ?string
    {
        $draftId = DraftInstruksi::query()
            ->where('cabang_id', $cabangId)
            ->max('draft_id');

        return $draftId !== null ? sprintf('#RS-%04d', $draftId) : null;
    }

    /**
     * Riwayat transaksi (berhasil/batal) untuk JS dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    private function transactions(int $cabangId): array
    {
        return TransaksiPenjualan::query()
            ->where('cabang_id', $cabangId)
            ->with(['detail.varian', 'kasir', 'pembayaran'])
            ->orderByDesc('tanggal')
            ->limit(300)
            ->get()
            ->map(fn ($trx) => [
                'no' => $trx->no_transaksi ?? sprintf('#TRX-%04d', $trx->transaksi_id),
                'date' => $trx->tanggal
                    ->timezone('Asia/Jakarta')
                    ->format('Y-m-d'),

                'time' => $trx->tanggal
                    ->timezone('Asia/Jakarta')
                    ->format('H:i:s'),
                'cashier' => $trx->kasir?->nama_lengkap ?? '-',
                'method' => $trx->pembayaran?->metode ?? 'tunai',
                'cash' => $trx->pembayaran?->jumlah_diterima !== null
                    ? (float) $trx->pembayaran->jumlah_diterima
                    : null,
                'status' => match ($trx->status) {
                    'berhasil' => 'selesai',
                    'menunggu_pembayaran' => 'pending',
                    default => 'batal',
                },
                'reason' => $trx->alasan_batal,
                'items' => $trx->detail
                    ->filter(fn ($line) => $line->varian !== null)
                    ->map(fn ($line) => [
                        'menu' => (string) $line->varian->menu_id,
                        'qty' => (int) $line->qty,
                    ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Riwayat mutasi stok cabang untuk JS dashboard & halaman alert.
     * Format sama dengan demo lama: type masuk/keluar/waste, lines bersign.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mutations(int $cabangId): array
    {
        $rows = StockMovement::query()
            ->where('cabang_id', $cabangId)
            ->orderByDesc('tanggal')
            ->limit(500)
            ->get();

        // Satu laporan waste (beberapa bahan dalam satu pengajuan) = satu entri,
        // sama seperti data demo lama.
        return $rows
            ->groupBy(fn ($movement) => $movement->jenis_mutasi === 'waste'
                ? 'waste-'.($movement->kelompok_id
                    ?? $movement->tanggal->toDateTimeString().'-'.$movement->keterangan)
                : 'row-'.$movement->movement_id)
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'id' => (int) $group->min('movement_id'),
                    'date' => $first->tanggal
                        ->timezone('Asia/Jakarta')
                        ->format('Y-m-d'),

                    'time' => $first->tanggal
                        ->timezone('Asia/Jakarta')
                        ->format('H:i:s'),
                    'type' => match ($first->jenis_mutasi) {
                        'penerimaan_cabang' => 'masuk',
                        'waste' => 'waste',
                        default => 'keluar',
                    },
                    'ref' => $first->keterangan ?? '',
                    'group_id' => $first->kelompok_id,
                    'lines' => $group->map(fn ($movement) => [
                        'item' => (string) $movement->bahan_id,
                        'amount' => in_array($movement->jenis_mutasi, ['penjualan', 'waste'], true)
                            ? -(float) $movement->jumlah
                            : (float) $movement->jumlah,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Catatan per bahan dibaca kembali dari konfirmasi_penerimaan.kondisi
     *
     * @return array<string, string>
     */
    private function notesFromKondisi(?string $kondisi): array
    {
        if ($kondisi === null || ! preg_match('/^(?:selisih|baik): (.+)$/su', $kondisi, $m)) {
            return [];
        }

        $notes = [];

        foreach (explode('; ', $m[1]) as $part) {
            $pair = explode(': ', $part, 2);

            if (count($pair) === 2) {
                $notes[trim($pair[0])] = trim($pair[1]);
            }
        }

        return $notes;
    }

    /**
     * Surat jalan (pengiriman DC) untuk JS dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    private function shipments(int $cabangId): array
    {
        return SuratJalan::query()
            ->where('cabang_id', $cabangId)
            ->with(['detail.bahan', 'detail.detailDraft', 'konfirmasi'])
            ->orderByDesc('surat_jalan_id')
            ->limit(100)
            ->get()
            ->map(function ($sj) {
                $notes = $this->notesFromKondisi($sj->konfirmasi?->kondisi);
                $draftId = $sj->detail->first()?->detailDraft?->draft_id;

                return [
                    'no' => sprintf('#SJ-%03d', $sj->surat_jalan_id),
                    'date' => $sj->tanggal_kirim?->format('d M Y') ?? '-',
                    'origin' => 'Distribution Center',
                    'store' => (string) $sj->cabang_id,
                    'order' => $draftId ? sprintf('#RS-%04d', $draftId) : '-',
                    'status' => $sj->computed_status,
                    'items' => $sj->detail
                        ->map(fn ($detail) => [
                            'item' => (string) $detail->bahan_id,
                            'sent' => (int) $detail->qty_dikirim,
                            'received' => $detail->qty_diterima === null ? null : (int) $detail->qty_diterima,
                            'note' => $notes[$detail->bahan?->nama_bahan ?? ''] ?? null,
                        ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
