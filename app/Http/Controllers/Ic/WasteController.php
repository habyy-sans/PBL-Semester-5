<?php

namespace App\Http\Controllers\Ic;

use App\Http\Controllers\Controller;
use App\Models\MenuProduk;
use App\Models\StockMovement;
use App\Models\StokCabang;
use App\Models\WasteLog;
use App\Models\NotifikasiAlert;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WasteController extends Controller
{
    private const TIMEZONE = 'Asia/Jakarta';

    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public function __construct(
        private readonly CatalogService $catalog,
    ) {}

    /**
     * Halaman Waste Log + riwayat waste cabang dari database.
     */
    public function index(Request $request): View
    {
        return view('ic.waste.index', [
            'history' => $this->history($request->user()),
        ]);
    }

    /**
     * Daftar bahan untuk dropdown "Barang".
     *
     * Dikirim sebagai LIST (bukan object) dan tiap bahan punya field `id`
     * (bahan_id, string), karena view memakai :key="item.id" dan items.find().
     */
    public function items(Request $request): JsonResponse
    {
        $items = [];

        foreach ($this->catalog->items($request->user()->cabang_id) as $bahanId => $item) {
            $items[] = ['id' => (string) $bahanId] + $item;
        }

        return response()->json($items);
    }

    /**
     * Daftar menu + resep untuk dropdown "Menu".
     *
     * Dikirim sebagai LIST dengan field `id` (menu_id, string). Menu yang belum
     * punya resep dilewati karena tidak ada bahan yang bisa dikurangi.
     */
    public function menus(Request $request): JsonResponse
    {
        $menus = [];

        foreach ($this->catalog->menus() as $menuId => $menu) {
            if ((array) $menu['recipe'] === []) {
                continue;
            }

            $menus[] = ['id' => (string) $menuId] + $menu;
        }

        return response()->json($menus);
    }

    /**
     * Catat waste: 1 POST = 1 baris waste_log per bahan dengan kelompok_id sama,
     * pengurangan stok_cabang, dan stock_movements (jenis waste) dalam satu DB transaction.
     *
     * Payload: { items: [{ bahan_id, qty, alasan, keterangan }], menu_id?, portions? }
     * menu_id + portions hanya dikirim untuk waste jenis Menu (disimpan di keterangan mutasi).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.bahan_id' => ['required', 'integer', 'exists:bahan_baku,bahan_id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.alasan' => ['required', 'string', 'max:100'],
            'items.*.keterangan' => ['nullable', 'string', 'max:255'],
            'menu_id' => ['nullable', 'integer', 'exists:menu_produk,menu_id'],
            'portions' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $kelompokId = (string) Str::uuid();

        $menuLabel = null;

        if (! empty($data['menu_id'])) {
            $menu = MenuProduk::query()->find($data['menu_id']);
            $menuLabel = $menu !== null
                ? 'Menu: '.$menu->nama_menu.' ×'.($data['portions'] ?? 1).' · '
                : null;
        }

        return DB::transaction(function () use ($data, $user, $kelompokId, $menuLabel) {
            // Gabungkan baris bahan yang sama supaya pengecekan stok akurat.
            $merged = [];

            foreach ($data['items'] as $item) {
                $id = (int) $item['bahan_id'];

                if (isset($merged[$id])) {
                    $merged[$id]['qty'] += (float) $item['qty'];
                } else {
                    $merged[$id] = [
                        'bahan_id' => $id,
                        'qty' => (float) $item['qty'],
                        'alasan' => $item['alasan'],
                        'keterangan' => $item['keterangan'] ?? null,
                    ];
                }
            }

            $bahanIds = array_keys($merged);

            // Kunci stok cabang yang terlibat.
            $stokMap = StokCabang::query()
                ->with('bahan')
                ->where('cabang_id', $user->cabang_id)
                ->whereIn('bahan_id', $bahanIds)
                ->orderBy('bahan_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('bahan_id');

            $logs = [];

            foreach ($merged as $bahanId => $item) {
                $qty = $item['qty'];
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
                    'keterangan' => 'Waste · '.$menuLabel.$item['alasan']
                        .(! empty($item['keterangan']) ? ' ('.$item['keterangan'].')' : ''),
                    'kelompok_id' => $kelompokId,
                ]);

                $logs[] = $log;
            }

            NotifikasiAlert::query()->create([
                'cabang_id' => $user->cabang_id,
                'bahan_id' => null,
                'tipe' => 'waste',
                'pesan' => 'Laporan waste [kelompok_id='.$kelompokId.']',
                'status_baca' => false,
                'tanggal' => now(),
            ]);

            return response()->json([
                'kelompok_id' => $kelompokId,
                'count' => count($logs),
            ]);
        });
    }

    /**
     * Riwayat waste cabang untuk tabel di halaman (terbaru di atas).
     * Format sama dengan entry yang dibuat JS setelah simpan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function history($user): array
    {
        if ($user->cabang_id === null) {
            return [];
        }

        $logs = WasteLog::query()
            ->where('cabang_id', $user->cabang_id)
            ->with(['bahan', 'kepalaShift'])
            ->orderByDesc('tanggal')
            ->orderByDesc('waste_id')
            ->limit(500)
            ->get();

        $movements = StockMovement::query()
            ->where('cabang_id', $user->cabang_id)
            ->where('jenis_mutasi', 'waste')
            ->whereIn('kelompok_id', $logs->pluck('kelompok_id')->filter()->unique()->values()->all())
            ->get()
            ->keyBy(fn ($m) => $m->kelompok_id.'|'.$m->bahan_id);

        return $logs
            ->groupBy(fn (WasteLog $log) => $log->kelompok_id ?? 'x'.$log->waste_id)
            ->map(fn (Collection $group, string $key) => $this->historyEntry($key, $group, $movements))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, WasteLog>  $group
     * @param  Collection<string, StockMovement>  $movements
     * @return array<string, mixed>
     */
    private function historyEntry(string $key, Collection $group, Collection $movements): array
    {
        /** @var WasteLog $first */
        $first = $group->first();
        $note = (string) ($movements->get($first->kelompok_id.'|'.$first->bahan_id)?->keterangan ?? '');

        // Nama menu & jumlah porsi (hanya ada untuk waste menu yang dicatat lewat form baru).
        $menuName = null;
        $portions = null;

        if (preg_match('/^Waste · Menu: (.+) ×(\d+) · /u', $note, $m)) {
            $menuName = $m[1];
            $portions = (int) $m[2];
        }

        $userNote = preg_match('/\(([^()]*)\)$/u', $note, $m) ? trim($m[1]) : '';

        $isMenu = $menuName !== null || $group->count() > 1;

        $value = $group->sum(function (WasteLog $log) {
            $size = (float) ($log->bahan?->isi_per_kemasan ?: 1);

            return (float) $log->qty * ((float) ($log->bahan?->harga_beli ?? 0) / $size);
        });

        $time = $first->tanggal->copy()->timezone(self::TIMEZONE);

        return [
            'id' => $key,
            'time' => $time->day.' '.self::MONTHS[$time->month - 1].' '.$time->year.' '.$time->format('H:i'),
            'type' => $isMenu ? 'menu' : 'bahan',
            'name' => $isMenu
                ? ($menuName ?? 'Waste menu ('.$group->count().' bahan)')
                : ($first->bahan?->nama_bahan ?? '-'),
            'qty' => $isMenu
                ? ($portions !== null ? $portions.' Porsi' : $group->count().' Bahan')
                : $this->packText($first),
            'reason' => $first->alasan,
            'note' => $userNote,
            'value' => (int) round($value),
            'by' => $first->kepalaShift?->nama_lengkap ?? '-',
            'deductions' => $group->map(fn (WasteLog $log) => [
                'name' => $log->bahan?->nama_bahan ?? '-',
                'text' => $this->deductionText($log),
            ])->values()->all(),
        ];
    }

    /** "2 Botol" bila kelipatan kemasan utuh, selain itu "300 ml". */
    private function packText(WasteLog $log): string
    {
        $qty = (float) $log->qty;
        $size = (float) ($log->bahan?->isi_per_kemasan ?: 1);
        $unit = $log->bahan?->satuan ?? '';
        $pack = $log->bahan?->nama_kemasan ?? $unit;

        if ($size > 1 && $qty >= $size && fmod($qty, $size) == 0.0) {
            return $this->num($qty / $size).' '.$pack;
        }

        return $this->num($qty).' '.($size > 1 ? $unit : $pack);
    }

    /** "700 ml (1 Botol)" / "70 ml" — sama seperti tampilan setelah simpan. */
    private function deductionText(WasteLog $log): string
    {
        $qty = (float) $log->qty;
        $size = (float) ($log->bahan?->isi_per_kemasan ?: 1);
        $unit = $log->bahan?->satuan ?? '';
        $text = $this->num($qty).' '.$unit;

        if ($size > 1 && $qty >= $size && fmod($qty, $size) == 0.0) {
            $text .= ' ('.$this->num($qty / $size).' '.($log->bahan?->nama_kemasan ?? '').')';
        }

        return $text;
    }

    private function num(float $value): string
    {
        $text = number_format($value, 2, ',', '.');

        return str_contains($text, ',') ? rtrim(rtrim($text, '0'), ',') : $text;
    }
}