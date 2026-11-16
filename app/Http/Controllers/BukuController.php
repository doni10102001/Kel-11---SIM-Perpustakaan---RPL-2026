<?php

namespace App\Http\Controllers;

use App\Http\Requests\Buku\TambahBukuRequest;
use App\Http\Requests\Buku\UbahBukuRequest;
use App\Services\LayananBuku;
use Illuminate\Http\RedirectResponse;

/**
 * Phase 9B — tulis data Buku & eksemplar (Admin). Pesan sukses dikirim SETELAH
 * commit (redirect + flash). Tidak ada aksi hapus.
 */
class BukuController extends Controller
{
    public function store(TambahBukuRequest $request, LayananBuku $layanan): RedirectResponse
    {
        $eksemplar = $request->kodeEksemplarBaru();
        $buku = $layanan->tambah($request->dataBuku(), $eksemplar, $request->user());
        $jumlah = count($eksemplar);

        return redirect()->route('admin.buku.index')
            ->with('status', "Buku {$buku->book_code} · {$buku->title} berhasil ditambahkan dengan {$jumlah} eksemplar.");
    }

    public function update(UbahBukuRequest $request, LayananBuku $layanan, string $kodeBuku): RedirectResponse
    {
        [$buku, $jumlahBaru] = $layanan->ubah($kodeBuku, $request->bibliografi(), $request->kodeEksemplarBaru(), $request->user());
        $tambahan = $jumlahBaru > 0 ? " {$jumlahBaru} eksemplar baru ditambahkan." : '';

        return redirect()->route('admin.buku.index')
            ->with('status', "Perubahan data buku {$buku->book_code} · {$buku->title} disimpan.{$tambahan}");
    }
}
