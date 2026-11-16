<?php

namespace App\Http\Requests\Buku;

use App\Support\WaktuPerpustakaan;

/**
 * Aturan data Buku & eksemplar baru — sama dengan validator frontend
 * (resources/js/utils/validasiMaster.js), panjang kolom MySQL, dan
 * KEPUTUSAN_DESAIN 3.8 (kode unik tanpa membedakan huruf, tanpa spasi,
 * maks. 20 karakter; Tahun Terbit 4 digit dan tidak melebihi tahun berjalan).
 *
 * Eksemplar baru dikirim sebagai `eksemplar: [{kodeEksemplar, status?}]`.
 * `status` hanya boleh `Tersedia` (atau tidak dikirim); server tetap
 * menetapkan status sendiri — Dipesan/Dipinjam tidak pernah dapat dipilih.
 */
trait AturanBuku
{
    /** Batas eksemplar baru per penyimpanan (mencegah permintaan raksasa). */
    public const MAKS_EKSEMPLAR_BARU = 100;

    protected const POLA_KODE = '/^[A-Za-z0-9._-]+$/';

    protected function rapikanBuku(): void
    {
        $rapikan = fn (string $kunci) => trim((string) $this->input($kunci));

        $eksemplar = $this->input('eksemplar', []);
        if (is_array($eksemplar)) {
            $eksemplar = array_map(
                fn ($e) => is_array($e) && array_key_exists('kodeEksemplar', $e) && is_string($e['kodeEksemplar'])
                    ? ['kodeEksemplar' => trim($e['kodeEksemplar'])] + $e
                    : $e,
                $eksemplar,
            );
        }

        $this->merge([
            'judul' => $rapikan('judul'),
            'penulis' => $rapikan('penulis'),
            'penerbit' => $rapikan('penerbit'),
            'tahunTerbit' => $rapikan('tahunTerbit'),
            'kategori' => $rapikan('kategori'),
        ]);

        if ($this->has('eksemplar')) {
            $this->merge(['eksemplar' => $eksemplar]);
        }
    }

    /** @return array<string, list<string>> */
    protected function aturanBibliografi(): array
    {
        $tahunIni = (int) substr(WaktuPerpustakaan::hariIni(), 0, 4);

        return [
            'judul' => ['required', 'string', 'max:200'],
            'penulis' => ['required', 'string', 'max:100'],
            'penerbit' => ['required', 'string', 'max:100'],
            'tahunTerbit' => ['required', 'digits:4', 'integer', "max:{$tahunIni}"],
            'kategori' => ['required', 'string', 'max:50'],
        ];
    }

    /** @return array<string, list<string>> */
    protected function aturanEksemplarBaru(bool $wajib): array
    {
        return [
            'eksemplar' => [$wajib ? 'required' : 'sometimes', 'array', $wajib ? 'min:1' : 'min:0', 'max:'.self::MAKS_EKSEMPLAR_BARU],
            'eksemplar.*' => ['required', 'array:kodeEksemplar,status'],
            'eksemplar.*.kodeEksemplar' => ['required', 'string', 'max:20', 'regex:'.self::POLA_KODE, 'distinct:ignore_case', 'unique:book_copies,copy_code'],
            // Eksemplar baru selalu Tersedia — Dipesan/Dipinjam hanya lewat transaksi booking/peminjaman.
            'eksemplar.*.status' => ['sometimes', 'in:Tersedia'],
        ];
    }

    /** @return array<string, string> */
    protected function pesanBuku(): array
    {
        $tahunIni = (int) substr(WaktuPerpustakaan::hariIni(), 0, 4);
        $pesanTahun = "Tahun Terbit harus berupa 4 digit angka dan tidak melebihi {$tahunIni}.";

        return [
            'judul.required' => 'Judul wajib diisi.',
            'judul.max' => 'Judul maksimal 200 karakter.',
            'penulis.required' => 'Penulis wajib diisi.',
            'penulis.max' => 'Penulis maksimal 100 karakter.',
            'penerbit.required' => 'Penerbit wajib diisi.',
            'penerbit.max' => 'Penerbit maksimal 100 karakter.',
            'kategori.required' => 'Kategori wajib diisi.',
            'kategori.max' => 'Kategori maksimal 50 karakter.',
            'tahunTerbit.required' => 'Tahun Terbit wajib diisi.',
            'tahunTerbit.digits' => $pesanTahun,
            'tahunTerbit.integer' => $pesanTahun,
            'tahunTerbit.max' => $pesanTahun,
            'eksemplar.required' => 'Tambahkan minimal satu eksemplar untuk buku ini.',
            'eksemplar.array' => 'Daftar eksemplar baru tidak valid.',
            'eksemplar.min' => 'Tambahkan minimal satu eksemplar untuk buku ini.',
            'eksemplar.max' => 'Maksimal '.self::MAKS_EKSEMPLAR_BARU.' eksemplar baru per penyimpanan.',
            'eksemplar.*.required' => 'Data eksemplar baru tidak valid.',
            'eksemplar.*.array' => 'Data eksemplar baru tidak valid.',
            'eksemplar.*.kodeEksemplar.required' => 'Kode Eksemplar wajib diisi.',
            'eksemplar.*.kodeEksemplar.string' => 'Kode Eksemplar tidak valid.',
            'eksemplar.*.kodeEksemplar.max' => 'Kode Eksemplar maksimal 20 karakter.',
            'eksemplar.*.kodeEksemplar.regex' => 'Kode Eksemplar hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung (tanpa spasi).',
            'eksemplar.*.kodeEksemplar.distinct' => 'Kode Eksemplar :input tercantum lebih dari sekali.',
            'eksemplar.*.kodeEksemplar.unique' => 'Kode Eksemplar :input sudah digunakan.',
            'eksemplar.*.status.in' => 'Eksemplar baru harus berstatus Tersedia. Status Dipesan dan Dipinjam hanya berubah melalui transaksi booking dan peminjaman.',
        ];
    }

    /** @return list<string> kode eksemplar baru yang sudah tervalidasi */
    public function kodeEksemplarBaru(): array
    {
        return array_values(array_map(fn (array $e) => $e['kodeEksemplar'], $this->validated('eksemplar', [])));
    }

    /** @return array{judul: string, penulis: string, penerbit: string, tahunTerbit: string, kategori: string} */
    public function bibliografi(): array
    {
        return $this->safe()->only(['judul', 'penulis', 'penerbit', 'tahunTerbit', 'kategori']);
    }
}
