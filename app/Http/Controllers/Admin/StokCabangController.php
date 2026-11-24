<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\StokCabang;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StokCabangController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $baseQuery = StokCabang::query()->with(['cabang', 'bahan']);
        $queryKeyword = $request->query('q');
        $keyword = is_string($queryKeyword) ? trim($queryKeyword) : '';

        if ($keyword !== '') {
            $baseQuery->whereHas('bahan', function (Builder $builder) use ($keyword): void {
                $builder->whereRaw('LOWER(nama_bahan) LIKE ?', ['%'.mb_strtolower($keyword).'%']);
            });
        }

        $activeFilters = [];
        foreach (['cabang_id', 'bahan_id'] as $filter) {
            $value = $request->query($filter);

            if (is_string($value) && ctype_digit($value)) {
                $baseQuery->where($filter, (int) $value);
                $activeFilters[$filter] = $value;
            }
        }

        $counts = [
            'all' => (clone $baseQuery)->count(),
            'habis' => (clone $baseQuery)->where('jumlah_stok', '<=', 0)->count(),
            'menipis' => (clone $baseQuery)
                ->where('jumlah_stok', '>', 0)
                ->whereColumn('jumlah_stok', '<', 'stok_minimum')
                ->count(),
            'aman' => (clone $baseQuery)
                ->where('jumlah_stok', '>', 0)
                ->whereColumn('jumlah_stok', '>=', 'stok_minimum')
                ->count(),
        ];

        $query = clone $baseQuery;
        $statusParam = $request->query('status');
        if (is_string($statusParam) && in_array($statusParam, ['habis', 'menipis', 'aman', 'perlu_perhatian'], true)) {
            $activeFilters['status'] = $statusParam;

            if ($statusParam === 'habis') {
                $query->where('jumlah_stok', '<=', 0);
            } elseif ($statusParam === 'menipis') {
                $query->where('jumlah_stok', '>', 0)->whereColumn('jumlah_stok', '<', 'stok_minimum');
            } elseif ($statusParam === 'perlu_perhatian') {
                $query->where(function (Builder $builder): void {
                    $builder->where('jumlah_stok', '<=', 0)
                        ->orWhereColumn('jumlah_stok', '<', 'stok_minimum');
                });
            } else {
                $query->where('jumlah_stok', '>', 0)->whereColumn('jumlah_stok', '>=', 'stok_minimum');
            }
        }

        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $validSort = is_string($sort) && in_array($sort, ['jumlah_stok', 'stok_minimum'], true);

        $query->orderBy(
            $validSort ? $sort : 'cabang_id',
            $direction,
        )->orderBy('bahan_id');

        return view('admin.stok.index', [
            'title' => 'Stok Cabang',
            'description' => 'Pantau ketersediaan bahan baku di seluruh cabang dan atur batas minimum pengingat restock.',
            'type' => 'cabang',
            'indexRoute' => 'admin.stok-cabang.index',
            'stocks' => $query->paginate(15)->withQueryString(),
            'locations' => Cabang::query()->orderBy('nama_cabang')->get(),
            'ingredients' => BahanBaku::query()->orderBy('nama_bahan')->get(),
            'filters' => $activeFilters,
            'keyword' => $keyword,
            'sort' => $validSort ? $sort : null,
            'direction' => $direction,
            'counts' => $counts,
            'lowCount' => $counts['habis'] + $counts['menipis'],
            'hasActiveFilters' => $keyword !== '' || $activeFilters !== [] || $validSort,
        ]);
    }

    public function updateMinimum(Request $request, StokCabang $stokCabang): RedirectResponse
    {
        $data = $request->validate([
            'stok_minimum' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        DB::transaction(function () use ($stokCabang, $data): void {
            $stock = StokCabang::query()
                ->whereKey($stokCabang->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $wasLow = (float) $stock->jumlah_stok < (float) $stock->stok_minimum;

            $stock->update($data);

            // Minimum dinaikkan hingga stok berada di bawahnya: beri tahu Manajer dan IC cabang.
            $this->notifications->lowStock($stock, $wasLow, 'min:'.now()->format('YmdHisu'));
        });

        return back()->with('success', 'Batas minimum stok cabang berhasil diperbarui.');
    }
}
