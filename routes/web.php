<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Dc\InventoryController;
use App\Http\Controllers\Ic\StockController;
use App\Http\Controllers\Kasir\PosController;
use App\Http\Controllers\Kasir\RiwayatController;
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

    Route::view('/receiving', 'ic.PenerimaanBarang.index')->name('receiving');

    Route::view('/waste', 'ic.Waste.index')->name('waste');

    Route::view('/mutation', 'ic.mutasi.index')->name('mutation');

});

/* =========================================================
   MANAJER CABANG
========================================================= */

Route::middleware(['auth', 'role:manager'])->prefix('manager')->name('manager.')->group(function () {

    Route::view('/dashboard', 'manager.dashboard.index')->name('dashboard');

    Route::view('/alert', 'manager.alert.index')->name('alert');

    Route::view('/restock', 'manager.restock.index')->name('restock');

    Route::view('/report', 'manager.reports.index')->name('report');

});

/* =========================================================
   DC EKSTERNAL
========================================================= */

Route::middleware(['auth', 'role:dc'])->prefix('dc')->name('dc.')->group(function () {

    Route::get('/stock', [InventoryController::class, 'index'])->name('stock');

    Route::view('/order', 'dc.pesanan.index')->name('order');

    Route::view('/shipment', 'dc.pengiriman.index')->name('shipment');

});

/* =========================================================
   ADMIN SISTEM
========================================================= */

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () use ($soon) {

    Route::view('/report', 'admin.laporan.index')->name('report');

    Route::view('/user', 'admin.kelola.index')->name('user');

    Route::view('/role', 'placeholder', $soon(
        'Role & Hak Akses',
        'Kelola role dan hak akses pengguna.'
    ))->name('role');

    Route::view('/product', 'placeholder', $soon(
        'Produk & Kategori',
        'Kelola data produk dan kategori.'
    ))->name('product');

    Route::view('/price', 'placeholder', $soon(
        'Kelola Harga',
        'Atur harga produk.'
    ))->name('price');

});
