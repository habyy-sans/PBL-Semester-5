<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $counts = Pengguna::query()
            ->selectRaw('role, status, COUNT(*) as total')
            ->groupBy('role', 'status')
            ->get();

        $descriptions = [
            'kasir' => 'Melayani transaksi pemesanan pelanggan di kasir POS, menerima pembayaran, dan mencetak struk.',
            'ic' => 'Mengawasi stok bahan baku gerai, memverifikasi penerimaan barang dari DC, dan mencatat waste log.',
            'manager' => 'Memantau kinerja gerai, mengawasi batas aman persediaan, dan menyetujui pengajuan restock cabang.',
            'dc' => 'Mengelola stok gudang pusat Distribution Center, memproses pesanan restock cabang, dan menerbitkan surat jalan.',
            'admin' => 'Mengelola struktur organisasi (cabang dan DC), data master global, batas stok minimum, serta akun pengguna.',
        ];

        $scopes = [
            'admin' => 'Seluruh sistem',
            'dc' => '1 Distribution Center',
            'manager' => '1 Cabang operasional',
            'ic' => '1 Cabang operasional',
            'kasir' => '1 Cabang operasional',
        ];

        $roles = collect(config('menu.roles'))->map(function (array $meta, string $key) use ($counts, $descriptions, $scopes): array {
            $dbRole = $meta['db'];
            $roleCounts = $counts->where('role', $dbRole);
            $active = (int) ($roleCounts->firstWhere('status', 'aktif')?->total ?? 0);
            $inactive = (int) ($roleCounts->firstWhere('status', 'nonaktif')?->total ?? 0);

            $modules = [];
            foreach (config("menu.menus.{$key}", []) as $items) {
                foreach ($items as $item) {
                    $modules[] = $item['label'];
                }
            }

            return [
                'key' => $key,
                'label' => $meta['label'],
                'db' => $dbRole,
                'scope' => $scopes[$key] ?? 'Terbatas',
                'description' => $descriptions[$key] ?? 'Hak akses operasional KopSync.',
                'active_count' => $active,
                'inactive_count' => $inactive,
                'total_count' => $active + $inactive,
                'modules' => $modules,
                'users_url' => route('admin.pengguna.index', ['role' => $key]),
                'create_url' => route('admin.pengguna.create', ['role_key' => $key]),
            ];
        })->values()->all();

        return view('admin.roles.index', [
            'roles' => $roles,
            'totalUsers' => Pengguna::query()->count(),
        ]);
    }
}
