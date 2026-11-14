<?php

namespace App\Http\Controllers\Kredensial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kredensial\UbahKataSandiRequest;
use App\Services\KredensialAkun;
use Illuminate\Http\RedirectResponse;

/**
 * Phase 8C/8D — Admin atau Anggota mengganti kata sandinya sendiri.
 * Perilaku sesi: sesi saat ini TETAP masuk dengan ID sesi baru (regenerate);
 * semua sesi lain akun ini berakhir. Anggota diarahkan ke dashboard-nya.
 */
class KataSandiController extends Controller
{
    public function update(UbahKataSandiRequest $request, KredensialAkun $kredensial): RedirectResponse
    {
        $akun = $request->user();

        $kredensial->gantiKataSandiSendiri($akun, $request->validated('password'), $request->session()->getId());
        // Hancurkan baris sesi lama; sesi baru menyimpan hash baru (auth.session).
        $request->session()->regenerate(true);

        $pesan = 'Kata sandi berhasil diubah. Sesi lain pada akun ini telah diakhiri.';

        return $akun->isAnggota()
            ? redirect()->route('anggota.dashboard')->with('status', $pesan)
            : back()->with('status', $pesan);
    }
}
