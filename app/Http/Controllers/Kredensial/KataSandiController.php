<?php

namespace App\Http\Controllers\Kredensial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kredensial\UbahKataSandiRequest;
use App\Services\KredensialAkun;
use Illuminate\Http\RedirectResponse;

/**
 * Phase 8C — Admin mengganti kata sandinya sendiri.
 * Perilaku sesi: sesi saat ini TETAP masuk dengan ID sesi baru (regenerate);
 * semua sesi lain akun ini berakhir.
 */
class KataSandiController extends Controller
{
    public function update(UbahKataSandiRequest $request, KredensialAkun $kredensial): RedirectResponse
    {
        $akun = $request->user();

        $kredensial->gantiKataSandiSendiri($akun, $request->validated('password'), $request->session()->getId());
        // Hancurkan baris sesi lama; sesi baru menyimpan hash baru (auth.session).
        $request->session()->regenerate(true);

        return back()->with('status', 'Kata sandi berhasil diubah. Sesi lain pada akun ini telah diakhiri.');
    }
}
