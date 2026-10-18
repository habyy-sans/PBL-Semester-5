<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\DetailTransaksi;
use App\Models\MenuProduk;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Models\StokCabang;
use App\Models\TransaksiPenjualan;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
    ) {}

    /**
     * Halaman Kasir (POS).
     *
     * Data dikirim dengan bentuk yang sama persis dengan config dummy
     * lama (items & menus) supaya logika JS di view tidak berubah.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('kasir.pos.index', [
            'items' => (object) $this->catalog->items($user->cabang_id),
            'menus' => (object) $this->catalog->menus(),
        ]);
    }

    /**
     * Proses pembayaran: simpan transaksi + potong stok + catat mutasi
     * dalam SATU database transaction.
     *
     * Payload JS: { items: [{ menu: menuId, qty }], method, cash }
     * Respons:    { no, total, change, cuts: [...] }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu' => ['required', 'integer', 'exists:menu_produk,menu_id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
            'method' => ['required', 'in:tunai,qris,debit'],
            'cash' => ['nullable', 'integer', 'min:0'],
        ]);

        $user = $request->user();

        try {
            return $this->processPayment($data, $user);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    private function processPayment(array $data, $user): JsonResponse
    {
        return DB::transaction(function () use ($data, $user) {
            $stokMap = StokCabang::query()
                ->where('cabang_id', $user->cabang_id)
                ->whereIn('bahan_id', $this->neededBahanIds($data['items']))
                ->orderBy('bahan_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('bahan_id');

            $usage = [];
            $usageMeta = [];
            $total = 0;

            foreach ($data['items'] as $line) {
                $varian = MenuProduk::query()
                    ->whereKey($line['menu'])
                    ->with(['varian.resep.bahan'])
                    ->firstOrFail()
                    ->varian
                    ->first();

                if ($varian === null) {
                    throw ValidationException::withMessages([
                        'items' => 'Menu belum punya varian/resep.',
                    ]);
                }

                $total += (float) $varian->menu->harga_jual * $line['qty'];

                foreach ($varian->resep as $resep) {
                    $usage[$resep->bahan_id] = ($usage[$resep->bahan_id] ?? 0)
                        + (float) $resep->jumlah_bahan * $line['qty'];
                    $usageMeta[$resep->bahan_id] = $resep->bahan;
                }
            }

            // Validasi stok (server-side, sumber kebenaran).
            foreach ($usage as $bahanId => $amount) {
                $stok = $stokMap->get($bahanId);

                if ($stok === null || (float) $stok->jumlah_stok < $amount) {
                    $nama = $usageMeta[$bahanId]->nama_bahan;
                    $sisa = $stok !== null ? (float) $stok->jumlah_stok : 0.0;

                    throw ValidationException::withMessages([
                        'items' => "Stok $nama tidak cukup (sisa $sisa, dibutuhkan $amount).",
                    ]);
                }
            }

            if ($data['method'] === 'tunai' && (int) ($data['cash'] ?? 0) < $total) {
                throw ValidationException::withMessages([
                    'cash' => 'Uang diterima kurang dari total belanja.',
                ]);
            }

            // 1) Transaksi penjualan.
            $transaksi = TransaksiPenjualan::query()->create([
                'cabang_id' => $user->cabang_id,
                'kasir_id' => $user->user_id,
                'tanggal' => now(),
                'total_bayar' => $total,
                'status' => 'berhasil',
            ]);

            $transaksi->forceFill([
                'no_transaksi' => sprintf('#TRX-%04d', $transaksi->transaksi_id),
            ])->saveOrFail();

            // 2) Detail item (snapshot harga saat transaksi).
            foreach ($data['items'] as $line) {
                $varian = MenuProduk::query()
                    ->whereKey($line['menu'])
                    ->with('varian')
                    ->firstOrFail()
                    ->varian
                    ->first();

                $harga = (float) $varian->menu->harga_jual;

                DetailTransaksi::query()->create([
                    'transaksi_id' => $transaksi->transaksi_id,
                    'varian_id' => $varian->varian_id,
                    'qty' => $line['qty'],
                    'harga_satuan' => $harga,
                    'subtotal' => $harga * $line['qty'],
                ]);
            }

            // 3) Pembayaran.
            $cash = $data['method'] === 'tunai' ? (int) $data['cash'] : null;

            Payment::query()->create([
                'transaksi_id' => $transaksi->transaksi_id,
                'metode' => $data['method'],
                'status' => 'berhasil',
                'paid_at' => now(),
                'jumlah_diterima' => $cash,
                'kembalian' => $cash !== null ? $cash - $total : null,
            ]);

            // 4) Potong stok cabang + catat mutasi per bahan.
            $cuts = [];

            foreach ($usage as $bahanId => $amount) {
                $stok = $stokMap->get($bahanId);

                $stok->jumlah_stok = (float) $stok->jumlah_stok - $amount;
                $stok->save();

                StockMovement::query()->create([
                    'bahan_id' => $bahanId,
                    'cabang_id' => $user->cabang_id,
                    'jenis_mutasi' => 'penjualan',
                    'jumlah' => $amount,
                    'referensi_id' => $transaksi->transaksi_id,
                    'tanggal' => $transaksi->tanggal,
                    'keterangan' => 'Penjualan · '.$transaksi->no_transaksi,
                ]);

                $cuts[] = [
                    'name' => $usageMeta[$bahanId]->nama_bahan,
                    'amount' => $amount,
                    'unit' => $usageMeta[$bahanId]->satuan,
                ];
            }

            return response()->json([
                'no' => $transaksi->no_transaksi,
                'total' => $total,
                'change' => $cash !== null ? $cash - $total : 0,
                'cuts' => $cuts,
            ]);
        });
    }

    /**
     * @param  array<int, array<string, int>>  $items
     * @return array<int, int>
     */
    private function neededBahanIds(array $items): array
    {
        $ids = [];

        foreach ($items as $line) {
            $varian = MenuProduk::query()
                ->whereKey($line['menu'])
                ->with('varian.resep')
                ->first()
                ?->varian
                ->first();

            if ($varian !== null) {
                foreach ($varian->resep as $resep) {
                    $ids[$resep->bahan_id] = true;
                }
            }
        }

        return array_keys($ids);
    }
}
