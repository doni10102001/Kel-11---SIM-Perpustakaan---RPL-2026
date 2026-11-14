<?php

namespace App\Http\Controllers\Kredensial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kredensial\BuatAksesLoginRequest;
use App\Http\Requests\Kredensial\ResetKataSandiAnggotaRequest;
use App\Services\KredensialAkun;
use Illuminate\Http\RedirectResponse;

/**
 * Phase 8C — kredensial Anggota oleh Admin (rute /admin/anggota/{kodeAnggota}/...).
 * Respons hanya redirect + pesan status; tidak pernah memuat kata sandi atau hash.
 */
class AksesLoginAnggotaController extends Controller
{
    public function store(BuatAksesLoginRequest $request, KredensialAkun $kredensial, string $kodeAnggota): RedirectResponse
    {
        $akun = $kredensial->buatAksesLogin($kodeAnggota, $request->validated('username'), $request->validated('password'), $request->user());

        return back()->with('status', "Akses login dibuat dengan username {$akun->username}. Login mengikuti Status Anggota.");
    }

    public function resetKataSandi(ResetKataSandiAnggotaRequest $request, KredensialAkun $kredensial, string $kodeAnggota): RedirectResponse
    {
        $kredensial->resetKataSandiAnggota($kodeAnggota, $request->validated('password'), $request->user());

        return back()->with('status', 'Kata sandi akses login telah direset. Sesi login anggota sebelumnya telah diakhiri.');
    }
}
