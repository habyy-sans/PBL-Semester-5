<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\DetailDraftInstruksi;
use App\Models\DraftInstruksi;
use App\Models\StokCabang;
use App\Models\BahanBaku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestockController extends Controller
{
    /**
     * Hitung draft restock real-time dari stok_cabang < stok_minimum (belum disimpan).
     * Hanya untuk cabang yang login. Juga kembalikan riwayat instruksi
     * (draft yang sudah disetujui) agar view tidak lagi pakai config demo.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $stokHabis = StokCabang::query()
            ->where('cabang_id', $user->cabang_id)
            ->whereColumn('jumlah_stok', '<', 'stok_minimum')
            ->with('bahan')
            ->orderBy('bahan_id')
            ->get();

        $draft = $stokHabis->map(function ($s) {
            return [
                'bahan_id' => $s->bahan_id,
                'nama_bahan' => $s->bahan?->nama_bahan ?? '-',
                'satuan' => $s->bahan?->satuan ?? '-',
                'nama_kemasan' => $s->bahan?->nama_kemasan ?? '-',
                'isi_per_kemasan' => (float) ($s->bahan?->isi_per_kemasan ?? 1),
                'harga_beli' => (float) ($s->bahan?->harga_beli ?? 0),
                'stok_aktual' => (float) $s->jumlah_stok,
                'stok_minimum' => (float) $s->stok_minimum,
                'kebutuhan' => (float) ($s->stok_minimum - $s->jumlah_stok),
            ];
        })->values()->all();

        $orders = DraftInstruksi::query()
            ->where('cabang_id', $user->cabang_id)
            ->with(['detail.bahan', 'cabang'])
            ->orderByDesc('draft_id')
            ->get()
            ->map(function ($d) {
                $lines = $d->detail->map(function ($dd) {
                    $isi = (float) ($dd->bahan?->isi_per_kemasan ?? 1);

                    return [
                        'item' => (string) $dd->bahan_id,
                        'name' => $dd->bahan?->nama_bahan ?? '-',
                        'qty' => $isi > 0 ? (int) ceil((float) $dd->qty_diminta / $isi) : (float) $dd->qty_diminta,
                        'qty_base' => (float) $dd->qty_diminta,
                        'pack' => $dd->bahan?->nama_kemasan ?? '-',
                        'harga_beli' => (float) ($dd->bahan?->harga_beli ?? 0),
                    ];
                })->values()->all();

                return [
                    'id' => $d->draft_id,
                    'no' => sprintf('#RS-%04d', $d->draft_id),
                    'date' => $d->dibuat_pada?->format('d M Y') ?? '-',
                    'source' => $d->asal,
                    'status' => $this->mapStatus($d->status),
                    'db_status' => $d->status,
                    'shipment' => null,
                    'items' => $lines,
                ];
            })->values()->all();

        return response()->json([
            'cabang_id' => $user->cabang_id,
            'count' => count($draft),
            'items' => $draft,
            'orders' => $orders,
        ]);
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'menunggu_persetujuan' => 'approval',
            'disetujui' => 'disetujui',
            'diproses' => 'dikirim',
            'selesai' => 'diterima',
            default => 'disetujui',
        };
    }

    /**
     * Manager approve draft → insert draft_instruksi + detail_draft_instruksi.
     *
     * Payload: { items: [{ bahan_id, qty_diminta }], asal: 'otomatis|manual', reason?: string }
     */
    public function approve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.bahan_id' => ['required', 'integer', 'exists:bahan_baku,bahan_id'],
            'items.*.qty_diminta' => ['required', 'numeric', 'min:0.001'],
            'asal' => ['required', 'in:otomatis,manual'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($data, $user) {
            // 1) Insert draft_instruksi (langsung disetujui Manager sesuai CHECK).
            $draft = DraftInstruksi::query()->create([
                'cabang_id' => $user->cabang_id,
                'dibuat_oleh' => $user->user_id,
                'asal' => $data['asal'],
                'status' => 'disetujui',
                'dibuat_pada' => now(),
                'disetujui_oleh' => $user->user_id,
                'tanggal_persetujuan' => now(),
            ]);

            // 2) Insert detail_draft_instruksi per bahan.
            foreach ($data['items'] as $item) {
                DetailDraftInstruksi::query()->create([
                    'draft_id' => $draft->draft_id,
                    'bahan_id' => $item['bahan_id'],
                    'qty_diminta' => (float) $item['qty_diminta'],
                ]);
            }

            // 3) Buat notifikasi alert untuk Manager/IC.
            \App\Models\NotifikasiAlert::query()->create([
                'cabang_id' => $user->cabang_id,
                'bahan_id' => null,
                'tipe' => 'stok_menipis',
                'pesan' => 'Draft Instruksi #' . sprintf('%04d', $draft->draft_id) . ' disetujui (' . $data['asal'] . ').',
                'status_baca' => false,
                'tanggal' => now(),
            ]);

            return response()->json([
                'draft_id' => $draft->draft_id,
                'no' => sprintf('#RS-%04d', $draft->draft_id),
                'status' => 'disetujui',
                'count' => count($data['items']),
            ]);
        });
    }
}