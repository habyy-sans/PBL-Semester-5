<?php

namespace App\Http\Controllers\Ic;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\StokCabang;
use App\Models\WasteLog;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WasteController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
    ) {}

    /**
     * Data bahan untuk form waste (keyed by bahan_id database).
     */
    public function items(Request $request): JsonResponse
    {
        return response()->json($this->catalog->items($request->user()->cabang_id));
    }

    /**
     * Data menu + resep untuk form waste (keyed by menu_id database).
     */
    public function menus(Request $request): JsonResponse
    {
        return response()->json($this->catalog->menus());
    }

    /**
     * Catat waste: 1 POST = 1 baris waste_log per bahan dengan kelompok_id sama,
     * pengurangan stok_cabang, dan stock_movements (jenis waste) dalam satu DB transaction.
     *
     * Payload: { items: [{ bahan_id, qty, alasan, keterangan }] }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.bahan_id' => ['required', 'integer', 'exists:bahan_baku,bahan_id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.alasan' => ['required', 'string', 'max:100'],
            'items.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $kelompokId = (string) Str::uuid();

        return DB::transaction(function () use ($data, $user, $kelompokId) {
            $bahanIds = array_column($data['items'], 'bahan_id');

            // Kunci stok cabang yang terlibat.
            $stokMap = StokCabang::query()
                ->where('cabang_id', $user->cabang_id)
                ->whereIn('bahan_id', $bahanIds)
                ->orderBy('bahan_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('bahan_id');

            $logs = [];

            foreach ($data['items'] as $item) {
                $bahanId = $item['bahan_id'];
                $qty = (float) $item['qty'];
                $stok = $stokMap->get($bahanId);

                if ($stok === null || (float) $stok->jumlah_stok < $qty) {
                    $namaBahan = $stok !== null ? $stok->bahan->nama_bahan : (string) $bahanId;

                    throw ValidationException::withMessages([
                        'items' => "Stok bahan {$namaBahan} tidak cukup.",
                    ]);
                }

                // 1) Insert waste_log.
                $log = WasteLog::query()->create([
                    'cabang_id' => $user->cabang_id,
                    'bahan_id' => $bahanId,
                    'kepala_shift_id' => $user->user_id,
                    'qty' => $qty,
                    'alasan' => $item['alasan'],
                    'tanggal' => now(),
                    'kelompok_id' => $kelompokId,
                ]);

                // 2) Kurangi stok cabang.
                $stok->jumlah_stok = (float) $stok->jumlah_stok - $qty;
                $stok->save();

                // 3) Insert stock_movement (jenis waste, jumlah positif sesuai CHECK).
                StockMovement::query()->create([
                    'bahan_id' => $bahanId,
                    'cabang_id' => $user->cabang_id,
                    'jenis_mutasi' => 'waste',
                    'jumlah' => $qty,
                    'referensi_id' => $log->waste_id,
                    'tanggal' => now(),
                    'keterangan' => 'Waste · '.$item['alasan'].($item['keterangan'] ? ' ('.$item['keterangan'].')' : ''),
                    'kelompok_id' => $kelompokId,
                ]);

                $logs[] = $log;
            }

            return response()->json([
                'kelompok_id' => $kelompokId,
                'count' => count($logs),
            ]);
        });
    }
}
