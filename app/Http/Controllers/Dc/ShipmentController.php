<?php

namespace App\Http\Controllers\Dc;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\DetailSuratJalan;
use App\Models\DraftInstruksi;
use App\Models\StockMovement;
use App\Models\StokDc;
use App\Models\SuratJalan;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Halaman Pengiriman & Surat Jalan DC.
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

        $shipments = SuratJalan::query()
            ->where('dc_id', $dcId)
            ->with([
                'dc',
                'cabang',
                'diterbitkanOleh',
                'detail.bahan',
                'detail.detailDraft',
                'konfirmasi',
            ])
            ->orderByDesc('surat_jalan_id')
            ->get()
            ->map(fn ($sj) => $this->format($sj))
            ->values()
            ->all();

        return view('dc.pengiriman.index', [
            'items' => $items,
            'storeList' => $storeList,
            'shipments' => $shipments,
        ]);
    }

    /**
     * Membuat Surat Jalan.
     *
     * Stok DC dikurangi saat Surat Jalan berhasil dibuat,
     * bukan saat tombol Kirim ditekan.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'draft_id' => [
                'required',
                'integer',
                'exists:draft_instruksi,draft_id',
            ],
            'catatan' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.detail_draft_id' => [
                'required',
                'integer',
                'distinct',
                'exists:detail_draft_instruksi,detail_draft_id',
            ],
            'items.*.bahan_id' => [
                'required',
                'integer',
                'exists:bahan_baku,bahan_id',
            ],
            'items.*.qty_dikirim' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ]);

        $user = $request->user();

        abort_if(
            $user->dc_id === null,
            403,
            'Akun DC belum memiliki penugasan.'
        );

        $result = DB::transaction(function () use ($data, $user) {

            // Kunci pesanan selama proses penerbitan Surat Jalan.
            $draft = DraftInstruksi::query()
                ->where('draft_id', $data['draft_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array(
                $draft->status,
                ['disetujui', 'diproses'],
                true
            )) {
                throw ValidationException::withMessages([
                    'draft_id' => 'Pesanan belum disetujui atau sudah selesai dipenuhi.',
                ]);
            }

            // Ambil seluruh detail pesanan dan kunci datanya.
            $draftDetails = $draft->detail()
                ->lockForUpdate()
                ->get()
                ->keyBy('detail_draft_id');

            if ($draftDetails->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Pesanan tidak memiliki detail bahan baku.',
                ]);
            }

            $requestedItems = collect($data['items']);
            $partialShipment = false;

            // Hitung jumlah yang pernah dikirim untuk setiap detail.
            // Surat Jalan yang dibatalkan tidak ikut dihitung.
            $previouslySent = DetailSuratJalan::query()
                ->whereIn('detail_draft_id', $draftDetails->keys())
                ->whereHas('suratJalan', function ($query) use ($user) {
                    $query->where('dc_id', $user->dc_id)
                        ->where('status', '!=', 'dibatalkan');
                })
                ->select('detail_draft_id')
                ->selectRaw('SUM(qty_dikirim) AS total_dikirim')
                ->groupBy('detail_draft_id')
                ->pluck('total_dikirim', 'detail_draft_id');

            $validatedLines = [];

            foreach ($requestedItems as $item) {
                $detailDraft = $draftDetails->get(
                    (int) $item['detail_draft_id']
                );

                if (
                    !$detailDraft ||
                    (int) $detailDraft->bahan_id !== (int) $item['bahan_id']
                ) {
                    throw ValidationException::withMessages([
                        'items' => 'Bahan yang dipilih tidak sesuai dengan detail pesanan.',
                    ]);
                }

                $alreadySent = (float) (
                    $previouslySent[$detailDraft->detail_draft_id] ?? 0
                );

                $ordered = (float) $detailDraft->qty_diminta;

                $remaining = max(0, $ordered - $alreadySent);

                $quantity = (float) $item['qty_dikirim'];

                if ($remaining <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Detail pesanan ini sudah terpenuhi seluruhnya.',
                    ]);
                }

                if ($quantity > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => "Jumlah kirim melebihi sisa pesanan bahan ID {$item['bahan_id']}. Sisa: {$remaining}.",
                    ]);
                }

                if ($quantity < $remaining) {
                    $partialShipment = true;
                }

                // Kunci stok DC untuk mencegah pengurangan bersamaan.
                $stock = StokDc::query()
                    ->where('dc_id', $user->dc_id)
                    ->where('bahan_id', $item['bahan_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$stock) {
                    throw ValidationException::withMessages([
                        'items' => "Stok DC untuk bahan ID {$item['bahan_id']} tidak ditemukan.",
                    ]);
                }

                $available = (float) $stock->jumlah_stok;

                if ($quantity > $available) {
                    throw ValidationException::withMessages([
                        'items' => "Stok DC tidak mencukupi untuk bahan ID {$item['bahan_id']}. Tersedia: {$available}.",
                    ]);
                }

                $validatedLines[] = [
                    'detail_draft_id' => $detailDraft->detail_draft_id,
                    'bahan_id' => $detailDraft->bahan_id,
                    'qty' => $quantity,
                    'stock' => $stock,
                ];
            }

            // Hitung sisa seluruh detail setelah pengiriman ini.
            $remainingAfterThisShipment = [];

            foreach ($draftDetails as $detailDraftId => $detailDraft) {
                $alreadySent = (float) (
                    $previouslySent[$detailDraftId] ?? 0
                );

                $currentSent = collect($validatedLines)
                    ->where('detail_draft_id', $detailDraftId)
                    ->sum('qty');

                $remaining = max(
                    0,
                    (float) $detailDraft->qty_diminta
                        - $alreadySent
                        - $currentSent
                );

                $remainingAfterThisShipment[$detailDraftId] = $remaining;

                if ($remaining > 0) {
                    $partialShipment = true;
                }
            }

            if (
                $partialShipment &&
                blank($data['catatan'] ?? null)
            ) {
                throw ValidationException::withMessages([
                    'catatan' => 'Catatan wajib diisi jika pengiriman dilakukan secara parsial.',
                ]);
            }

            // Buat Surat Jalan.
            $sj = SuratJalan::query()->create([
                'cabang_id' => $draft->cabang_id,
                'dc_id' => $user->dc_id,
                'diterbitkan_oleh' => $user->user_id,
                'tanggal_kirim' => now(),
                'status' => 'disiapkan',
                'catatan' => $data['catatan'] ?? null,
            ]);

            foreach ($validatedLines as $line) {

                // Simpan detail Surat Jalan.
                DetailSuratJalan::query()->create([
                    'surat_jalan_id' => $sj->surat_jalan_id,
                    'bahan_id' => $line['bahan_id'],
                    'detail_draft_id' => $line['detail_draft_id'],
                    'qty_dikirim' => $line['qty'],
                    'qty_diterima' => null,
                ]);

                // Kurangi stok DC satu kali saat Surat Jalan dibuat.
                $line['stock']->jumlah_stok =
                    (float) $line['stock']->jumlah_stok - $line['qty'];

                $line['stock']->save();

                // Catat mutasi stok pengiriman DC.
                StockMovement::query()->create([
                    'bahan_id' => $line['bahan_id'],
                    'cabang_id' => null,
                    'dc_id' => $user->dc_id,
                    'jenis_mutasi' => 'pengiriman_dc',
                    'jumlah' => $line['qty'],
                    'referensi_id' => $sj->surat_jalan_id,
                    'tanggal' => now(),
                    'keterangan' => 'Pengiriman melalui Surat Jalan #' .
                        sprintf('%03d', $sj->surat_jalan_id),
                ]);
            }

            $allFulfilled = collect($remainingAfterThisShipment)
                ->every(fn ($remaining) => $remaining <= 0);

            $draft->status = $allFulfilled
                ? 'selesai'
                : 'diproses';

            $draft->save();

            return [
                'surat_jalan_id' => $sj->surat_jalan_id,
                'no' => sprintf('#SJ-%03d', $sj->surat_jalan_id),
                'status' => $sj->status,
                'draft_status' => $draft->status,
                'partial' => $partialShipment,
            ];
        });

        return response()->json($result, 201);
    }

    /**
     * DC mengubah status disiapkan menjadi dikirim.
     *
     * Stok tidak dikurangi lagi karena sudah dikurangi
     * saat Surat Jalan dibuat.
     *
     * Notifikasi dikirim kepada IC cabang tujuan.
     */
    public function kirim(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        abort_if(
            $user->dc_id === null,
            403,
            'Akun DC belum memiliki penugasan.'
        );

        return DB::transaction(function () use ($id, $user) {

            $sj = SuratJalan::query()
                ->where('surat_jalan_id', $id)
                ->where('dc_id', $user->dc_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sj->status !== 'disiapkan') {
                throw ValidationException::withMessages([
                    'status' => 'Surat jalan tidak berada dalam status disiapkan.',
                ]);
            }

            // Ubah status tanpa mengurangi stok lagi.
            $sj->status = 'dikirim';
            $sj->tanggal_kirim = now();
            $sj->save();

            // Notifikasi kepada IC cabang tujuan.
            $this->notifications->shipmentDispatched($sj);

            return response()->json([
                'surat_jalan_id' => $sj->surat_jalan_id,
                'no' => sprintf('#SJ-%03d', $sj->surat_jalan_id),
                'status' => 'menunggu',
            ]);
        });
    }

    /**
     * Membatalkan Surat Jalan yang belum diberangkatkan.
     *
     * Stok DC dikembalikan satu kali dan dicatat sebagai
     * pembatalan_pengiriman_dc.
     */
    public function batalkan(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();

        abort_if(
            $user->dc_id === null,
            403,
            'Akun DC belum memiliki penugasan.'
        );

        $result = DB::transaction(function () use ($id, $user, $data) {

            // Kunci Surat Jalan agar tidak bisa dibatalkan dua kali.
            $sj = SuratJalan::query()
                ->where('surat_jalan_id', $id)
                ->where('dc_id', $user->dc_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sj->status !== 'disiapkan') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya Surat Jalan berstatus disiapkan yang dapat dibatalkan.',
                ]);
            }

            // Ambil dan kunci seluruh detail Surat Jalan.
            $details = DetailSuratJalan::query()
                ->where('surat_jalan_id', $sj->surat_jalan_id)
                ->orderBy('bahan_id')
                ->lockForUpdate()
                ->get();

            if ($details->isEmpty()) {
                throw ValidationException::withMessages([
                    'surat_jalan' => 'Surat Jalan tidak memiliki detail barang.',
                ]);
            }

            // Pastikan Surat Jalan belum pernah dikonfirmasi IC.
            if ($sj->konfirmasi()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Surat Jalan yang sudah dikonfirmasi IC tidak dapat dibatalkan.',
                ]);
            }

            // Ambil draft asal dari detail Surat Jalan.
            $draftIds = $details
                ->map(fn ($detail) => $detail->detailDraft?->draft_id)
                ->filter()
                ->unique()
                ->values();

            if ($draftIds->count() !== 1) {
                throw ValidationException::withMessages([
                    'surat_jalan' => 'Draft asal Surat Jalan tidak valid.',
                ]);
            }

            $draft = DraftInstruksi::query()
                ->where('draft_id', $draftIds->first())
                ->lockForUpdate()
                ->firstOrFail();

            // Gabungkan jumlah berdasarkan bahan agar stok
            // tidak tertimpa jika bahan muncul pada beberapa detail.
            $quantities = $details
                ->groupBy('bahan_id')
                ->map(fn ($lines) => $lines->sum(
                    fn ($line) => (float) $line->qty_dikirim
                ));

            foreach ($quantities as $bahanId => $quantity) {

                $stock = StokDc::query()
                    ->where('dc_id', $user->dc_id)
                    ->where('bahan_id', $bahanId)
                    ->lockForUpdate()
                    ->first();

                if (!$stock) {
                    throw ValidationException::withMessages([
                        'stock' => "Data stok DC untuk bahan ID {$bahanId} tidak ditemukan.",
                    ]);
                }

                // Kembalikan stok DC.
                $stock->jumlah_stok =
                    (float) $stock->jumlah_stok + $quantity;

                $stock->save();

                // Catat mutasi kompensasi.
                StockMovement::query()->create([
                    'bahan_id' => $bahanId,
                    'cabang_id' => null,
                    'dc_id' => $user->dc_id,
                    'jenis_mutasi' => 'pembatalan_pengiriman_dc',
                    'jumlah' => $quantity,
                    'referensi_id' => $sj->surat_jalan_id,
                    'tanggal' => now(),
                    'keterangan' => 'Pembatalan Surat Jalan #' .
                        sprintf('%03d', $sj->surat_jalan_id) .
                        '. Alasan: ' . $data['alasan'],
                ]);
            }

            // Ubah status Surat Jalan.
            $sj->status = 'dibatalkan';
            $sj->save();

            // Hitung ulang pemenuhan draft setelah Surat Jalan dibatalkan.
            $draftDetailIds = $draft->detail()
                ->pluck('detail_draft_id');

            $sentTotals = DetailSuratJalan::query()
                ->whereIn('detail_draft_id', $draftDetailIds)
                ->whereHas('suratJalan', function ($query) use ($user) {
                    $query->where('dc_id', $user->dc_id)
                        ->where('status', '!=', 'dibatalkan');
                })
                ->select('detail_draft_id')
                ->selectRaw('SUM(qty_dikirim) AS total_dikirim')
                ->groupBy('detail_draft_id')
                ->pluck('total_dikirim', 'detail_draft_id');

            $draftDetails = $draft->detail()->get();

            $anySent = false;
            $allFulfilled = true;

            foreach ($draftDetails as $draftDetail) {
                $sent = (float) ($sentTotals[$draftDetail->detail_draft_id] ?? 0);
                $ordered = (float) $draftDetail->qty_diminta;

                if ($sent > 0) {
                    $anySent = true;
                }

                if ($sent < $ordered) {
                    $allFulfilled = false;
                }
            }

            if (!$anySent) {
                $draft->status = 'disetujui';
            } elseif ($allFulfilled) {
                $draft->status = 'selesai';
            } else {
                $draft->status = 'diproses';
            }

            $draft->save();

            return [
                'surat_jalan_id' => $sj->surat_jalan_id,
                'no' => sprintf('#SJ-%03d', $sj->surat_jalan_id),
                'status' => 'dibatalkan',
                'draft_status' => $draft->status,
            ];
        });

        return response()->json([
            'message' => 'Surat Jalan berhasil dibatalkan dan stok DC telah dikembalikan.',
            ...$result,
        ]);
    }


    /**
     * Mengambil catatan per bahan dari konfirmasi penerimaan.
     *
     * Format kondisi:
     * "selisih: Nama Bahan: catatan; Bahan Lain: catatan"
     *
     * @return array<string, string>
     */
    private function notesFromKondisi(?string $kondisi): array
    {
        if (
            $kondisi === null ||
            !preg_match('/^(?:selisih|baik): (.+)$/su', $kondisi, $m)
        ) {
            return [];
        }

        $notes = [];

        foreach (explode('; ', $m[1]) as $part) {
            $pair = explode(': ', $part, 2);

            if (count($pair) === 2) {
                $notes[trim($pair[0])] = trim($pair[1]);
            }
        }

        return $notes;
    }

    /**
     * Format data Surat Jalan untuk halaman DC.
     */
    private function format(SuratJalan $sj): array
    {
        $notes = $this->notesFromKondisi(
            $sj->konfirmasi?->kondisi
        );

        $firstDetail = $sj->detail->first(
            fn ($detail) => $detail->detail_draft_id !== null
        );

        $draftId = $firstDetail?->detailDraft?->draft_id;

        $items = $sj->detail->map(fn ($detail) => [
            'item' => (string) $detail->bahan_id,
            'sent' => (float) $detail->qty_dikirim,
            'received' => $detail->qty_diterima === null
                ? null
                : (float) $detail->qty_diterima,
            'note' => $notes[$detail->bahan?->nama_bahan ?? ''] ?? null,
        ])->values()->all();

        return [
            'id' => $sj->surat_jalan_id,
            'no' => sprintf('SJ-%03d', $sj->surat_jalan_id),

            'date' => $sj->tanggal_kirim
                ? $sj->tanggal_kirim
                    ->timezone('Asia/Jakarta')
                    ->format('d F Y, H:i') . ' WIB'
                : '-',

            'store' => (string) $sj->cabang_id,
            'order' => $draftId
                ? sprintf('RS-%04d', $draftId)
                : '-',

            'origin' => 'DC',
            'status' => $sj->computed_status,

            'company' => 'KOPI STUDIO 24',

            'dc_name' => $sj->dc?->nama_dc ?? 'Distribution Center',
            'dc_address' => $sj->dc?->alamat ?? '-',
            'dc_phone' => $sj->dc?->telepon ?? '-',

            'branch_name' => $sj->cabang?->nama_cabang ?? '-',
            'branch_address' => $sj->cabang?->alamat ?? '-',
            'branch_phone' => $sj->cabang?->telepon ?? '-',

            'prepared_by' => $sj->diterbitkanOleh?->nama_lengkap ?? '-',

            'catatan' => $sj->catatan,

            'items' => $items,
        ];
    }
}
