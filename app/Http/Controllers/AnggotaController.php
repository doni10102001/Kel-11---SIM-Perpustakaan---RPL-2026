<?php

namespace App\Http\Controllers;

use App\Http\Requests\Anggota\TambahAnggotaRequest;
use App\Http\Requests\Anggota\UbahAnggotaRequest;
use App\Services\LayananAnggota;
use Illuminate\Http\RedirectResponse;

/**
 * Phase 9A — tulis data Anggota (Admin). Pesan sukses dikirim SETELAH commit
 * (redirect + flash); tidak ada kata sandi/hash di respons.
 */
class AnggotaController extends Controller
{
    public function store(TambahAnggotaRequest $request, LayananAnggota $layanan): RedirectResponse
    {
        $anggota = $layanan->tambah($request->profil(), $request->akses(), $request->user());
        $akses = $anggota->user_id ? ' Akses login dibuat; anggota wajib mengganti kata sandi saat pertama masuk.' : '';

        return redirect()->route('admin.anggota.index')
            ->with('status', "Anggota {$anggota->member_code} · {$anggota->name} berhasil ditambahkan.{$akses}");
    }

    public function update(UbahAnggotaRequest $request, LayananAnggota $layanan, string $kodeAnggota): RedirectResponse
    {
        $anggota = $layanan->ubah($kodeAnggota, $request->profil(), $request->user());

        return redirect()->route('admin.anggota.index')
            ->with('status', "Perubahan data anggota {$anggota->member_code} · {$anggota->name} disimpan.");
    }
}
