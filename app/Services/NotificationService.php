<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\DraftInstruksi;
use App\Models\Notification;
use App\Models\Pengguna;
use App\Models\StokCabang;
use App\Models\SuratJalan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NotificationService
{
    /* =========================================================
       PENERIMA & PENYIMPANAN
    ========================================================= */

    /**
     * Cari penerima aktif berdasarkan role (key pendek: dc, manager, ic).
     * IC dan Manager wajib diberi $cabangId supaya tidak pernah
     * mengirim ke seluruh cabang secara tidak sengaja.
     *
     * @return Collection<int, Pengguna>
     */
    public function recipients(string $roleKey, ?int $cabangId = null, ?int $dcId = null): Collection
    {
        if (! Notification::enabledFor($roleKey)) {
            throw new InvalidArgumentException("Role [{$roleKey}] tidak memiliki fitur notifikasi.");
        }

        if (in_array($roleKey, ['ic', 'manager'], true) && $cabangId === null) {
            throw new InvalidArgumentException("Penerima role [{$roleKey}] wajib menyertakan cabang_id.");
        }

        $query = Pengguna::query()
            ->where('role', config("menu.roles.{$roleKey}.db"))
            ->where('status', 'aktif');

        if ($cabangId !== null) {
            $query->where('cabang_id', $cabangId);
        }

        if ($dcId !== null) {
            $query->where('dc_id', $dcId);
        }

        return $query->get();
    }

    /**
     * Simpan notifikasi untuk beberapa penerima. Idempoten: pasangan
     * (user_id, dedupe_key) yang sudah ada dilewati oleh database.
     *
     * Panggil di dalam DB::transaction() yang sama dengan penyimpanan
     * data bisnis supaya keduanya sukses atau gagal bersama.
     *
     * @param  iterable<Pengguna>  $recipients
     * @return int jumlah notifikasi yang benar-benar dibuat
     */
    public function send(
        iterable $recipients,
        string $type,
        string $title,
        string $description,
        string $referenceType,
        int $referenceId,
        string $dedupeKey,
        ?int $cabangId = null,
        ?int $dcId = null,
    ): int {
        $now = now();
        $rows = [];

        foreach ($recipients as $user) {
            // Jenis notifikasi harus sesuai dengan role penerima.
            if (! in_array($type, Notification::ROLE_TYPES[$user->role_key] ?? [], true)) {
                continue;
            }

            $rows[] = [
                'user_id'        => $user->user_id,
                'cabang_id'      => $cabangId,
                'dc_id'          => $dcId,
                'type'           => $type,
                'title'          => $title,
                'description'    => $description,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'dedupe_key'     => $dedupeKey,
                'is_read'        => false,
                'read_at'        => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        return DB::table('notifications')->insertOrIgnore($rows);
    }

    /* =========================================================
       EVENT BISNIS
       Semua method ini dipanggil dari dalam DB::transaction()
       milik controller yang memproses aktivitasnya.
    ========================================================= */

    /**
     * Draft restock otomatis baru dibuat dan menunggu review Manager.
     */
    public function approvalNeeded(DraftInstruksi $draft, int $itemCount): int
    {
        $draft->loadMissing('cabang');

        $code = $this->draftCode($draft);
        $branch = $draft->cabang?->nama_cabang ?? 'cabang';

        return $this->send(
            $this->recipients('manager', (int) $draft->cabang_id),
            'approval',
            "Draft restock {$code} menunggu persetujuan",
            "Draft {$code} untuk {$branch} ({$itemCount} jenis bahan) perlu direview dan disetujui.",
            'draft_instruksi',
            (int) $draft->draft_id,
            'approval:draft:'.$draft->draft_id,
            (int) $draft->cabang_id,
        );
    }

    /**
     * Manager menyetujui draft: DC menerima pesanan masuk.
     * Draft tidak punya dc_id, jadi semua akun DC aktif menerimanya
     * (sama seperti halaman Pesanan Masuk yang menampilkan semua draft disetujui).
     */
    public function orderApproved(DraftInstruksi $draft, int $itemCount): int
    {
        $draft->loadMissing('cabang');

        $code = $this->draftCode($draft);
        $branch = $draft->cabang?->nama_cabang ?? 'cabang';

        return $this->send(
            $this->recipients('dc'),
            'pesanan_masuk',
            "Pesanan masuk {$code}",
            "Pesanan {$code} dari {$branch} telah disetujui Manager ({$itemCount} jenis bahan) dan siap diproses.",
            'draft_instruksi',
            (int) $draft->draft_id,
            'pesanan_masuk:draft:'.$draft->draft_id,
            (int) $draft->cabang_id,
        );
    }

    /**
     * DC mengirim surat jalan: IC cabang tujuan diminta mengecek dan mengonfirmasi.
     */
    public function shipmentDispatched(SuratJalan $sj): int
    {
        $sj->loadMissing('detail.bahan', 'dc');

        $code = $this->shipmentCode($sj);
        $origin = $sj->dc?->nama_dc ?? 'Distribution Center';

        $names = $sj->detail->map(fn ($d) => $d->bahan?->nama_bahan)->filter()->values();
        $rest = $names->count() - 3;
        $items = $names->isEmpty()
            ? ''
            : ' Isi: '.$names->take(3)->implode(', ').($rest > 0 ? " (+{$rest} lainnya)" : '').'.';

        return $this->send(
            $this->recipients('ic', (int) $sj->cabang_id),
            'surat_jalan',
            "Pengiriman barang {$code}",
            "{$code} dari {$origin} sedang dikirim ke cabang Anda.{$items} Cek barang saat tiba lalu konfirmasi penerimaan.",
            'surat_jalan',
            (int) $sj->surat_jalan_id,
            'surat_jalan:dikirim:'.$sj->surat_jalan_id,
            (int) $sj->cabang_id,
            (int) $sj->dc_id,
        );
    }

    /**
     * IC sudah mengonfirmasi penerimaan: DC pemilik surat jalan diberi tahu.
     */
    public function shipmentReceived(SuratJalan $sj, bool $hasDifference): int
    {
        $sj->loadMissing('cabang');

        $code = $this->shipmentCode($sj);
        $branch = $sj->cabang?->nama_cabang ?? 'cabang tujuan';
        $note = $hasDifference
            ? ' Ada selisih antara jumlah dikirim dan jumlah diterima.'
            : ' Jumlah diterima sesuai.';

        return $this->send(
            $this->recipients('dc', null, (int) $sj->dc_id),
            'surat_jalan',
            "Surat Jalan {$code} diterima",
            "{$code} telah dikonfirmasi diterima oleh {$branch}.{$note}",
            'surat_jalan',
            (int) $sj->surat_jalan_id,
            'surat_jalan:diterima:'.$sj->surat_jalan_id,
            (int) $sj->cabang_id,
            (int) $sj->dc_id,
        );
    }

    /**
     * Stok cabang BARU SAJA turun di bawah batas minimum.
     *
     * $wasLow harus dihitung SEBELUM stok berubah. Jika stok sudah rendah
     * sebelumnya, tidak ada notifikasi baru (tidak berulang untuk kondisi yang sama).
     * $eventRef adalah referensi unik peristiwanya (mis. 'trx:12', 'waste:uuid').
     */
    public function lowStock(StokCabang $stok, bool $wasLow, string $eventRef): int
    {
        $quantity = (float) $stok->jumlah_stok;
        $minimum = (float) $stok->stok_minimum;

        if ($wasLow || $quantity >= $minimum) {
            return 0;
        }

        $stok->loadMissing('bahan', 'cabang');

        $name = $stok->bahan?->nama_bahan ?? 'Bahan';
        $unit = $stok->bahan?->satuan ?? '';
        $branch = $stok->cabang?->nama_cabang ?? 'cabang';
        $cabangId = (int) $stok->cabang_id;
        $dedupe = 'stok_menipis:'.$stok->stok_cabang_id.':'.$eventRef;

        $now = $this->num($quantity);
        $min = $this->num($minimum);
        $need = $this->num($minimum - $quantity);

        $sent = $this->send(
            $this->recipients('manager', $cabangId),
            'restock',
            "Alert restock: {$name}",
            "Stok {$name} di {$branch} tinggal {$now} {$unit}, di bawah batas minimum {$min} {$unit}. Kebutuhan restock minimal {$need} {$unit}.",
            'stok_cabang',
            (int) $stok->stok_cabang_id,
            $dedupe,
            $cabangId,
        );

        $sent += $this->send(
            $this->recipients('ic', $cabangId),
            'stok_menipis',
            "Stok menipis: {$name}",
            "Stok {$name} tinggal {$now} {$unit}, di bawah batas minimum {$min} {$unit}.",
            'stok_cabang',
            (int) $stok->stok_cabang_id,
            $dedupe,
            $cabangId,
        );

        return $sent;
    }

    /**
     * IC mencatat waste: Manager cabang diberi tahu (satu notifikasi per laporan).
     *
     * @param  array<int, string>  $lines  mis. ['Susu 700 ml', 'Gula 50 g']
     */
    public function wasteReported(string $kelompokId, int $wasteId, int $cabangId, array $lines, string $reason): int
    {
        $branch = Cabang::query()->find($cabangId)?->nama_cabang ?? 'cabang';

        $rest = count($lines) - 3;
        $summary = implode(', ', array_slice($lines, 0, 3)).($rest > 0 ? " (+{$rest} lainnya)" : '');

        return $this->send(
            $this->recipients('manager', $cabangId),
            'waste',
            'Alert waste baru',
            "{$summary} — alasan: {$reason}. Dicatat di {$branch}.",
            'waste_log',
            $wasteId,
            'waste:'.$kelompokId,
            $cabangId,
        );
    }

    /* =========================================================
       HELPER
    ========================================================= */

    private function draftCode(DraftInstruksi $draft): string
    {
        return sprintf('#RS-%04d', $draft->draft_id);
    }

    private function shipmentCode(SuratJalan $sj): string
    {
        return sprintf('#SJ-%03d', $sj->surat_jalan_id);
    }

    private function num(float $value): string
    {
        $text = number_format($value, 2, ',', '.');

        return str_contains($text, ',') ? rtrim(rtrim($text, '0'), ',') : $text;
    }
}