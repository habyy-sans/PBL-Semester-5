<?php

use Illuminate\Support\Facades\Route;

// Data untuk halaman "segera hadir"
$soon = fn (string $title, string $description) => [
    'title'       => $title,
    'description' => $description,
];


/* =========================================================
   LOGIN
========================================================= */

Route::redirect('/', '/login');

Route::view('/login', 'auth.login.index')->name('login');


/* =========================================================
   KASIR
========================================================= */

Route::prefix('kasir')->name('kasir.')->group(function () use ($soon) {

    Route::view('/pos', 'kasir.pos.index')->name('pos');

    Route::view('/history', 'kasir.riwayat-transaksi.index')->name('history');

});


/* =========================================================
   KEPALA SHIFT / IC
========================================================= */

Route::prefix('ic')->name('ic.')->group(function () use ($soon) {

    Route::view('/stock', 'ic.stock.index')->name('stock');

    Route::view('/receiving', 'ic.PenerimaanBarang.index')->name('receiving');

    Route::view('/waste', 'ic.Waste.index')->name('waste');

    Route::view('/mutation', 'ic.mutasi.index')->name('mutation');

});


/* =========================================================
   MANAJER CABANG
========================================================= */

Route::prefix('manager')->name('manager.')->group(function () use ($soon) {

    Route::view('/dashboard', 'manager.dashboard.index')->name('dashboard');

    Route::view('/alert', 'manager.alert.index')->name('alert');

    Route::view('/restock', 'manager.restock.index')->name('restock');

    Route::view('/report', 'manager.reports.index')->name('report');

});


/* =========================================================
   DC EKSTERNAL
========================================================= */

Route::prefix('dc')->name('dc.')->group(function () use ($soon) {

    Route::view('/stock', 'dc.inventory.index')->name('stock');

    Route::view('/order', 'dc.pesanan.index')->name('order');

    Route::view('/shipment', 'dc.pengiriman.index')->name('shipment');

});


/* =========================================================
   ADMIN SISTEM
========================================================= */

Route::prefix('admin')->name('admin.')->group(function () use ($soon) {

    Route::view('/report', 'admin.laporan.index')->name('report');

    Route::view('/user', 'admin.kelola.index')->name('user');

    Route::view('/product', 'placeholder', $soon(
        'Produk & Kategori',
        'Kelola data produk dan kategori.'
    ))->name('product');

    Route::view('/price', 'placeholder', $soon(
        'Kelola Harga',
        'Atur harga produk.'
    ))->name('price');

});