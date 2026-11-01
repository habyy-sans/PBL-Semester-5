# Glosarium Domain KopSync

KopSync adalah sistem sinkronisasi point-of-sale dan persediaan kedai kopi multi-cabang yang menghubungkan gerai ritel dengan gudang logistik terpusat.

## Peran

**Admin Sistem**:
Pengelola sistem terpusat yang berwenang mengatur struktur organisasi, data master global, serta manajemen akun pengguna.
_Hindari_: Superuser, IT Officer, Superadmin

**Manajer Cabang**:
Pemegang wewenang manajerial di tingkat cabang yang mengawasi kinerja gerai, memantau batas aman persediaan, dan menyetujui pengajuan restock.
_Hindari_: Store Manager, Supervisor Cabang

**Kepala Shift (IC)**:
Pengawas operasional di gerai yang bertanggung jawab atas perhitungan fisik persediaan, pencatatan pembuangan bahan rusak, dan verifikasi penerimaan barang dari gudang pusat.
_Hindari_: Penjaga Gudang Toko, Barista Senior

**Kasir**:
Staf lini depan gerai yang bertugas melayani transaksi pemesanan pelanggan di kasir dan mencetak bukti pembayaran.
_Hindari_: Barista Kasir, Operator Toko

**Distribution Center (DC)**:
Penanggung jawab fasilitas pergudangan logistik pusat yang bertugas menyimpan persediaan utama, memproses pesanan restock dari cabang, dan mengirimkan barang.
_Hindari_: Supplier, Vendor Luar, DC Eksternal

## Katalog & Persediaan

**Bahan Baku**:
Bahan dasar atau kemasan yang dicatat dalam persediaan dan digunakan dalam formula racikan menu.
_Hindari_: Item, Komoditas, Raw Material

**Resep Menu**:
Komposisi dan takaran baku bahan baku yang dibutuhkan untuk memproduksi satu varian produk jadi.
_Hindari_: BOM, Formula Racikan, Takaran

**Menu Produk**:
Produk minuman atau makanan jadi yang siap dipesan dan dijual kepada pelanggan melalui aplikasi kasir.
_Hindari_: Barang Jual, Komoditi

**Stok Cabang**:
Kuantitas fisik bahan baku yang tercatat dan tersimpan di dalam satu gerai cabang tertentu.
_Hindari_: Stok Toko, Inventory Cabang

**Stok Gudang DC**:
Kuantitas fisik persediaan bahan baku yang disimpan di fasilitas pergudangan pusat Distribution Center.
_Hindari_: Central Stock, Stok Master

## Operasional & Pemenuhan

**Order Restock**:
Permintaan penambahan persediaan gerai ke Distribution Center yang telah mendapatkan persetujuan manajerial resmi.
_Hindari_: Draft Instruksi, PO Pembelian, Nota Minta

**Pengiriman (Shipment)**:
Pemberangkatan fisik barang dari Distribution Center menuju cabang tujuan yang disertai dokumen surat jalan resmi.
_Hindari_: Dispatch, Ekspedisi

**Penerimaan Barang**:
Pemeriksaan fisik dan validasi barang yang tiba di cabang berdasarkan dokumen surat jalan pengiriman.
_Hindari_: Receiving, Bongkar Muatan

**Pencatatan Waste**:
Dokumentasi pemusnahan atau pengurangan persediaan gerai akibat kerusakan, kedaluwarsa, atau tumpah.
_Hindari_: Barang Basi, Retur Rusak
