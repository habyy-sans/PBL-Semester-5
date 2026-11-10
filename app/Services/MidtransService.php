<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MidtransService
{
    private readonly ?string $serverKey;
    private readonly ?string $clientKey;
    private readonly bool $isProduction;

    public function __construct()
    {
        $this->serverKey = config('services.midtrans.server_key');
        $this->clientKey = config('services.midtrans.client_key');
        $this->isProduction = (bool) config('services.midtrans.is_production', false);
    }

    /**
     * Cek apakah API Key Midtrans sudah dikonfigurasi.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->serverKey);
    }

    /**
     * URL dasar Midtrans API berdasarkan environment.
     */
    public function getBaseUrl(): string
    {
        return $this->isProduction
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    /**
     * Buat transaksi Direct QRIS (Core API).
     *
     * @return array{
     *     order_id: string,
     *     transaction_id: string,
     *     qr_url: string,
     *     qr_string: string,
     *     status: string,
     *     is_mock: bool
     * }
     */
    public function createQrisTransaction(string $orderId, int $grossAmount): array
    {
        if (! $this->isConfigured()) {
            // Mode Mock / Fallback cerdas jika user belum memasukkan Server Key
            $mockQrString = '00020101021226590016ID.CO.GOPAY.WWW011893600914' . time();
            $mockQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($mockQrString);

            return [
                'order_id' => $orderId,
                'transaction_id' => 'mock-' . (string) Str::uuid(),
                'qr_url' => $mockQrUrl,
                'qr_string' => $mockQrString,
                'status' => 'pending',
                'is_mock' => true,
            ];
        }

        $endpoint = $this->getBaseUrl() . '/charge';

        $payload = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'qris' => [
                'acquirer' => 'gopay',
            ],
        ];

        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, $payload);

            if (! $response->successful()) {
                Log::error('Midtrans QRIS Charge failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new Exception('Gagal membuat tagihan QRIS ke Midtrans: ' . ($response->json('status_message') ?? $response->body()));
            }

            $body = $response->json();

            // Midtrans memberikan actions array yang berisi URL gambar QR
            $qrUrl = null;
            if (isset($body['actions']) && is_array($body['actions'])) {
                foreach ($body['actions'] as $action) {
                    if (($action['name'] ?? '') === 'generate-qr-code') {
                        $qrUrl = $action['url'] ?? null;
                        break;
                    }
                }
            }

            return [
                'order_id' => $body['order_id'] ?? $orderId,
                'transaction_id' => $body['transaction_id'] ?? '',
                'qr_url' => $qrUrl ?? '',
                'qr_string' => $body['qr_string'] ?? '',
                'status' => $body['transaction_status'] ?? 'pending',
                'is_mock' => false,
            ];
        } catch (Exception $e) {
            Log::error('Midtrans Exception: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verifikasi keaslian webhook signature key dari Midtrans.
     * Formula resmi: SHA512(order_id + status_code + gross_amount + server_key)
     */
    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signatureKey): bool
    {
        if (! $this->isConfigured()) {
            return true;
        }

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);

        return hash_equals($expected, $signatureKey);
    }

    /**
     * Cek status transaksi langsung ke Midtrans API.
     */
    public function checkStatus(string $orderId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $endpoint = $this->getBaseUrl() . '/' . $orderId . '/status';

        $response = Http::withBasicAuth($this->serverKey, '')
            ->withHeaders(['Accept' => 'application/json'])
            ->get($endpoint);

        return $response->successful() ? $response->json() : null;
    }
}
