<?php

namespace App\Http\Controllers\Dc;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\DetailSuratJalan;
use App\Models\DraftInstruksi;
use App\Models\StockMovement;
use App\Models\StokDc;
use App\Models\SuratJalan;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Halaman Pengiriman & Surat Jalan (DC) — data dari database,
     * format sama dengan demo lama agar view tidak berubah.
     */
    public function index(Request $request): View
    {
        abort_if($request->user()->dc_id === null, 403, 'Akun DC belum memiliki penugasan.');

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
            ->where('dc_id', $request->user()->dc_id)
            ->with(['detail.bahan', 'detail.detailDraft', 'konfirmasi'])
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
            'items.*.bahan_id' => ['required', 'integer', 'distinct', 'exists:bahan_baku,bahan_id'],
            'items.*.qty_dikirim' => ['required', 'numeric', 'min:0.001'],
        ]);

        $user = $request->user();
        abort_if($user->dc_id === null, 403, 'Akun DC belum memiliki penugasan.');

        return DB::transaction(function () use ($data, $user) {
            $draft = DraftInstruksi::query()
                ->where('draft_id', $data['draft_id'])
                ->where('status', 'disetujui')
                ->lockForUpdate()
                ->firstOrFail();

            $detailDraftMap = $draft->detail()->get()->keyBy('bahan_id');

            foreach ($data['items'] as $item) {
                abort_unless(
                    $detailDraftMap->has($item['bahan_id']),
                    422,
                    'Bahan baku tidak termasuk dalam pesanan restock yang disetujui.'
                );
            }

            $bahanMap = BahanBaku::query()
                ->whereIn('bahan_id', array_column($data['items'], 'bahan_id'))
                ->get()
                ->keyBy('bahan_id');

            $sj = SuratJalan::query()->create([
                'cabang_id' => $draft->cabang_id,
                'dc_id' => $user->dc_id,
                'diterbitkan_oleh' => $user->user_id,
                'tanggal_kirim' => now(),
                'status' => 'disiapkan',
            ]);

            foreach ($data['items'] as $item) {
                $bahan = $bahanMap->get($item['bahan_id']);
                $isi = (float) ($bahan?->isi_per_kemasan ?? 1);
                $qtyDibutuhkan = (float) $item['qty_dikirim'] * $isi;

                $stokDc = StokDc::query()
                    ->where('dc_id', $user->dc_id)
                    ->where('bahan_id', $item['bahan_id'])
                    ->lockForUpdate()
                    ->first();

                $currentStock = (float) ($stokDc?->jumlah_stok ?? 0);
                abort_if(
                    $currentStock < $qtyDibutuhkan,
                    422,
                    "Stok {$bahan?->nama_bahan} di gudang DC tidak mencukupi untuk pengiriman."
                );

                $stokDc->jumlah_stok = $currentStock - $qtyDibutuhkan;
                $stokDc->save();

                StockMovement::query()->create([
                    'bahan_id' => $item['bahan_id'],
                    'cabang_id' => null,
                    'dc_id' => $user->dc_id,
                    'jenis_mutasi' => 'pengiriman_dc',
                    'jumlah' => $qtyDibutuhkan,
                    'referensi_id' => $sj->surat_jalan_id,
                    'tanggal' => now(),
                    'keterangan' => 'Pengiriman ' . sprintf('#SJ-%03d', $sj->surat_jalan_id),
                    'kelompok_id' => null,
                ]);

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
     * DC mengubah status disiapkan → dikirim, lalu memberi tahu IC cabang tujuan.
     */
    public function kirim(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_if($user->dc_id === null, 403, 'Akun DC belum memiliki penugasan.');

        return DB::transaction(function () use ($id, $user) {
            $sj = SuratJalan::query()
                ->where('surat_jalan_id', $id)
                ->where('dc_id', $user->dc_id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($sj->status === 'disiapkan', 422, 'Surat jalan sudah dikirim.');

            $sj->status = 'dikirim';
            $sj->tanggal_kirim = now();
            $sj->save();

            $this->notifications->shipmentDispatched($sj);

            return response()->json([
                'surat_jalan_id' => $sj->surat_jalan_id,
                'no' => sprintf('#SJ-%03d', $sj->surat_jalan_id),
                'status' => 'menunggu',
            ]);
        });
    }

    /**
     * Catatan per bahan dibaca kembali dari konfirmasi_penerimaan.kondisi
     * (format: "selisih: Nama Bahan: catatan; Bahan Lain: catatan").
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

    private function format(SuratJalan $sj): array
    {
        $notes = $this->notesFromKondisi($sj->konfirmasi?->kondisi);
        $draftId = $sj->detail->first()?->detailDraft?->draft_id;

        $items = $sj->detail->map(fn ($d) => [
            'item' => (string) $d->bahan_id,
            'sent' => (int) $d->qty_dikirim,
            'received' => $d->qty_diterima === null ? null : (int) $d->qty_diterima,
            'note' => $notes[$d->bahan?->nama_bahan ?? ''] ?? null,
        ])->values()->all();

        return [
            'id' => $sj->surat_jalan_id,
            'no' => sprintf('#SJ-%03d', $sj->surat_jalan_id),
            'date' => $sj->tanggal_kirim?->format('d M Y') ?? '-',
            'store' => (string) $sj->cabang_id,
            'order' => $draftId ? sprintf('#RS-%04d', $draftId) : '-',
            'origin' => 'DC',
            'status' => $sj->computed_status,
            'items' => $items,
        ];
    }
}
