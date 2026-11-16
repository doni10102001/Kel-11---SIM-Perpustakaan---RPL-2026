<?php

namespace App\Http\Controllers;

use App\Queries\DataPerpustakaan;
use App\Support\NavigasiDemo;
use App\Support\WaktuPerpustakaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 8B — menyajikan halaman aplikasi dengan data dari MySQL.
 *
 * Rute /{peran}/{halaman} sudah dilindungi middleware (auth, auth.session,
 * akun.aktif, peran:*). Di sini otorisasi diulang sebagai pertahanan berlapis
 * (Gate `buka-halaman`), lalu DataPerpustakaan memuat HANYA data yang boleh
 * dilihat akun tersebut. Komponen Vue, tata letak, dan remah roti tidak berubah.
 */
class HalamanController extends Controller
{
    public function __invoke(Request $request, DataPerpustakaan $data): Response
    {
        $peran = (string) $request->route()->defaults['peran'];
        $kunci = (string) $request->route()->defaults['halaman'];
        $halaman = NavigasiDemo::halaman($peran)[$kunci] ?? abort(404);

        Gate::authorize('buka-halaman', $peran);

        // Phase 9B — Data Buku & Cari Buku: pencarian + pagination dijalankan MySQL.
        if (DataPerpustakaan::halamanPencarianBuku($peran, $kunci)) {
            $hasil = $data->cariBuku($peran, $request->user(), $this->filterBuku($request));

            return Inertia::render($halaman['komponen'], [
                'data' => $hasil['data'],
                'pencarianBuku' => $hasil['meta'],
                'filterLaporan' => null,
            ]);
        }

        [$laporan, $galatLaporan] = $kunci === 'laporan' ? $this->filterLaporan($request) : [null, null];

        return Inertia::render($halaman['komponen'], [
            'data' => $data->untukHalaman($peran, $kunci, $request->user(), $laporan, $request->route()->parameters()),
            'filterLaporan' => $laporan,
            // Batch 3: filter laporan tidak valid ditolak server dengan pesan per field (tanpa data).
            'galatLaporan' => $galatLaporan,
        ]);
    }

    /**
     * Filter pencarian buku dari query string (?q=…&kategori=…&page=…).
     * Nilai bukan teks diabaikan, panjang dibatasi; halaman tidak valid → 1.
     *
     * @return array{q: string, kategori: string, halaman: int}
     */
    private function filterBuku(Request $request): array
    {
        $teks = function (string $kunci, int $maks) use ($request): string {
            $nilai = $request->query($kunci);

            return is_string($nilai) ? mb_substr(trim($nilai), 0, $maks) : '';
        };
        $halaman = filter_var($request->query('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);

        return ['q' => $teks('q', 100), 'kategori' => $teks('kategori', 50), 'halaman' => $halaman ?: 1];
    }

    /**
     * Filter Laporan dari query string, divalidasi di server. Tidak valid → null
     * (tidak ada data dimuat; halaman menampilkan validasinya sendiri).
     *
     * @return array{jenis: string, awal: ?string, akhir: ?string}|null
     */
    /**
     * @return array{0: array{jenis: string, awal: ?string, akhir: ?string}|null, 1: array<string, string>|null}
     *                                                                                                           [filter valid, galat per field]
     */
    private function filterLaporan(Request $request): array
    {
        // Belum ada permintaan laporan (halaman dibuka tanpa query) → bukan galat.
        if (! $request->query->has('jenis')) {
            return [null, null];
        }

        $validator = Validator::make($request->query(), [
            'jenis' => ['required', 'string', 'in:buku,anggota,peminjaman,pengembalian'],
            'awal' => ['nullable', 'string', 'date_format:Y-m-d'],
            'akhir' => ['nullable', 'string', 'date_format:Y-m-d'],
        ], [
            'jenis.*' => 'Pilih Jenis Laporan.',
            'awal.*' => 'Periode Awal tidak valid.',
            'akhir.*' => 'Periode Akhir tidak valid.',
        ]);

        if ($validator->fails()) {
            return [null, array_map(fn ($p) => $p[0], $validator->errors()->toArray())];
        }

        $nilai = $validator->validated();
        $bertanggal = in_array($nilai['jenis'], ['peminjaman', 'pengembalian'], true);
        $awal = $bertanggal ? ($nilai['awal'] ?? null) : null;
        $akhir = $bertanggal ? ($nilai['akhir'] ?? null) : null;

        foreach (['awal' => $awal, 'akhir' => $akhir] as $kunci => $tanggal) {
            if ($tanggal !== null && ! WaktuPerpustakaan::tanggalValid($tanggal)) {
                return [null, [$kunci => $kunci === 'awal' ? 'Periode Awal tidak valid.' : 'Periode Akhir tidak valid.']];
            }
        }
        if ($awal !== null && $akhir !== null && $awal > $akhir) {
            return [null, ['akhir' => 'Periode Akhir tidak boleh sebelum Periode Awal.']];
        }

        return [['jenis' => $nilai['jenis'], 'awal' => $awal, 'akhir' => $akhir], null];
    }
}
