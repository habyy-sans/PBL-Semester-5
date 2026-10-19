<?php

namespace App\Http\Controllers\Ic;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use App\Models\DetailSuratJalan;
use App\Models\KonfirmasiPenerimaan;
use App\Models\StockMovement;
use App\Models\StokCabang;
use App\Models\SuratJalan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReceivingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $items = BahanBaku::query()
            ->with(['stokCabang' => fn ($q) => $q->where('cabang_id', $user->cabang_id)])
            ->orderBy('bahan_id')->get()
            ->mapWithKeys(function ($b) {
                $stok = $b->stokCabang->first();

                return [(string) $b->bahan_id => [
                    'stock' => (float) ($stok->jumlah_stok ?? 0),
                    'name' => $b->nama_bahan,
                    'category' => $b->kategori,
                    'pack' => $b->nama_kemasan,
                    'size' => (float) $b->isi_per_kemasan,
                    'unit' => $b->satuan,
                    'price' => (float) $b->harga_beli,
                ]];
            })->all();

        $shipments = SuratJalan::query()
            ->where('cabang_id', $user->cabang_id)
            ->with(['detail.bahan', 'konfirmasi'])
            ->orderByDesc('surat_jalan_id')
            ->get()
            ->map(fn ($sj) => $this->format($sj))
            ->values()->all();

        return view('ic.PenerimaanBarang.index', [
            'items' => $items,
            'shipments' => $shipments,
        ]);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.bahan_id' => ['required', 'integer', 'exists:bahan_baku,bahan_id'],
            'items.*.qty_diterima' => ['required', 'integer', 'min:0'],
            'items.*.catatan' => ['nullable', 'string', 'max:255'],
            'kondisi' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($data, $user, $id) {
            $sj = SuratJalan::query()
                ->where('surat_jalan_id', $id)
                ->where('cabang_id', $user->cabang_id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($sj->status !== 'dikirim', 422, 'Hanya surat jalan berstatus dikirim yang bisa dikonfirmasi.');

            abort_if($sj->konfirmasi()->exists(), 422, 'Surat jalan sudah dikonfirmasi.');

            $detailMap = DetailSuratJalan::query()
                ->where('surat_jalan_id', $sj->surat_jalan_id)
                ->lockForUpdate()
                ->get()
                ->keyBy('bahan_id');

            $bahanIds = array_map(fn (array $item) => (int) $item['bahan_id'], $data['items']);

            abort_if(
                count($bahanIds) !== count(array_unique($bahanIds)),
                422,
                'Terdapat detail ganda: setiap bahan hanya boleh dikonfirmasi tepat satu kali.'
            );

            abort_if(
                array_diff($bahanIds, $detailMap->keys()->all()) !== []
                || array_diff($detailMap->keys()->all(), $bahanIds) !== [],
                422,
                'Konfirmasi penerimaan harus mencakup semua detail surat jalan.'
            );

            $stokMap = StokCabang::query()
                ->where('cabang_id', $sj->cabang_id)
                ->whereIn('bahan_id', $bahanIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('bahan_id');

            $bahanMap = BahanBaku::query()->whereIn('bahan_id', $bahanIds)->get()->keyBy('bahan_id');

            $notes = [];

            foreach ($data['items'] as $item) {
                $detail = $detailMap->get($item['bahan_id']);
                abort_if($detail === null, 422, 'Bahan tidak ada di surat jalan.');

                $qtyDiterima = (int) $item['qty_diterima'];

                abort_if(
                    (float) $qtyDiterima > (float) $detail->qty_dikirim,
                    422,
                    'Jumlah diterima melebihi jumlah dikirim.'
                );

                $detail->qty_diterima = $qtyDiterima;
                $detail->save();

                $bahan = $bahanMap->get($item['bahan_id']);
                $isi = (float) ($bahan?->isi_per_kemasan ?? 1);
                $qtyBase = $qtyDiterima * $isi;

                if ($qtyBase > 0) {
                    $stok = $stokMap->get($item['bahan_id']);

                    if ($stok === null) {
                        $stok = StokCabang::query()->create([
                            'cabang_id' => $sj->cabang_id,
                            'bahan_id' => $item['bahan_id'],
                            'jumlah_stok' => 0,
                            'stok_minimum' => 0,
                        ]);
                        $stokMap->put($item['bahan_id'], $stok);
                    }

                    $stok->jumlah_stok = (float) $stok->jumlah_stok + $qtyBase;
                    $stok->save();

                    StockMovement::query()->create([
                        'bahan_id' => $item['bahan_id'],
                        'cabang_id' => $sj->cabang_id,
                        'dc_id' => null,
                        'jenis_mutasi' => 'penerimaan_cabang',
                        'jumlah' => $qtyBase,
                        'referensi_id' => $sj->surat_jalan_id,
                        'tanggal' => now(),
                        'keterangan' => 'Penerimaan ' . sprintf('#SJ-%03d', $sj->surat_jalan_id),
                        'kelompok_id' => null,
                    ]);
                }

                if (! empty($item['catatan'])) {
                    $notes[] = $bahan?->nama_bahan . ': ' . $item['catatan'];
                }
            }

            KonfirmasiPenerimaan::query()->create([
                'surat_jalan_id' => $sj->surat_jalan_id,
                'kepala_shift_id' => $user->user_id,
                'tanggal_konfirmasi' => now(),
                'kondisi' => $data['kondisi'] ?? ($notes === [] ? 'baik' : 'selisih: ' . implode('; ', $notes)),
            ]);

            $sj->status = 'selesai';
            $sj->save();

            return response()->json([
                'surat_jalan_id' => $sj->surat_jalan_id,
                'no' => sprintf('#SJ-%03d', $sj->surat_jalan_id),
                'status' => 'diterima',
            ]);
        });
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
            'origin' => 'DC',
            'status' => $sj->computed_status,
            'items' => $items,
        ];
    }
}
