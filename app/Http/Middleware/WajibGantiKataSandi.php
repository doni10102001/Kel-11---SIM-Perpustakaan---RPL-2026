<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 8D — Anggota dengan must_change_password = true hanya boleh membuka
 * halaman Ubah Kata Sandi dan mengirim penggantian kata sandi (logout berada di
 * luar grup ini). Semua rute Anggota lain dialihkan ke halaman tersebut (HTML/
 * Inertia) atau ditolak 403 (JSON). Rute Admin tidak disentuh: Anggota yang
 * membuka rute Admin tetap mendapat 403 dari middleware peran. Admin tidak
 * pernah terdampak (flag selalu false untuk Admin).
 */
class WajibGantiKataSandi
{
    /** Rute yang tetap dapat dipakai selama kewajiban berlaku (mencegah loop). */
    public const RUTE_DIIZINKAN = ['anggota.kata-sandi', 'anggota.kata-sandi.update'];

    public const PESAN = 'Anda wajib mengganti kata sandi yang diberikan Admin sebelum menggunakan layanan perpustakaan.';

    public function handle(Request $request, Closure $next): Response
    {
        $akun = $request->user();
        $rute = $request->route()?->getName() ?? '';

        if (! $akun instanceof User
            || ! $akun->wajibGantiKataSandi()
            || ! str_starts_with($rute, 'anggota.')
            || in_array($rute, self::RUTE_DIIZINKAN, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => self::PESAN, 'wajib_ganti_kata_sandi' => true], 403);
        }

        return redirect()->route('anggota.kata-sandi')->with('status', self::PESAN);
    }
}
