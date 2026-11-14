<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 8A — otorisasi peran di server (`peran:admin`, `peran:anggota`).
 * Navigasi Vue yang disembunyikan BUKAN pengaman; rute ini yang menolak.
 * Akun terautentikasi dengan peran lain → 403.
 */
class PastikanPeran
{
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $akun = $request->user();

        if (! $akun instanceof User || ! in_array($akun->role?->value, $peran, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
