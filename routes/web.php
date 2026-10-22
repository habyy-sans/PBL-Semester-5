<?php

use App\Http\Controllers\Admin\BahanBakuController as AdminBahanBakuController;
use App\Http\Controllers\Admin\CabangController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DcController;
use App\Http\Controllers\Admin\KategoriMenuController;
use App\Http\Controllers\Admin\MenuProdukController;
use App\Http\Controllers\Admin\PenggunaController;
use App\Http\Controllers\Admin\ResepMenuController;
use App\Http\Controllers\Admin\StokCabangController;
use App\Http\Controllers\Admin\StokDcController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VarianMenuController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Dc\BahanBakuController;
use App\Http\Controllers\Dc\InventoryController;
use App\Http\Controllers\Dc\OrderController;
use App\Http\Controllers\Dc\ShipmentController;
use App\Http\Controllers\Ic\ReceivingController;
use App\Http\Controllers\Ic\StockController;
use App\Http\Controllers\Ic\WasteController;
use App\Http\Controllers\Kasir\PosController;
use App\Http\Controllers\Kasir\RiwayatController;
use App\Http\Controllers\Manager\DashboardController;
use App\Http\Controllers\Manager\RestockController;
use Illuminate\Support\Facades\Route;

// Data untuk halaman "segera hadir"
$soon = fn (string $title, string $description) => [
    'title' => $title,
    'description' => $description,
];

/* =========================================================
   LOGIN
========================================================= */

Route::get('/', function () {
    $user = auth()->user();

    return $user
        ? redirect()->route($user->home_route)
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

/* =========================================================
   KASIR
========================================================= */

Route::middleware(['auth', 'role:kasir'])->prefix('kasir')->name('kasir.')->group(function () {

    Route::get('/pos', [PosController::class, 'index'])->name('pos');
    Route::post('/pos', [PosController::class, 'store']);

    Route::get('/history', [RiwayatController::class, 'index'])->name('history');

});

/* =========================================================
   KEPALA SHIFT / IC
========================================================= */

Route::middleware(['auth', 'role:ic'])->prefix('ic')->name('ic.')->group(function () {

    Route::get('/stock', [StockController::class, 'index'])->name('stock');

    Route::get('/receiving', [ReceivingController::class, 'index'])->name('receiving');
    Route::post('/receiving/{id}/confirm', [ReceivingController::class, 'confirm'])->name('receiving.confirm');

    Route::view('/waste', 'ic.Waste.index')->name('waste');
    Route::get('/waste/items', [WasteController::class, 'items'])->name('waste.items');
    Route::get('/waste/menus', [WasteController::class, 'menus'])->name('waste.menus');
    Route::post('/waste', [WasteController::class, 'store'])->name('waste.store');

    Route::view('/mutation', 'ic.mutasi.index')->name('mutation');

});

/* =========================================================
   MANAJER CABANG
========================================================= */

Route::middleware(['auth', 'role:manager'])->prefix('manager')->name('manager.')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/alert', [DashboardController::class, 'alert'])->name('alert');

    Route::view('/restock', 'manager.restock.index')->name('restock');
    Route::get('/restock/draft', [RestockController::class, 'index'])->name('restock.draft');
    Route::post('/restock/approve', [RestockController::class, 'approve'])->name('restock.approve');

    Route::view('/report', 'manager.reports.index')->name('report');

});

/* =========================================================
   DC EKSTERNAL
========================================================= */

Route::middleware(['auth', 'role:dc'])->prefix('dc')->name('dc.')->group(function () {

    Route::get('/stock', [InventoryController::class, 'index'])->name('stock');

    Route::get('/order', [OrderController::class, 'index'])->name('order');

    Route::get('/shipment', [ShipmentController::class, 'index'])->name('shipment');
    Route::post('/shipment', [ShipmentController::class, 'store'])->name('shipment.store');
    Route::post('/shipment/{id}/kirim', [ShipmentController::class, 'kirim'])->name('shipment.kirim');

    Route::get('/bahan-baku', [BahanBakuController::class, 'index'])
        ->name('bahan-baku.index');

    Route::post('/bahan-baku', [BahanBakuController::class, 'store'])
        ->name('bahan-baku.store');

    Route::put('/bahan-baku/{bahanBaku}', [BahanBakuController::class, 'update'])
        ->name('bahan-baku.update');

    Route::post('/bahan-baku/{bahanBaku}/tambah-stok', [BahanBakuController::class, 'addStock'])
        ->name('bahan-baku.add-stock');

});

/* =========================================================
   ADMIN SISTEM
========================================================= */

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () use ($soon) {

    Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('index');
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::view('/report', 'admin.laporan.index')->name('report');

    Route::resource('pengguna', PenggunaController::class)->except('show');
    Route::patch('/pengguna/{pengguna}/status', [PenggunaController::class, 'updateStatus'])->name('pengguna.status');
    Route::patch('/pengguna/{pengguna}/password', [PenggunaController::class, 'resetPassword'])->name('pengguna.password');

    Route::resource('cabang', CabangController::class)->except('show');
    Route::resource('dc', DcController::class)->except('show');
    Route::resource('bahan-baku', AdminBahanBakuController::class)->except('show');
    Route::resource('kategori-menu', KategoriMenuController::class)->except('show');
    Route::resource('menu-produk', MenuProdukController::class)->except('show');
    Route::resource('varian-menu', VarianMenuController::class)->except('show');
    Route::resource('resep-menu', ResepMenuController::class)->except('show');
    Route::get('/stok-cabang', [StokCabangController::class, 'index'])->name('stok-cabang.index');
    Route::patch('/stok-cabang/{stokCabang}/minimum', [StokCabangController::class, 'updateMinimum'])->name('stok-cabang.minimum');
    Route::get('/stok-dc', [StokDcController::class, 'index'])->name('stok-dc.index');
    Route::patch('/stok-dc/{stokDc}/minimum', [StokDcController::class, 'updateMinimum'])->name('stok-dc.minimum');

    Route::get('/user', [UserController::class, 'index'])->name('user');
    Route::get('/user/data', [UserController::class, 'users'])->name('user.data');
    Route::post('/user', [UserController::class, 'store'])->name('user.store');
    Route::put('/user/{user}', [UserController::class, 'update'])->name('user.update');
    Route::patch('/user/{user}/status', [UserController::class, 'updateStatus'])->name('user.status');

    Route::view('/role', 'admin.roles.index')->name('role');

    Route::view('/product', 'placeholder', $soon(
        'Produk & Kategori',
        'Kelola data produk dan kategori.'
    ))->name('product');

    Route::view('/price', 'placeholder', $soon(
        'Kelola Harga',
        'Atur harga produk.'
    ))->name('price');

});
