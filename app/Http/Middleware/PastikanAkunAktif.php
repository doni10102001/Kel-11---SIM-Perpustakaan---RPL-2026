<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 8A — kelayakan akun diperiksa ulang dari database pada SETIAP request
 * terautentikasi, bukan hanya saat login. Bila Admin menonaktifkan Anggota
 * (members.status) atau akun Admin (users.is_active), sesi lama ditolak pada
 * request berikutnya: logout, sesi di-invalidate, token CSRF diganti.
 */
class PastikanAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $akun = $request->user();

        if (! $akun instanceof User) {
            return $next($request);
        }

        // Data segar (bukan objek yang tersimpan di guard), termasuk Status Anggota.
        $segar = $akun->fresh(['member']);
        $alasan = $segar?->alasanTidakBolehMasuk();

        if ($segar !== null && $alasan === null) {
            Auth::guard('web')->setUser($segar);

            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $pesan = $alasan?->pesan() ?? 'Sesi Anda telah berakhir. Silakan masuk kembali.';

        return $request->expectsJson()
            ? response()->json(['message' => $pesan], 401)
            : redirect()->route('login')->with('status', $pesan);
    }
}
