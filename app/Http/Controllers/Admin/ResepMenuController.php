<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use App\Models\KategoriMenu;
use App\Models\MenuProduk;
use App\Models\ResepMenu;
use App\Models\VarianMenu;
use App\Services\AdminMasterSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResepMenuController extends Controller
{
    public function __construct(
        private readonly AdminMasterSyncService $sync,
    ) {}

    public function index(Request $request): View
    {
        $baseQuery = VarianMenu::query()
            ->with(['menu.kategori', 'resep.bahan'])
            ->join('menu_produk', 'menu_produk.menu_id', '=', 'varian_menu.menu_id')
            ->select('varian_menu.*');

        $queryKeyword = $request->query('q');
        $keyword = is_string($queryKeyword) ? trim($queryKeyword) : '';

        if ($keyword !== '') {
            $pattern = '%'.mb_strtolower($keyword).'%';
            $baseQuery->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereRaw('LOWER(menu_produk.nama_menu) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(varian_menu.nama_varian) LIKE ?', [$pattern])
                    ->orWhereHas('resep.bahan', function (Builder $ingredient) use ($pattern): void {
                        $ingredient->whereRaw('LOWER(nama_bahan) LIKE ?', [$pattern]);
                    });
            });
        }

        $activeFilters = [];

        $kategoriParam = $request->query('kategori_menu_id');
        if (is_string($kategoriParam) && ctype_digit($kategoriParam)) {
            $baseQuery->where('menu_produk.kategori_menu_id', (int) $kategoriParam);
            $activeFilters['kategori_menu_id'] = $kategoriParam;
        }

        $menuParam = $request->query('menu_id');
        if (is_string($menuParam) && ctype_digit($menuParam)) {
            $baseQuery->where('varian_menu.menu_id', (int) $menuParam);
            $activeFilters['menu_id'] = $menuParam;
        }

        $bahanParam = $request->query('bahan_id');
        if (is_string($bahanParam) && ctype_digit($bahanParam)) {
            $baseQuery->whereHas('resep', function (Builder $resep) use ($bahanParam): void {
                $resep->where('bahan_id', (int) $bahanParam);
            });
            $activeFilters['bahan_id'] = $bahanParam;
        }

        $counts = [
            'all' => (clone $baseQuery)->count(),
            'lengkap' => (clone $baseQuery)->has('resep')->count(),
            'belum' => (clone $baseQuery)->doesntHave('resep')->count(),
        ];

        $query = clone $baseQuery;
        $statusParam = $request->query('status');
        if (is_string($statusParam) && in_array($statusParam, ['lengkap', 'belum'], true)) {
            $activeFilters['status'] = $statusParam;

            if ($statusParam === 'lengkap') {
                $query->has('resep');
            } else {
                $query->doesntHave('resep');
            }
        }

        $variants = $query
            ->orderBy('menu_produk.nama_menu')
            ->orderBy('varian_menu.nama_varian')
            ->orderBy('varian_menu.varian_id')
            ->paginate(12)
            ->withQueryString();

        $metrics = [];
        foreach ($variants as $variant) {
            $sellingPrice = (float) ($variant->menu?->harga_jual ?? 0) + (float) $variant->harga_tambahan;
            $hpp = $this->sync->calculateVariantHpp($variant);
            $profit = $sellingPrice - $hpp;
            $margin = $sellingPrice > 0 ? ($profit / $sellingPrice) * 100 : 0.0;

            $metrics[$variant->varian_id] = [
                'selling_price' => $sellingPrice,
                'hpp' => $hpp,
                'profit' => $profit,
                'margin' => $margin,
            ];
        }

        return view('admin.resep.index', [
            'variants' => $variants,
            'metrics' => $metrics,
            'counts' => $counts,
            'categories' => KategoriMenu::query()->orderBy('nama_kategori')->get(['kategori_menu_id', 'nama_kategori']),
            'menus' => MenuProduk::query()->orderBy('nama_menu')->get(['menu_id', 'nama_menu']),
            'ingredients' => BahanBaku::query()->orderBy('nama_bahan')->get(['bahan_id', 'nama_bahan']),
            'filters' => $activeFilters,
            'keyword' => $keyword,
            'hasActiveFilters' => $keyword !== '' || $activeFilters !== [],
        ]);
    }

    public function create(Request $request): View
    {
        $rawVarianId = $request->query('varian_id');
        $selectedVariant = is_string($rawVarianId) && ctype_digit($rawVarianId)
            ? VarianMenu::query()->with(['menu.kategori', 'resep.bahan'])->find((int) $rawVarianId)
            : null;

        return $this->builderView($selectedVariant, false);
    }

    public function builder(VarianMenu $varian): View
    {
        $varian->load(['menu.kategori', 'resep.bahan']);

        return $this->builderView($varian, true);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->has('items')) {
            $validated = $this->validateBatchRecipe($request, true);
            $varian = VarianMenu::query()->with('menu')->findOrFail((int) $validated['varian_id']);
            $count = $this->sync->syncRecipeForVariant($varian, $validated['items']);

            return redirect()
                ->route('admin.resep-menu.index')
                ->with('success', "Resep {$varian->menu?->nama_menu} - {$varian->nama_varian} berhasil disimpan ({$count} bahan baku).");
        }

        // Fallback single-item agar pemanggil atau test lama tetap kompatibel.
        $validated = $this->validateSingleItem($request);

        DB::transaction(function () use ($validated): void {
            $exists = ResepMenu::query()
                ->where('varian_id', (int) $validated['varian_id'])
                ->where('bahan_id', (int) $validated['bahan_id'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'bahan_id' => 'Bahan baku ini sudah ada di resep varian yang dipilih.',
                ]);
            }

            ResepMenu::query()->create($validated);
        });

        return redirect()
            ->route('admin.resep-menu.index')
            ->with('success', 'Resep Menu berhasil ditambahkan.');
    }

    public function sync(Request $request, VarianMenu $varian): RedirectResponse
    {
        $validated = $this->validateBatchRecipe($request, false);
        $varian->loadMissing('menu');
        $count = $this->sync->syncRecipeForVariant($varian, $validated['items']);

        return redirect()
            ->route('admin.resep-menu.index')
            ->with('success', "Komposisi resep {$varian->menu?->nama_menu} - {$varian->nama_varian} berhasil diperbarui ({$count} bahan baku).");
    }

    public function clear(VarianMenu $varian): RedirectResponse
    {
        $varian->loadMissing('menu');
        $this->sync->clearRecipeForVariant($varian);

        return redirect()
            ->route('admin.resep-menu.index')
            ->with('success', "Seluruh bahan resep {$varian->menu?->nama_menu} - {$varian->nama_varian} berhasil dikosongkan.");
    }

    public function edit(int $resepMenu): View
    {
        $resep = ResepMenu::query()
            ->with('varian.menu.kategori', 'varian.resep.bahan')
            ->findOrFail($resepMenu);

        return $this->builderView($resep->varian, true);
    }

    public function update(Request $request, int $resepMenu): RedirectResponse
    {
        $resep = ResepMenu::query()->findOrFail($resepMenu);
        $validated = $this->validateSingleItem($request);

        DB::transaction(function () use ($resep, $validated): void {
            $exists = ResepMenu::query()
                ->where('varian_id', (int) $validated['varian_id'])
                ->where('bahan_id', (int) $validated['bahan_id'])
                ->where('resep_id', '!=', $resep->resep_id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'bahan_id' => 'Bahan baku ini sudah ada di resep varian yang dipilih.',
                ]);
            }

            $resep->update($validated);
        });

        return redirect()
            ->route('admin.resep-menu.index')
            ->with('success', 'Resep Menu berhasil diperbarui.');
    }

    public function destroy(int $resepMenu): RedirectResponse
    {
        $resep = ResepMenu::query()->findOrFail($resepMenu);
        $resep->delete();

        return redirect()
            ->route('admin.resep-menu.index')
            ->with('success', 'Bahan resep berhasil dihapus.');
    }

    private function builderView(?VarianMenu $selectedVariant, bool $isLockedVariant): View
    {
        $variants = VarianMenu::query()
            ->with(['menu.kategori', 'resep.bahan'])
            ->join('menu_produk', 'menu_produk.menu_id', '=', 'varian_menu.menu_id')
            ->select('varian_menu.*')
            ->orderBy('menu_produk.nama_menu')
            ->orderBy('varian_menu.nama_varian')
            ->orderBy('varian_menu.varian_id')
            ->get();

        $variantPayload = $variants->map(function (VarianMenu $variant): array {
            return [
                'varian_id' => (int) $variant->varian_id,
                'label' => ($variant->menu?->nama_menu ?? 'Menu').' - '.$variant->nama_varian,
                'menu_name' => $variant->menu?->nama_menu ?? 'Menu',
                'variant_name' => $variant->nama_varian,
                'category_name' => $variant->menu?->kategori?->nama_kategori ?? '-',
                'selling_price' => (float) ($variant->menu?->harga_jual ?? 0) + (float) $variant->harga_tambahan,
                'existing_items' => $variant->resep->map(fn (ResepMenu $resep): array => [
                    'bahan_id' => (string) $resep->bahan_id,
                    'jumlah_bahan' => $this->formatNumericInput($resep->jumlah_bahan),
                ])->values()->all(),
            ];
        })->values()->all();

        $initialItems = $selectedVariant !== null
            ? $selectedVariant->resep->map(fn (ResepMenu $resep): array => [
                'bahan_id' => (string) $resep->bahan_id,
                'jumlah_bahan' => $this->formatNumericInput($resep->jumlah_bahan),
            ])->values()->all()
            : [];

        return view('admin.resep.builder', [
            'selectedVariant' => $selectedVariant,
            'isLockedVariant' => $isLockedVariant,
            'variants' => $variants,
            'variantPayload' => $variantPayload,
            'ingredients' => $this->sync->ingredientCatalogPayload(),
            'initialItems' => $initialItems,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBatchRecipe(Request $request, bool $requireVariant): array
    {
        $rules = [
            'items' => ['required', 'array', 'min:1'],
            'items.*.bahan_id' => ['required', 'integer', 'distinct', Rule::exists('bahan_baku', 'bahan_id')],
            'items.*.jumlah_bahan' => ['required', 'numeric', 'gt:0', 'max:999999999.999'],
        ];

        if ($requireVariant) {
            $rules['varian_id'] = ['required', 'integer', Rule::exists('varian_menu', 'varian_id')];
        }

        return Validator::make($request->all(), $rules, [
            'varian_id.required' => 'Pilih varian menu terlebih dahulu.',
            'items.required' => 'Tambahkan minimal 1 bahan baku ke dalam resep.',
            'items.min' => 'Tambahkan minimal 1 bahan baku ke dalam resep.',
            'items.*.bahan_id.required' => 'Pilih bahan baku pada setiap baris resep.',
            'items.*.bahan_id.distinct' => 'Bahan baku yang sama tidak boleh dipilih dua kali dalam 1 resep.',
            'items.*.jumlah_bahan.required' => 'Masukkan jumlah takaran bahan baku.',
            'items.*.jumlah_bahan.numeric' => 'Jumlah takaran bahan baku harus berupa angka.',
            'items.*.jumlah_bahan.gt' => 'Takaran bahan baku harus lebih dari 0.',
        ])->validate();
    }

    /**
     * @return array{varian_id: int|string, bahan_id: int|string, jumlah_bahan: float|int|string}
     */
    private function validateSingleItem(Request $request): array
    {
        return Validator::make($request->all(), [
            'varian_id' => ['required', 'integer', Rule::exists('varian_menu', 'varian_id')],
            'bahan_id' => ['required', 'integer', Rule::exists('bahan_baku', 'bahan_id')],
            'jumlah_bahan' => ['required', 'numeric', 'gt:0', 'max:999999999.999'],
        ], [
            'jumlah_bahan.gt' => 'Takaran bahan baku harus lebih dari 0.',
        ])->validate();
    }

    private function formatNumericInput(mixed $value): string
    {
        $formatted = rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
