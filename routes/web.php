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

    Route::view('/alert', 'placeholder', $soon(
        'Alert',
        'Alert restock dan alert waste yang perlu perhatian.'
    ))->name('alert');

    Route::view('/restock', 'manager.restock.index')->name('restock');

    Route::view('/report', 'manager.reports.index')->name('report');

});


/* =========================================================
   DC EKSTERNAL
========================================================= */

Route::prefix('dc')->name('dc.')->group(function () use ($soon) {

    Route::view('/stock', 'dc.inventory.index')->name('stock');

    Route::view('/order', 'placeholder', $soon(
        'Pesanan Masuk',
        'Pesanan yang sudah disetujui Manajer dan siap diproses.'
    ))->name('order');

    Route::view('/shipment', 'placeholder', $soon(
        'Pengiriman & Surat Jalan',
        'Proses pengiriman, Surat Jalan digital, dan status pengiriman.'
    ))->name('shipment');

});


/* =========================================================
   ADMIN SISTEM
========================================================= */

Route::prefix('admin')->name('admin.')->group(function () use ($soon) {

    Route::view('/report', 'placeholder', $soon(
        'Laporan Seluruh Cabang',
        'Laporan mutasi stok dan laba rugi seluruh cabang.'
    ))->name('report');

    Route::view('/user', 'admin.index')->name('user');

    Route::view('/role', 'placeholder', $soon(
        'Role & Hak Akses',
        'Atur role dan hak akses pengguna.'
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