<?php

namespace App\Http\Controllers\Transaksi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaksi\CatatPeminjamanRequest;
use App\Http\Requests\Transaksi\ProsesPengembalianRequest;
use App\Services\Transaksi\LayananPeminjaman;
use Illuminate\Http\RedirectResponse;

/**
 * Batch 2 — peminjaman langsung & pengembalian (Admin). Pesan sukses dan kode
 * transaksi dikirim lewat flash SETELAH commit; halaman membangun bukti dari
 * data MySQL terbaru.
 */
class PeminjamanController extends Controller
{
    public function store(CatatPeminjamanRequest $request, LayananPeminjaman $layanan): RedirectResponse
    {
        $pinjam = $layanan->pinjam($request->isian(), $request->user());

        return redirect()->route('admin.peminjaman')
            ->with('status', "Peminjaman {$pinjam->loan_code} berhasil dicatat.")
            ->with('transaksi', $pinjam->loan_code);
    }

    public function kembalikan(ProsesPengembalianRequest $request, LayananPeminjaman $layanan): RedirectResponse
    {
        $kembali = $layanan->kembalikan((string) $request->validated('idPeminjaman'), $request->user());

        return redirect()->route('admin.pengembalian')
            ->with('status', "Pengembalian {$kembali->return_code} berhasil diproses.")
            ->with('transaksi', $kembali->return_code);
    }
}
