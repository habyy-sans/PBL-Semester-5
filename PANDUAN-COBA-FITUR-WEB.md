# Panduan Mencoba Fitur Web KopSync

## 1. Buka aplikasi

Server Laravel sudah dijalankan di:

[http://localhost:8000/login](http://localhost:8000/login)

Jika server berhenti, jalankan dari direktori proyek:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

## 2. Masuk dengan akun demo

Seeder demo menetapkan password yang sama untuk akun-akun demo: `password123`. Akun hanya bisa dipakai jika sudah ada di database yang terhubung.

| Peran | Email | Username |
|---|---|---|
| Kasir | `andi.kasir1@kopsync.id` | `andi.kasir1` |
| Kepala Shift (IC) | `nia.ic1@kopsync.id` | `nia.ic1` |
| Manajer | `dewi.malang1@kopsync.id` | `dewi.malang1` |
| Distribution Center (DC) | `budi.dc@kopsync.id` | `budi.dc` |
| Admin | `admin@kopsync.id` | `admin` |

Pilih satu akun dan isi email atau username beserta password di halaman login. Pengguna nonaktif seperti `lia.kasir2` memang tidak dapat masuk.

## 3. Coba alur POS sebagai Kasir

1. Masuk sebagai Kasir. Halaman POS ada di `/kasir/pos`.
2. Cari menu atau pilih kategori, lalu klik kartu produk untuk menambahkannya ke pesanan.
3. Gunakan tombol `+`/`−` untuk mengubah jumlah. Klik `SOP` untuk melihat resep dan estimasi biaya.
4. Pilih metode pembayaran. Untuk Tunai, isi uang diterima atau pilih nominal cepat; nilai harus minimal sama dengan total.
5. Klik `Bayar`, periksa ringkasan, lalu pilih `Proses Transaksi`.
6. Jika berhasil, halaman menampilkan nomor transaksi dan bahan yang terpotong. Buka `/kasir/history` untuk melihat riwayat transaksi.
7. Masuk sebagai IC cabang yang sama dan buka `/ic/stock` untuk memeriksa perubahan stok.

QRIS dan Debit di POS adalah simulasi: aplikasi menandai pembayaran berhasil tanpa memanggil Midtrans.

## 4. Coba tampilan stok

- **IC:** `/ic/stock` menampilkan stok cabang akun, dengan pencarian serta filter kategori/status.
- **DC:** `/dc/stock` menampilkan ringkasan stok cabang.
- **Manager:** `/manager/dashboard`, `/manager/alert`, `/manager/restock`, `/manager/report`.
- **Admin:** `/admin/report`, `/admin/user`.

Halaman Manager, fitur penerimaan/waste/mutasi IC, order/pengiriman DC, dan beberapa halaman Admin saat ini terutama berupa tampilan. Jangan menganggap aksi pada halaman tersebut sudah menyimpan perubahan bisnis ke database.

## 5. Data demo dan keamanan

Transaksi POS menyimpan transaksi/pembayaran dan mengurangi stok cabang. Coba hanya pada database development/sandbox, bukan database produksi atau data operasional.

Jika akun demo belum tersedia, seeder proyek dapat membuat/memperbarui akun dan data master. Pastikan koneksi database pada environment lokal menunjuk ke sandbox dan tabel domain sudah dibuat sebelum menjalankan:

```powershell
php artisan db:seed --class=DatabaseSeeder
```

Perintah tersebut menulis data ke database yang dikonfigurasi. Jangan menjalankan `migrate:fresh`; migrasi Laravel yang ada tidak membuat skema domain Supabase.

## 6. Jika login tidak berhasil

- Pastikan database development dapat dijangkau dan tabel domain tersedia.
- Periksa log aplikasi di `storage/logs/laravel.log` untuk detail error; jangan membagikan nilai rahasia dari `.env`.
- Jika baru mengubah konfigurasi lokal, jalankan `php artisan config:clear`, lalu muat ulang halaman login.
- Jika fitur POS tidak menampilkan menu atau stok, pastikan data bahan, menu, resep, dan stok sudah ada di database sandbox.

Server yang berjalan dapat dihentikan dengan `Ctrl+C` pada terminal tempat `php artisan serve` dijalankan.
