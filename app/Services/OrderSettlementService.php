<?php

namespace App\Services;

use App\Models\DetailTransaksi;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Models\StokCabang;
use App\Models\TransaksiPenjualan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderSettlementService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Selesaikan pembayaran transaksi: update status, potong stok, catat mutasi, dan kirim notifikasi.
     * Bersifat idempoten (aman dipanggil berulang kali).
     *
     * @return array{
     *     cuts: array<int, array{name: string, amount: float, unit: string}>,
     *     already_settled: bool
     * }
     */
    public function settle(TransaksiPenjualan $transaksi, ?string $gatewayTransactionId = null): array
    {
        return DB::transaction(function () use ($transaksi, $gatewayTransactionId) {
            $transaksi = TransaksiPenjualan::query()
                ->whereKey($transaksi->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaksi->status === 'berhasil') {
                return [
                    'cuts' => [],
                    'already_settled' => true,
                ];
            }

            abort_if($transaksi->status === 'batal', 422, 'Transaksi yang sudah dibatalkan tidak dapat diselesaikan.');

            // Muat relasi detail transaksi beserta varian, resep, dan bahannya
            $transaksi->loadMissing(['detail.varian.resep.bahan', 'cabang', 'pembayaran']);

            $usage = [];
            $usageMeta = [];

            foreach ($transaksi->detail as $detail) {
                $varian = $detail->varian;
                if (! $varian) {
                    continue;
                }

                foreach ($varian->resep as $resep) {
                    $bahanId = $resep->bahan_id;
                    $amount = (float) $resep->jumlah_bahan * (int) $detail->qty;

                    $usage[$bahanId] = ($usage[$bahanId] ?? 0.0) + $amount;
                    $usageMeta[$bahanId] = $resep->bahan;
                }
            }

            // Kunci baris stok cabang terkait untuk pemotongan
            $stokMap = StokCabang::query()
                ->where('cabang_id', $transaksi->cabang_id)
                ->whereIn('bahan_id', array_keys($usage))
                ->orderBy('bahan_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('bahan_id');

            // 1. Update status transaksi
            $transaksi->status = 'berhasil';
            $transaksi->save();

            // 2. Update status payment
            $payment = $transaksi->pembayaran;
            if ($payment) {
                $payment->status = 'berhasil';
                $payment->paid_at = now();
                if ($gatewayTransactionId) {
                    $payment->gateway_transaction_id = $gatewayTransactionId;
                }
                $payment->save();
            }

            // 3. Potong stok dan catat riwayat mutasi
            $cuts = [];

            foreach ($usage as $bahanId => $amount) {
                $stok = $stokMap->get($bahanId);
                if (! $stok) {
                    continue;
                }

                $wasLow = (float) $stok->jumlah_stok < (float) $stok->stok_minimum;

                $stok->jumlah_stok = (float) $stok->jumlah_stok - $amount;
                $stok->save();

                StockMovement::query()->create([
                    'bahan_id' => $bahanId,
                    'cabang_id' => $transaksi->cabang_id,
                    'jenis_mutasi' => 'penjualan',
                    'jumlah' => $amount,
                    'referensi_id' => $transaksi->transaksi_id,
                    'tanggal' => $transaksi->tanggal ?? now(),
                    'keterangan' => 'Penjualan · ' . $transaksi->no_transaksi,
                ]);

                // Notifikasi batas stok minimum
                $this->notifications->lowStock($stok, $wasLow, 'trx:' . $transaksi->transaksi_id);

                $bahan = $usageMeta[$bahanId] ?? null;
                $cuts[] = [
                    'name' => $bahan?->nama_bahan ?? "Bahan #{$bahanId}",
                    'amount' => $amount,
                    'unit' => $bahan?->satuan ?? 'unit',
                ];
            }

            return [
                'cuts' => $cuts,
                'already_settled' => false,
            ];
        });
    }
}
