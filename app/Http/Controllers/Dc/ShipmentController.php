<?php

namespace App\Http\Controllers\Dc;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\DetailSuratJalan;
use App\Models\DraftInstruksi;
use App\Models\SuratJalan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    /**
     * Halaman Pengiriman & Surat Jalan (DC) — data dari database,
     * format sama dengan demo lama agar view tidak berubah.
     */
    public function index(Request $request): View
    {
        $items = BahanBaku::query()->orderBy('bahan_id')->get()
            ->mapWithKeys(fn ($b) => [(string) $b->bahan_id => [
                'name' => $b->nama_bahan,
                'category' => $b->kategori,
                'pack' => $b->nama_kemasan,
                'size' => (float) $b->isi_per_kemasan,
                'unit' => $b->satuan,
                'price' => (float) $b->harga_beli,
            ]])->all();

        $storeList = Cabang::query()->orderBy('cabang_id')->get()
            ->map(fn ($c) => ['id' => (string) $c->cabang_id, 'name' => $c->nama_cabang])
            ->all();

        $shipments = SuratJalan::query()
            ->with(['detail.bahan', 'konfirmasi'])
            ->orderByDesc('surat_jalan_id')
            ->get()
            ->map(fn ($sj) => $this->format($sj))
            ->values()->all();

        return view('dc.pengiriman.index', [
            'items' => $items,
            'storeList' => $storeList,
            'shipments' => $shipments,
        ]);
    }

    /**
     * DC membuat surat jalan dari draft yang disetujui manager.
     * Payload: { draft_id, items: [{ bahan_id, qty_dikirim }] }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'draft_id' => ['required', 'integer', 'exists:draft_instruksi,draft_id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.bahan_id' => ['required', 'integer', 'exists:bahan_baku,bahan_id'],
            'items.*.qty_dikirim' => ['required', 'numeric', 'min:0.001'],
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($data, $user) {
            $draft = DraftInstruksi::query()
                ->where('draft_id', $data['draft_id'])
                ->where('status', 'disetujui')
                ->lockForUpdate()
                ->firstOrFail();

            $sj = SuratJalan::query()->create([
                'cabang_id' => $draft->cabang_id,
                'dc_id' => $user->dc_id,
                'diterbitkan_oleh' => $user->user_id,
                'tanggal_kirim' => now(),
                'status' => 'disiapkan',
            ]);

            $detailDraftMap = $draft->detail()->get()->keyBy('bahan_id');

            foreach ($data['items'] as $item) {
                DetailSuratJalan::query()->create([
                    'surat_jalan_id' => $sj->surat_jalan_id,
                    'bahan_id' => $item['bahan_id'],
                    'detail_draft_id' => $detailDraftMap->get($item['bahan_id'])?->detail_draft_id,
                    'qty_dikirim' => (float) $item['qty_dikirim'],
                    'qty_diterima' => null,
                ]);
            }

            $draft->status = 'diproses';
            $draft->save();

            return response()->json([
                'surat_jalan_id' => $sj->surat_jalan_id,
                'no' => sprintf('#SJ-%03d', $sj->surat_jalan_id),
                'status' => 'dikirim',
            ], 201);
        });
    }

    /**
     * DC mengubah status disiapkan → dikirim.
     */
    public function kirim(Request $request, int $id): JsonResponse
    {
        $sj = SuratJalan::query()->where('surat_jalan_id', $id)->firstOrFail();

        abort_unless($sj->status === 'disiapkan', 422, 'Surat jalan sudah dikirim.');

        $sj->status = 'dikirim';
        $sj->tanggal_kirim = now();
        $sj->save();

        return response()->json([
            'surat_jalan_id' => $sj->surat_jalan_id,
            'no' => sprintf('#SJ-%03d', $sj->surat_jalan_id),
            'status' => 'menunggu',
        ]);
    }

    private function format(SuratJalan $sj): array
    {
        $items = $sj->detail->map(fn ($d) => [
            'item' => (string) $d->bahan_id,
            'sent' => (int) $d->qty_dikirim,
            'received' => $d->qty_diterima === null ? null : (int) $d->qty_diterima,
            'note' => null,
        ])->values()->all();

        return [
            'id' => $sj->surat_jalan_id,
            'no' => sprintf('#SJ-%03d', $sj->surat_jalan_id),
            'date' => $sj->tanggal_kirim?->format('d M Y') ?? '-',
            'store' => (string) $sj->cabang_id,
            'order' => null,
            'origin' => 'DC',
            'status' => $sj->computed_status,
            'items' => $items,
        ];
    }
}
