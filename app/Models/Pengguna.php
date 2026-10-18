<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable([
    'nama_lengkap',
    'username',
    'email',
    'password_hash',
    'telepon',
    'role',
    'cabang_id',
    'dc_id',
    'status',
])]
#[Hidden(['password_hash', 'remember_token'])]
class Pengguna extends Authenticatable
{
    protected $table = 'pengguna';

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    /**
     * Get the attribute that stores the user's password.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function dc(): BelongsTo
    {
        return $this->belongsTo(Dc::class, 'dc_id');
    }

    /**
     * Role pendek ('kasir', 'ic', 'manager', 'dc', 'admin') yang dipakai
     * config/menu.php, dipetakan dari nilai role panjang di database.
     */
    public function getRoleKeyAttribute(): ?string
    {
        foreach (config('menu.roles') as $key => $meta) {
            if (($meta['db'] ?? null) === $this->role) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Nama route halaman awal sesuai role.
     */
    public function getHomeRouteAttribute(): string
    {
        return config("menu.roles.{$this->role_key}.home", 'login');
    }
}
