<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\TransaksiPenjualan;
use App\Models\VarianMenu;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiwayatController extends Controller
{
    /**
     * Halaman Riwayat Transaksi kasir.
     * Mengambil data transaksi pembelian milik cabang yang login,
     * beserta pembayaran, detail item, dan kasir.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $transaksi = TransaksiPenjualan::query()
            ->where('cabang_id', $user->cabang_id)
            ->with(['kasir', 'pembayaran', 'detail.varian.menu'])
            ->orderByDesc('tanggal')
            ->get();

        $data = $transaksi->map(function ($t) {
            $items = $t->detail->map(function ($d) {
                $varian = $d->varian;
                $menu = $varian ? $varian->menu : null;

                return [
                    'menu_id' => $menu?->menu_id ?? null,
                    'menu' => $menu?->nama_menu ?? '-',
                    'varian' => $varian?->nama_varian ?? '-',
                    'qty' => (int) $d->qty,
                    'price' => (float) $d->harga_satuan,
                    'subtotal' => (float) $d->subtotal,
                ];
            })->values()->all();

            $payment = $t->pembayaran;

            return [
                'no' => $t->no_transaksi ?? sprintf('#TRX-%04d', $t->transaksi_id),
                'date' => $t->tanggal?->format('Y-m-d'),
                'time' => $t->tanggal?->format('H:i:s'),
                'cashier' => $t->kasir?->nama_lengkap ?? '-',
                'method' => $payment?->metode ?? '-',
                'cash' => $payment?->jumlah_diterima ?? null,
                'total' => (float) $t->total_bayar,
                'status' => $t->status === 'berhasil' ? 'selesai' : 'batal',
                'items' => $items,
                'usage' => $this->computeUsage($items),
                'reason' => $t->alasan_batal ?? null,
            ];
        })->values()->all();

        return view('kasir.riwayat-transaksi.index', [
            'transactions' => $data,
        ]);
    }

    /**
     * Hitung total bahan yang terpakai per transaksi (dari resep varian).
     *
     * @return array<string, array{name: string, unit: string, amount: float}>
     */
    private function computeUsage(array $items): array
    {
        $usage = [];

        foreach ($items as $item) {
            $varian = VarianMenu::query()
                ->where('nama_varian', $item['varian'])
                ->whereHas('menu', fn ($q) => $q->where('nama_menu', $item['menu']))
                ->with('resep.bahan')
                ->first();

            if ($varian === null) {
                continue;
            }

            foreach ($varian->resep as $resep) {
                $key = $resep->bahan_id;

                if (! isset($usage[$key])) {
                    $usage[$key] = [
                        'name' => $resep->bahan->nama_bahan,
                        'unit' => $resep->bahan->satuan,
                        'amount' => 0.0,
                    ];
                }

                $usage[$key]['amount'] += (float) $resep->jumlah_bahan * $item['qty'];
            }
        }

        return $usage;
    }
}
