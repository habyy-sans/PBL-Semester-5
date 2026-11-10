<?php

namespace App\Http\Controllers\Manager;
use App\Services\NotificationService;
use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\DetailDraftInstruksi;
use App\Models\DraftInstruksi;
use App\Models\StokCabang;
use App\Models\BahanBaku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestockController extends Controller
{
public function __construct(
    private readonly NotificationService $notifications,
) {}
    
public function generateAutomatic(Request $request): JsonResponse
{
    $user = $request->user();

    if (!$user->cabang_id) {
        return response()->json([
            'message' => 'Akun Manager belum terhubung dengan cabang.',
        ], 422);
    }

    return DB::transaction(function () use ($user) {
        // tidak membuat draft otomatis ganda.
        Cabang::query()
            ->where('cabang_id', $user->cabang_id)
            ->lockForUpdate()
            ->firstOrFail();

        // Cegah draft otomatis baru selama instruksi sebelumnya
        // masih menunggu approval atau masih dalam proses pemenuhan.
        $existing = DraftInstruksi::query()
            ->where('cabang_id', $user->cabang_id)
            ->where('asal', 'otomatis')
            ->whereIn('status', [
                'menunggu_persetujuan',
                'disetujui',
                'diproses',
            ])
            ->with('detail')
            ->orderByDesc('draft_id')
            ->first();

        if ($existing) {
            return response()->json([
                'created' => false,
                'draft_id' => $existing->draft_id,
                'no' => sprintf('#RS-%04d', $existing->draft_id),
                'status' => $existing->status,
                'message' => 'Draft otomatis yang menunggu persetujuan sudah tersedia.',
            ]);
        }

        // Ambil stok bahan cabang yang berada di bawah minimum.
        $lowStocks = StokCabang::query()
            ->where('cabang_id', $user->cabang_id)
            ->whereColumn('jumlah_stok', '<', 'stok_minimum')
            ->with('bahan')
            ->lockForUpdate()
            ->get();

        if ($lowStocks->isEmpty()) {
            return response()->json([
                'created' => false,
                'draft_id' => null,
                'message' => 'Tidak ada stok yang membutuhkan restock.',
            ]);
        }

        // Buat header draft.
        $draft = DraftInstruksi::query()->create([
            'cabang_id' => $user->cabang_id,
            'dibuat_oleh' => $user->user_id,
            'asal' => 'otomatis',
            'status' => 'menunggu_persetujuan',
            'dibuat_pada' => now(),
        ]);

        // Buat detail draft dalam satuan dasar.
        foreach ($lowStocks as $stock) {
            $bahan = $stock->bahan;

            if (!$bahan) {
                continue;
            }

            $kebutuhan = max(
                0,
                (float) $stock->stok_minimum - (float) $stock->jumlah_stok
            );

           $isiKemasan = (float) ($bahan->isi_per_kemasan ?: 1);

            $minimumOrder = max(
                1,
                (int) ($bahan->minimum_order_qty ?? 1)
            );

            // Kebutuhan dalam jumlah kemasan utuh.
            $kebutuhanKemasan = (int) ceil(
                $kebutuhan / $isiKemasan
            );

            // Terapkan MOQ.
            $jumlahKemasan = max(
                $kebutuhanKemasan,
                $minimumOrder
            );

            // Simpan kembali dalam satuan dasar.
            $jumlahPesanan = $jumlahKemasan * $isiKemasan;

            if ($jumlahPesanan <= 0) {
                continue;
            }

            DetailDraftInstruksi::query()->create([
                'draft_id' => $draft->draft_id,
                'bahan_id' => $stock->bahan_id,
                'qty_diminta' => $jumlahPesanan,
            ]);
        }

        // Pastikan draft mempunyai detail.
        if (!$draft->detail()->exists()) {
            $draft->delete();

            return response()->json([
                'created' => false,
                'draft_id' => null,
                'message' => 'Tidak ada detail bahan yang dapat dibuat.',
            ]);
        }
        // Beri tahu Manager cabang bahwa ada draft yang perlu direview dan disetujui.
        $this->notifications->approvalNeeded($draft, $draft->detail()->count());
 
        return response()->json([
            'created' => true,
            'draft_id' => $draft->draft_id,
            'no' => sprintf('#RS-%04d', $draft->draft_id),
            'status' => $draft->status,
            'message' => 'Draft otomatis berhasil dibuat.',
        ], 201);
    });
}

    /**
     * Hitung draft restock real-time dari stok_cabang < stok_minimum (belum disimpan).
     * Hanya untuk cabang yang login. Juga kembalikan riwayat instruksi
     * (draft yang sudah disetujui) agar view tidak lagi pakai config demo.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $stokHabis = StokCabang::query()
            ->where('cabang_id', $user->cabang_id)
            ->whereColumn('jumlah_stok', '<', 'stok_minimum')
            ->with('bahan')
            ->orderBy('bahan_id')
            ->get();

        $draft = $stokHabis->map(function ($s) {
            return [
                'bahan_id' => $s->bahan_id,
                'nama_bahan' => $s->bahan?->nama_bahan ?? '-',
                'satuan' => $s->bahan?->satuan ?? '-',
                'nama_kemasan' => $s->bahan?->nama_kemasan ?? '-',
                'isi_per_kemasan' => (float) ($s->bahan?->isi_per_kemasan ?? 1),
                'harga_beli' => (float) ($s->bahan?->harga_beli ?? 0),
                'stok_aktual' => (float) $s->jumlah_stok,
                'stok_minimum' => (float) $s->stok_minimum,
                'kebutuhan' => (float) ($s->stok_minimum - $s->jumlah_stok),
                'minimum_order_qty' => (int) ($s->bahan?->minimum_order_qty ?? 1),
                'minimum_order_base' => (
                    (float) ($s->bahan?->isi_per_kemasan ?? 1)
                    * (int) ($s->bahan?->minimum_order_qty ?? 1)
                ),
            ];
        })->values()->all();

        // Ambil seluruh bahan baku khusus pilihan Reorder Manual.
        $manualItems = BahanBaku::query()
            ->orderBy('nama_bahan')
            ->get()
            ->map(function ($bahan) {
                return [
                    'bahan_id' => $bahan->bahan_id,
                    'nama_bahan' => $bahan->nama_bahan,
                    'satuan' => $bahan->satuan,
                    'nama_kemasan' => $bahan->nama_kemasan,
                    'isi_per_kemasan' => (float) ($bahan->isi_per_kemasan ?? 1),
                    'harga_beli' => (float) ($bahan->harga_beli ?? 0),
                    'minimum_order_qty' => (int) ($bahan->minimum_order_qty ?? 1),
                ];
            })
            ->values()
            ->all();

        $orders = DraftInstruksi::query()
            ->where('cabang_id', $user->cabang_id)
            ->with(['detail.bahan', 'cabang'])
            ->orderByDesc('draft_id')
            ->get()
            ->map(function ($d) {
                $lines = $d->detail->map(function ($dd) {
                    $isi = (float) ($dd->bahan?->isi_per_kemasan ?? 1);

                    return [
                        'item' => (string) $dd->bahan_id,
                        'name' => $dd->bahan?->nama_bahan ?? '-',
                        'qty' => $isi > 0 ? (int) ceil((float) $dd->qty_diminta / $isi) : (float) $dd->qty_diminta,
                        'qty_base' => (float) $dd->qty_diminta,
                        'pack' => $dd->bahan?->nama_kemasan ?? '-',
                        'satuan' => $dd->bahan?->satuan ?? '-',
                        'isi_per_kemasan' => $isi,
                        'harga_beli' => (float) ($dd->bahan?->harga_beli ?? 0),
                        'minimum_order_qty' => (int) ($dd->bahan?->minimum_order_qty ?? 1),
                    ];
                })->values()->all();

                return [
                    'id' => $d->draft_id,
                    'no' => sprintf('#RS-%04d', $d->draft_id),
                    'date' => $d->dibuat_pada
                        ? $d->dibuat_pada->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i:s') . ' WIB'
                        : '-',                    
                    'source' => $d->asal,
                    'status' => $this->mapStatus($d->status),
                    'db_status' => $d->status,
                    'shipment' => null,
                    'items' => $lines,
                ];
            })->values()->all();

        return response()->json([
            'cabang_id' => $user->cabang_id,
            'count' => count($draft),
            'items' => $draft,
            'manual_items' => $manualItems,
            'orders' => $orders,
        ]);
    }

    
/**
 * Menyimpan perubahan detail draft sebelum disetujui Manager.
 */
public function updateDraft(Request $request): JsonResponse
{
    $data = $request->validate([
        'draft_id' => ['required', 'integer'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.bahan_id' => ['required', 'integer', 'distinct', 'exists:bahan_baku,bahan_id'],
        'items.*.qty_diminta' => ['required', 'numeric', 'min:0.001'],
    ]);

    $user = $request->user();

    return DB::transaction(function () use ($data, $user) {
        // Hanya draft milik cabang Manager dan masih menunggu persetujuan.
        $draft = DraftInstruksi::query()
            ->where('draft_id', $data['draft_id'])
            ->where('cabang_id', $user->cabang_id)
            ->where('status', 'menunggu_persetujuan')
            ->lockForUpdate()
            ->firstOrFail();

        $bahanIds = collect($data['items'])
            ->pluck('bahan_id')
            ->all();

        foreach ($data['items'] as $item) {
            $bahan = BahanBaku::query()
                ->findOrFail($item['bahan_id']);

            $this->validateOrderQuantity(
                $bahan,
                (float) $item['qty_diminta']
            );

            DetailDraftInstruksi::query()->updateOrCreate(
                [
                    'draft_id' => $draft->draft_id,
                    'bahan_id' => $item['bahan_id'],
                ],
                [
                    'qty_diminta' => (float) $item['qty_diminta'],
                ]
            );
        }

        // Hapus detail yang dikeluarkan Manager dari draft.
        DetailDraftInstruksi::query()
            ->where('draft_id', $draft->draft_id)
            ->whereNotIn('bahan_id', $bahanIds)
            ->delete();

        return response()->json([
            'message' => 'Perubahan draft berhasil disimpan.',
            'draft_id' => $draft->draft_id,
            'no' => sprintf('#RS-%04d', $draft->draft_id),
            'status' => $draft->status,
            'count' => count($data['items']),
        ]);
    });
}

    
/**
 * Menghitung jumlah minimum pemesanan dalam satuan dasar.
 */
private function minimumOrderBase(BahanBaku $bahan): float
{
    $isiKemasan = (float) ($bahan->isi_per_kemasan ?: 1);
    $minimumOrder = max(1, (int) ($bahan->minimum_order_qty ?? 1));

    return $isiKemasan * $minimumOrder;
}

/**
 * Validasi kuantitas pesanan:
 * - Tidak boleh di bawah MOQ.
 * - Harus berupa kelipatan kemasan utuh.
 * Kuantitas disimpan dalam satuan dasar.
 */
private function validateOrderQuantity(
    BahanBaku $bahan,
    float $quantity
): void {
    $isiKemasan = (float) ($bahan->isi_per_kemasan ?: 1);
    $minimumBase = $this->minimumOrderBase($bahan);

    if ($quantity + 0.000001 < $minimumBase) {
        throw ValidationException::withMessages([
            'items' => [
                "Pesanan {$bahan->nama_bahan} minimal "
                . ($bahan->minimum_order_qty ?? 1)
                . " {$bahan->nama_kemasan}.",
            ],
        ]);
    }

    $jumlahKemasan = $quantity / $isiKemasan;

    if (abs($jumlahKemasan - round($jumlahKemasan)) > 0.000001) {
        throw ValidationException::withMessages([
            'items' => [
                "Jumlah {$bahan->nama_bahan} harus berupa "
                . "kelipatan kemasan utuh ({$bahan->nama_kemasan}).",
            ],
        ]);
    }
}

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'menunggu_persetujuan' => 'approval',
            'disetujui' => 'disetujui',
            'diproses' => 'dikirim',
            'selesai' => 'diterima',
            default => 'disetujui',
        };
    }

    
/**
 * Menyetujui draft yang sudah tersimpan.
 * Untuk Reorder Manual, tetap membuat draft baru secara langsung.
 */
public function approve(Request $request): JsonResponse
{
    $data = $request->validate([
        'draft_id' => ['nullable', 'integer'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.bahan_id' => ['required', 'integer', 'distinct', 'exists:bahan_baku,bahan_id'],
        'items.*.qty_diminta' => ['required', 'numeric', 'min:0.001'],
        'asal' => ['required', 'in:otomatis,manual'],
        'reason' => ['nullable', 'string', 'max:255'],
    ]);

    $user = $request->user();

    return DB::transaction(function () use ($data, $user) {
        if (!empty($data['draft_id'])) {
            // Draft otomatis yang sudah ada: gunakan ID yang sama.
            $draft = DraftInstruksi::query()
                ->where('draft_id', $data['draft_id'])
                ->where('cabang_id', $user->cabang_id)
                ->where('status', 'menunggu_persetujuan')
                ->lockForUpdate()
                ->firstOrFail();

            if ($draft->asal !== $data['asal']) {
                abort(422, 'Sumber draft tidak sesuai.');
            }

            $bahanIds = collect($data['items'])
                ->pluck('bahan_id')
                ->all();

            foreach ($data['items'] as $item) {
                $bahan = BahanBaku::query()
                    ->findOrFail($item['bahan_id']);

                $this->validateOrderQuantity(
                    $bahan,
                    (float) $item['qty_diminta']
                );

                DetailDraftInstruksi::query()->updateOrCreate(
                    [
                        'draft_id' => $draft->draft_id,
                        'bahan_id' => $item['bahan_id'],
                    ],
                    [
                        'qty_diminta' => (float) $item['qty_diminta'],
                    ]
                );
            }

            DetailDraftInstruksi::query()
                ->where('draft_id', $draft->draft_id)
                ->whereNotIn('bahan_id', $bahanIds)
                ->delete();

            // Ubah status draft yang sama menjadi disetujui.
            $draft->update([
                'status' => 'disetujui',
                'disetujui_oleh' => $user->user_id,
                'tanggal_persetujuan' => now(),
            ]);
        } else {
            // Reorder Manual: tetap membuat draft baru seperti sebelumnya.
            if ($data['asal'] !== 'manual') {
                abort(422, 'Draft otomatis harus menggunakan draft yang sudah tersimpan.');
            }

            $draft = DraftInstruksi::query()->create([
                'cabang_id' => $user->cabang_id,
                'dibuat_oleh' => $user->user_id,
                'asal' => 'manual',
                'status' => 'disetujui',
                'dibuat_pada' => now(),
                'disetujui_oleh' => $user->user_id,
                'tanggal_persetujuan' => now(),
            ]);

            foreach ($data['items'] as $item) {
                $bahan = BahanBaku::query()
                    ->findOrFail($item['bahan_id']);

                $this->validateOrderQuantity(
                    $bahan,
                    (float) $item['qty_diminta']
                );

                DetailDraftInstruksi::query()->create([
                    'draft_id' => $draft->draft_id,
                    'bahan_id' => $item['bahan_id'],
                    'qty_diminta' => (float) $item['qty_diminta'],
                ]);
            }
        }

        \App\Models\NotifikasiAlert::query()->create([
            'cabang_id' => $user->cabang_id,
            'bahan_id' => null,
            'tipe' => 'stok_menipis',
            'pesan' => 'Draft Instruksi #' . sprintf('%04d', $draft->draft_id)
                . ' disetujui (' . $draft->asal . ').',
            'status_baca' => false,
            'tanggal' => now(),
        ]);
        // Beri tahu DC bahwa ada pesanan masuk yang harus diproses.
        $this->notifications->orderApproved($draft, count($data['items']));
        
        return response()->json([
            'draft_id' => $draft->draft_id,
            'no' => sprintf('#RS-%04d', $draft->draft_id),
            'status' => 'disetujui',
            'count' => count($data['items']),
        ]);
    });
}

}