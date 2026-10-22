<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\StokCabang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StokCabangController extends Controller
{
    public function index(Request $request): View
    {
        $query = StokCabang::query()->with(['cabang', 'bahan']);
        $queryKeyword = $request->query('q');
        $keyword = is_string($queryKeyword) ? trim($queryKeyword) : '';

        if ($keyword !== '') {
            $query->whereHas('bahan', function (Builder $builder) use ($keyword): void {
                $builder->whereRaw('LOWER(nama_bahan) LIKE ?', ['%'.mb_strtolower($keyword).'%']);
            });
        }

        foreach (['cabang_id', 'bahan_id'] as $filter) {
            $value = $request->query($filter);

            if (is_string($value) && $value !== '') {
                $query->where($filter, $value);
            }
        }

        if ($request->query('status') === 'menipis') {
            $query->whereColumn('jumlah_stok', '<', 'stok_minimum');
        } elseif ($request->query('status') === 'aman') {
            $query->whereColumn('jumlah_stok', '>=', 'stok_minimum');
        }

        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $query->orderBy(
            in_array($sort, ['jumlah_stok', 'stok_minimum'], true) ? $sort : 'cabang_id',
            $direction,
        )->orderBy('bahan_id');

        return view('admin.stok.index', [
            'title' => 'Stok Cabang',
            'type' => 'cabang',
            'stocks' => $query->paginate(15)->withQueryString(),
            'locations' => Cabang::query()->orderBy('nama_cabang')->get(),
            'ingredients' => BahanBaku::query()->orderBy('nama_bahan')->get(),
            'filters' => $request->only(['cabang_id', 'bahan_id', 'status']),
            'keyword' => $keyword,
            'sort' => $sort,
            'direction' => $direction,
            'lowCount' => StokCabang::query()->whereColumn('jumlah_stok', '<', 'stok_minimum')->count(),
        ]);
    }

    public function updateMinimum(Request $request, StokCabang $stokCabang): RedirectResponse
    {
        $data = $request->validate([
            'stok_minimum' => ['required', 'numeric', 'min:0'],
        ]);

        $stokCabang->update($data);

        return back()->with('success', 'Batas minimum stok cabang berhasil diperbarui.');
    }
}
