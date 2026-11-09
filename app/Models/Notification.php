<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'cabang_id',
    'dc_id',
    'type',
    'title',
    'description',
    'reference_type',
    'reference_id',
    'dedupe_key',
    'is_read',
    'read_at',
])]
class Notification extends Model
{
    /**
     * Jenis notifikasi yang boleh diterima tiap role.
     * Role yang tidak ada di sini (admin, kasir) tidak punya fitur notifikasi.
     */
    public const ROLE_TYPES = [
        'dc'      => ['pesanan_masuk', 'surat_jalan'],
        'manager' => ['restock', 'waste', 'approval'],
        'ic'      => ['surat_jalan', 'stok_menipis'],
    ];

    /**
     * Halaman tujuan per role dan jenis. Nilainya nama route existing.
     * Dipakai server saat notifikasi diklik, bukan URL dari database.
     */
    public const ROLE_DESTINATIONS = [
        'dc' => [
            'pesanan_masuk' => 'dc.order',
            'surat_jalan'   => 'dc.shipment',
        ],
        'manager' => [
            'restock'  => 'manager.alert',
            'waste'    => 'manager.alert',
            'approval' => 'manager.restock',
        ],
        'ic' => [
            'surat_jalan'  => 'ic.receiving',
            'stok_menipis' => 'ic.stock',
        ],
    ];

    protected $table = 'notifications';

    protected $primaryKey = 'notification_id';

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'is_read'    => 'boolean',
            'read_at'    => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function enabledFor(?string $role): bool
    {
        return $role !== null && array_key_exists($role, self::ROLE_TYPES);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'user_id', 'user_id');
    }

    /**
     * Satu-satunya pintu untuk membaca notifikasi milik user yang login.
     * Dipakai oleh daftar, badge, tandai-baca, dan tandai-semua supaya
     * hasilnya selalu sinkron.
     *
     * - hanya milik user tersebut
     * - hanya jenis yang boleh diterima role-nya
     * - IC & Manager: hanya cabangnya (tanpa cabang = tidak melihat apa pun)
     * - DC: hanya DC-nya, atau notifikasi tanpa dc_id
     */
    public function scopeVisibleTo(Builder $query, Pengguna $user): Builder
    {
        $role = $user->role_key;

        $query->where('user_id', $user->user_id)
            ->whereIn('type', self::ROLE_TYPES[$role] ?? []);

        if (in_array($role, ['ic', 'manager'], true)) {
            return $user->cabang_id === null
                ? $query->whereRaw('1 = 0')
                : $query->where('cabang_id', $user->cabang_id);
        }

        if ($role === 'dc') {
            return $user->dc_id === null
                ? $query->whereNull('dc_id')
                : $query->where(function (Builder $q) use ($user) {
                    $q->whereNull('dc_id')->orWhere('dc_id', $user->dc_id);
                });
        }

        return $query->whereRaw('1 = 0');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->where('is_read', true);
    }

    /**
     * URL halaman tujuan (relatif) sesuai role penerima, atau null bila tidak ada pemetaan.
     */
    public function destinationUrl(?string $role): ?string
    {
        $route = self::ROLE_DESTINATIONS[$role ?? ''][$this->type] ?? null;

        return $route ? route($route, [], false) : null;
    }
}