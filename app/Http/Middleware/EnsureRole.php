<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Tolak request jika role user yang login tidak termasuk dalam daftar
     * role yang diizinkan. Contoh pemakaian: ->middleware('role:kasir').
     *
     * @param  string  ...$roles  role pendek di config/menu.php (kasir, ic, manager, dc, admin)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 403);
        abort_unless(in_array($user->role_key, $roles, true), 403, 'Anda tidak memiliki akses ke halaman ini.');

        return $next($request);
    }
}
