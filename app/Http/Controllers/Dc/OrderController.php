<?php

namespace App\Http\Controllers\Dc;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\DetailSuratJalan;
use App\Models\DraftInstruksi;
use App\Models\StokDc;
use App\Models\SuratJalan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Menampilkan pesanan DC beserta progres pengiriman
     * per detail pesanan.
     */
    public function index(Request $request): View
    {
        $dcId = $request->user()->dc_id;

        abort_if(
            $dcId === null,
            403,
            'Akun DC belum memiliki penugasan.'
        );

        $items = BahanBaku::query()
            ->orderBy('bahan_id')
            ->get()
            ->mapWithKeys(fn ($b) => [(string) $b->bahan_id => [
                'name' => $b->nama_bahan,
                'category' => $b->kategori,
                'pack' => $b->nama_kemasan,
                'size' => (float) ($b->isi_per_kemasan ?? 1),
                'unit' => $b->satuan,
                'min' => 0,
                'price' => (float) $b->harga_beli,
            ]])
            ->all();

        $storeList = Cabang::query()
            ->orderBy('cabang_id')
            ->get()
            ->map(fn ($c) => [
                'id' => (string) $c->cabang_id,
                'name' => $c->nama_cabang,
            ])
            ->all();

        // Stok DC menggunakan satuan dasar.
        $dcStock = StokDc::query()
            ->where('dc_id', $dcId)
            ->pluck('jumlah_stok', 'bahan_id')
            ->map(fn ($qty) => (float) $qty)
            ->all();

        // Menghitung total barang yang sudah dikirim
        // berdasarkan setiap detail pesanan.
        // Surat Jalan yang dibatalkan tidak ikut dihitung.
        $sentByDetail = DetailSuratJalan::query()
            ->selectRaw(
                'detail_surat_jalan.detail_draft_id,
                SUM(detail_surat_jalan.qty_dikirim) AS total_dikirim'
            )
            ->join(
                'surat_jalan',
                'surat_jalan.surat_jalan_id',
                '=',
                'detail_surat_jalan.surat_jalan_id'
            )
            ->whereNotNull('detail_surat_jalan.detail_draft_id')
            ->where('surat_jalan.dc_id', $dcId)
            ->where('surat_jalan.status', '!=', 'dibatalkan')
            ->groupBy('detail_surat_jalan.detail_draft_id')
            ->pluck('total_dikirim', 'detail_draft_id')
            ->map(fn ($qty) => (float) $qty)
            ->all();

        // Riwayat Surat Jalan hanya dari DC yang sedang login.
        $sjByDraft = SuratJalan::query()
            ->where('dc_id', $dcId)
            ->with('detail.detailDraft')
            ->orderBy('surat_jalan_id')
            ->get()
            ->groupBy(function ($sj) {
                $firstDetail = $sj->detail->first(
                    fn ($detail) => $detail->detail_draft_id !== null
                );

                return $firstDetail?->detailDraft?->draft_id;
            })
            ->filter(fn ($group, $draftId) => $draftId !== null);

        $orders = DraftInstruksi::query()
            ->whereIn('status', [
                'disetujui',
                'diproses',
                'selesai',
            ])
            ->with(['detail.bahan'])
            ->orderByDesc('draft_id')
            ->get()
            ->map(function ($draft) use (
                $sjByDraft,
                $sentByDetail,
                $dcStock
            ) {
                $lines = $draft->detail->map(
                    function ($detail) use ($sentByDetail, $dcStock) {
                        $requested = (float) $detail->qty_diminta;

                        $sent = (float) (
                            $sentByDetail[$detail->detail_draft_id] ?? 0
                        );

                        $remaining = max(0, $requested - $sent);

                        return [
                            'detail_draft_id' => (int) $detail->detail_draft_id,
                            'item' => (string) $detail->bahan_id,
                            'qty_requested_base' => $requested,
                            'qty_sent_base' => $sent,
                            'qty_remaining_base' => $remaining,
                            'qty_to_ship_base' => min(
                                $remaining,
                                (float) ($dcStock[$detail->bahan_id] ?? 0)
                            ),
                            'stock_base' => (float) (
                                $dcStock[$detail->bahan_id] ?? 0
                            ),
                        ];
                    }
                )->values()->all();

                $sjList = $sjByDraft->get(
                    $draft->draft_id,
                    collect()
                );

                $latestSj = $sjList->last();

                $status = match ($draft->status) {
                    'disetujui' => 'baru',
                    'diproses' => 'diproses',
                    'selesai' => 'selesai',
                    default => 'baru',
                };

                return [
                    'id' => (int) $draft->draft_id,
                    'no' => sprintf('#RS-%04d', $draft->draft_id),
                    'date' => (
                        $draft->tanggal_persetujuan ?? $draft->dibuat_pada
                    )?->format('d M Y') ?? '-',
                    'store' => (string) $draft->cabang_id,
                    'status' => $status,
                    'shipment' => $latestSj
                        ? sprintf('#SJ-%03d', $latestSj->surat_jalan_id)
                        : null,
                    'lines' => $lines,
                ];
            })
            ->values()
            ->all();

        return view('dc.pesanan.index', [
            'items' => $items,
            'storeList' => $storeList,
            'orders' => $orders,
            'dcStock' => $dcStock,
        ]);
    }
}
