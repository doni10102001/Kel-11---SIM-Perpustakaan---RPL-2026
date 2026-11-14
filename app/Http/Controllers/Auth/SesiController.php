<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MasukRequest;
use App\Support\NavigasiDemo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 8A — masuk dan keluar dengan sesi Laravel (guard `web`, sesi server).
 * Tidak ada registrasi mandiri dan tidak ada "Ingat saya" (D-05).
 */
class SesiController extends Controller
{
    public const PESAN_KELUAR = 'Anda telah keluar dari aplikasi.';

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(MasukRequest $request): RedirectResponse
    {
        $akun = $request->autentikasi();

        Auth::guard('web')->login($akun);
        // Cegah session fixation: ID sesi baru setelah masuk.
        $request->session()->regenerate();

        $tujuan = $request->session()->pull('url.intended');

        return redirect()->to(NavigasiDemo::tujuanSetelahMasuk($akun, is_string($tujuan) ? $tujuan : null));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', self::PESAN_KELUAR);
    }
}
