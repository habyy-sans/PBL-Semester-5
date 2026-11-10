<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\DetailTransaksi;
use App\Models\MenuProduk;
use App\Models\Payment;
use App\Models\StokCabang;
use App\Models\TransaksiPenjualan;
use App\Services\CatalogService;
use App\Services\MidtransService;
use App\Services\NotificationService;
use App\Services\OrderSettlementService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly NotificationService $notifications,
        private readonly MidtransService $midtrans,
        private readonly OrderSettlementService $settlement,
    ) {}

    /**
     * Halaman Kasir (POS).
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $cabangId = $user->cabang_id;

        $todayOrdersCount = TransaksiPenjualan::query()
            ->where('cabang_id', $cabangId)
            ->whereDate('tanggal', today())
            ->count();

        $nextTrxId = (TransaksiPenjualan::query()->max('transaksi_id') ?? 0) + 1;
        $activeTrxNo = sprintf('#TRX-%04d', $nextTrxId);

        return view('kasir.pos.index', [
            'items' => (object) $this->catalog->items($cabangId),
            'menus' => (object) $this->catalog->menus(),
            'todayOrdersCount' => $todayOrdersCount,
            'activeTrxNo' => $activeTrxNo,
            'branchName' => $user->cabang?->nama_cabang ?? 'KopSync Gerai',
            'isMidtransConfigured' => $this->midtrans->isConfigured(),
        ]);
    }

    /**
     * Proses pembayaran POS:
     * - Tunai / Debit: langsung selesai (settle)
     * - QRIS: dibuatkan tagihan Direct QRIS Midtrans (pending) dan mengembalikan QR Code
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
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memproses transaksi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cek status pembayaran untuk polling frontend kasir saat menunggu QRIS.
     */
    public function status(int $id): JsonResponse
    {
        $transaksi = TransaksiPenjualan::query()
            ->with('pembayaran')
            ->findOrFail($id);

        // Jika transaksi masih menunggu pembayaran dan Midtrans sudah terhubung,
        // cek langsung ke Midtrans API sebagai proteksi jika webhook tertunda
        if ($transaksi->status === 'menunggu_pembayaran' && $transaksi->pembayaran?->gateway_order_id) {
            $midtransStatus = $this->midtrans->checkStatus($transaksi->pembayaran->gateway_order_id);

            if ($midtransStatus) {
                $status = $midtransStatus['transaction_status'] ?? '';
                $fraud = $midtransStatus['fraud_status'] ?? 'accept';

                if ($status === 'settlement' || ($status === 'capture' && $fraud === 'accept')) {
                    $this->settlement->settle($transaksi, $midtransStatus['transaction_id'] ?? null);
                    $transaksi->refresh();
                } elseif (in_array($status, ['cancel', 'deny', 'expire'], true)) {
                    $transaksi->update(['status' => 'batal']);
                    $transaksi->pembayaran?->update(['status' => 'batal']);
                }
            }
        }

        return response()->json([
            'transaksi_id' => $transaksi->transaksi_id,
            'no' => $transaksi->no_transaksi,
            'status' => $transaksi->status,
            'total' => (float) $transaksi->total_bayar,
        ]);
    }

    /**
     * Batalkan transaksi pending (misal pelanggan batal bayar QRIS).
     */
    public function cancel(int $id): JsonResponse
    {
        $transaksi = TransaksiPenjualan::query()->findOrFail($id);

        if ($transaksi->status === 'menunggu_pembayaran') {
            $transaksi->update([
                'status' => 'batal',
                'alasan_batal' => 'Dibatalkan oleh kasir di kasir POS',
            ]);

            $transaksi->pembayaran?->update(['status' => 'batal']);
        }

        return response()->json([
            'status' => 'batal',
            'no' => $transaksi->no_transaksi,
        ]);
    }

    private function processPayment(array $data, $user): JsonResponse
    {
        return DB::transaction(function () use ($data, $user) {
            // Validasi ketersediaan stok bahan baku
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

            // Validasi kecukupan stok
            foreach ($usage as $bahanId => $amount) {
                $stok = $stokMap->get($bahanId);

                if ($stok === null || (float) $stok->jumlah_stok < $amount) {
                    $nama = $usageMeta[$bahanId]->nama_bahan ?? "Bahan #{$bahanId}";
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

            // 1. Catat Transaksi Penjualan
            $isQris = $data['method'] === 'qris';
            $initialStatus = $isQris ? 'menunggu_pembayaran' : 'berhasil';

            $transaksi = TransaksiPenjualan::query()->create([
                'cabang_id' => $user->cabang_id,
                'kasir_id' => $user->user_id,
                'tanggal' => now(),
                'total_bayar' => $total,
                'status' => $initialStatus,
            ]);

            $transaksi->forceFill([
                'no_transaksi' => sprintf('#TRX-%04d', $transaksi->transaksi_id),
            ])->saveOrFail();

            // 2. Simpan Detail Item
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

            // 3. Penanganan Pembayaran QRIS vs Tunai/Debit
            if ($isQris) {
                $gatewayOrderId = sprintf('TRX-%04d-%d', $transaksi->transaksi_id, time());

                // Minta tagihan Direct QRIS ke Midtrans
                $qrisResponse = $this->midtrans->createQrisTransaction($gatewayOrderId, (int) $total);

                Payment::query()->create([
                    'transaksi_id' => $transaksi->transaksi_id,
                    'metode' => 'qris',
                    'gateway_order_id' => $gatewayOrderId,
                    'gateway_transaction_id' => $qrisResponse['transaction_id'] ?? null,
                    'status' => 'pending',
                ]);

                return response()->json([
                    'type' => 'qris',
                    'status' => 'pending',
                    'transaksi_id' => $transaksi->transaksi_id,
                    'no' => $transaksi->no_transaksi,
                    'order_id' => $gatewayOrderId,
                    'total' => $total,
                    'qr_url' => $qrisResponse['qr_url'],
                    'qr_string' => $qrisResponse['qr_string'],
                    'is_mock' => $qrisResponse['is_mock'] ?? false,
                ]);
            }

            // Tunai / Debit: Langsung diproses dan diselesaikan
            $cash = $data['method'] === 'tunai' ? (int) $data['cash'] : null;

            Payment::query()->create([
                'transaksi_id' => $transaksi->transaksi_id,
                'metode' => $data['method'],
                'status' => 'berhasil',
                'paid_at' => now(),
                'jumlah_diterima' => $cash,
                'kembalian' => $cash !== null ? $cash - $total : null,
            ]);

            // Potong stok dan notifikasi
            $settleResult = $this->settlement->settle($transaksi);

            return response()->json([
                'type' => 'direct',
                'no' => $transaksi->no_transaksi,
                'total' => $total,
                'change' => $cash !== null ? $cash - $total : 0,
                'cuts' => $settleResult['cuts'],
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
