<?php

namespace App\Http\Controllers\Dc;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\DraftInstruksi;
use App\Models\SuratJalan;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Halaman Pesanan Masuk (DC) — draft yang disetujui manager
     * dari semua cabang + Surat Jalan terkait, format sama dengan demo lama.
     */
    public function index(): View
    {
        $items = BahanBaku::query()->orderBy('bahan_id')->get()
            ->mapWithKeys(fn ($b) => [(string) $b->bahan_id => [
                'name' => $b->nama_bahan,
                'category' => $b->kategori,
                'pack' => $b->nama_kemasan,
                'size' => (float) ($b->isi_per_kemasan ?? 1),
                'unit' => $b->satuan,
                'min' => 0,
                'price' => (float) $b->harga_beli,
            ]])->all();

        $storeList = Cabang::query()->orderBy('cabang_id')->get()
            ->map(fn ($c) => ['id' => (string) $c->cabang_id, 'name' => $c->nama_cabang])
            ->all();

        $sjByDraft = SuratJalan::query()
            ->with('detail.detailDraft')
            ->orderBy('surat_jalan_id')
            ->get()
            ->groupBy(function ($sj) {
                $first = $sj->detail->firstWhere('detail_draft_id', '!==', null);

                return $first?->detailDraft?->draft_id;
            })
            ->filter(fn ($group, $key) => $key !== '' && $key !== null);

        $orders = DraftInstruksi::query()
            ->whereIn('status', ['disetujui', 'diproses', 'selesai'])
            ->with(['detail.bahan', 'detail.suratJalanDetail'])
            ->orderByDesc('draft_id')
            ->get()
            ->map(function ($d) use ($sjByDraft) {
                $lines = $d->detail->map(fn ($dd) => [
                    'item' => (string) $dd->bahan_id,
                    'qty' => $this->toPacks((float) $dd->qty_diminta, (float) ($dd->bahan?->isi_per_kemasan ?? 1)),
                ])->values()->all();

                $sjList = $sjByDraft->get($d->draft_id, collect());
                $sjNo = $sjList->isNotEmpty()
                    ? sprintf('#SJ-%03d', $sjList->last()->surat_jalan_id)
                    : null;

                $received = $sjList->isNotEmpty() && $sjList->every(fn ($sj) => $sj->status === 'selesai');

                return [
                    'id' => $d->draft_id,
                    'no' => sprintf('#RS-%04d', $d->draft_id),
                    'date' => $d->dibuat_pada?->format('d M Y') ?? '-',
                    'store' => (string) $d->cabang_id,
                    'status' => $received || $d->status === 'selesai' ? 'dikirim' : ($sjNo ? 'dikirim' : 'baru'),
                    'shipment' => $sjNo,
                    'lines' => $lines,
                ];
            })->values()->all();

        return view('dc.pesanan.index', [
            'items' => $items,
            'storeList' => $storeList,
            'orders' => $orders,
        ]);
    }

    private function toPacks(float $qtyBase, float $isi): int
    {
        if ($isi <= 0) {
            return (int) $qtyBase;
        }

        return (int) ceil($qtyBase / $isi);
    }
}
