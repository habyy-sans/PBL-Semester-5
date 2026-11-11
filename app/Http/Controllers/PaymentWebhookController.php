<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\TransaksiPenjualan;
use App\Services\MidtransService;
use App\Services\OrderSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly OrderSettlementService $settlement,
    ) {}

    /**
     * Webhook notifikasi pembayaran resmi dari Midtrans.
     * Midtrans mengirimkan HTTP POST ke endpoint ini saat status transaksi berubah.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Midtrans Webhook Received', $payload);

        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signatureKey = (string) ($payload['signature_key'] ?? '');
        $transactionStatus = (string) ($payload['transaction_status'] ?? '');
        $fraudStatus = (string) ($payload['fraud_status'] ?? 'accept');
        $transactionId = (string) ($payload['transaction_id'] ?? '');

        // 1. Verifikasi tanda tangan keamanan (Signature Key)
        if (! $this->midtrans->verifySignature($orderId, $statusCode, $grossAmount, $signatureKey)) {
            Log::warning('Midtrans Webhook: Invalid signature', [
                'order_id' => $orderId,
                'signature_key' => $signatureKey,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid signature key',
            ], 403);
        }

        // 2. Cari transaksi terkait berdasarkan gateway_order_id atau no_transaksi
        $payment = Payment::query()
            ->where('gateway_order_id', $orderId)
            ->first();

        $transaksi = $payment?->transaksi
            ?? TransaksiPenjualan::query()->where('no_transaksi', $orderId)->first();

        if (! $transaksi) {
            Log::error('Midtrans Webhook: Transaksi tidak ditemukan', ['order_id' => $orderId]);

            return response()->json([
                'status' => 'error',
                'message' => 'Order not found',
            ], 404);
        }

        // 3. Tangani perubahan status pembayaran
        if ($transactionStatus === 'settlement' || ($transactionStatus === 'capture' && $fraudStatus === 'accept')) {
            // Pembayaran berhasil!
            $result = $this->settlement->settle($transaksi, $transactionId);

            Log::info('Midtrans Webhook: Pembayaran sukses & diselesaikan', [
                'transaksi_id' => $transaksi->transaksi_id,
                'no_transaksi' => $transaksi->no_transaksi,
                'already_settled' => $result['already_settled'],
            ]);
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'], true)) {
            // Pembayaran gagal atau kadaluarsa (hanya batalkan jika belum berhasil)
            if ($transaksi->status !== 'berhasil') {
                $transaksi->update([
                    'status' => 'batal',
                    'alasan_batal' => 'Midtrans: status ' . $transactionStatus,
                ]);

                if ($payment) {
                    $payment->update(['status' => 'batal']);
                }

                Log::info('Midtrans Webhook: Transaksi dibatalkan', ['order_id' => $orderId, 'status' => $transactionStatus]);
            }
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Notification processed successfully',
        ]);
    }

    /**
     * Endpoint simulasi cepat untuk pengujian kasir langsung (Sandbox / Local Dev).
     * Kasir dapat mengklik "Simulasi Bayar" untuk menguji flow sukses tanpa membuka simulator manual.
     */
    public function simulate(Request $request, int $transaksiId): JsonResponse
    {
        abort_if(
            config('services.midtrans.is_production', false),
            403,
            'Simulasi pembayaran tidak diizinkan pada mode produksi.'
        );

        $user = $request->user();

        $transaksi = TransaksiPenjualan::query()
            ->with('pembayaran')
            ->when($user?->cabang_id, fn ($q, $cabangId) => $q->where('cabang_id', $cabangId))
            ->where('status', 'menunggu_pembayaran')
            ->findOrFail($transaksiId);

        $existingGatewayTxId = (string) ($transaksi->pembayaran?->gateway_transaction_id ?? '');

        if ($existingGatewayTxId !== '') {
            $this->midtrans->simulateSandboxQris($existingGatewayTxId);
        }

        $result = $this->settlement->settle(
            $transaksi,
            $existingGatewayTxId !== '' ? null : 'sim-qris-' . time()
        );

        return response()->json([
            'status' => 'berhasil',
            'no' => $transaksi->no_transaksi,
            'total' => (float) $transaksi->total_bayar,
            'cuts' => $result['cuts'],
        ]);
    }
}
