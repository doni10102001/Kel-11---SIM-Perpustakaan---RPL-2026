<?php

namespace App\Services;

use App\Enums\StatusEksemplar;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 9B — mutasi data Buku & eksemplar oleh Admin: tambah buku beserta
 * eksemplar awal, ubah data bibliografi, tambah eksemplar.
 *
 * Aturan:
 * - Semua dalam satu transaksi MySQL; audit ditulis di transaksi yang sama
 *   (transaksi gagal → tidak ada audit "berhasil").
 * - Buku baru wajib memiliki minimal satu eksemplar (SRS 6.2, Class Diagram
 *   1..*); buku dan eksemplar awalnya dibuat atomik — gagal satu, batal semua.
 * - Kode Buku diisi Admin dan TIDAK dapat diubah; keunikan dijaga validasi dan
 *   `uq_books_book_code` / `uq_book_copies_copy_code` (bentrok balapan → galat
 *   validasi, bukan SQL).
 * - Eksemplar baru SELALU `Tersedia`. Status eksemplar yang sudah ada TIDAK
 *   pernah disentuh di sini; Dipesan/Dipinjam hanya berubah lewat transaksi
 *   booking/peminjaman (State Machine Eksemplar).
 * - Tidak ada penghapusan fisik buku/eksemplar (tidak ada rute hapus; FK
 *   RESTRICT menolak penghapusan yang punya riwayat).
 *
 * Penguncian: ubah() mengunci baris `books` (FOR UPDATE) sebelum memeriksa
 * jumlah eksemplar dan menyisipkan eksemplar baru. Tidak ada baris member,
 * booking, atau peminjaman yang dikunci/diubah, sehingga urutan kunci
 * transaksi (member → copy → booking → loan → return) tidak terganggu.
 */
class LayananBuku
{
    public const PESAN_KODE_BUKU_DIPAKAI = 'Kode Buku sudah digunakan buku lain.';

    public const PESAN_MINIMAL_EKSEMPLAR = 'Tambahkan minimal satu eksemplar untuk buku ini.';

    /**
     * @param  array{kodeBuku: string, judul: string, penulis: string, penerbit: string, tahunTerbit: int|string, kategori: string}  $isian
     * @param  list<string>  $kodeEksemplar  kode eksemplar awal (minimal satu)
     */
    public function tambah(array $isian, array $kodeEksemplar, User $aktor): Book
    {
        if ($kodeEksemplar === []) {
            throw ValidationException::withMessages(['eksemplar' => self::PESAN_MINIMAL_EKSEMPLAR]);
        }

        return DB::transaction(function () use ($isian, $kodeEksemplar, $aktor) {
            $buku = new Book;
            $buku->forceFill(['book_code' => $isian['kodeBuku'], ...$this->kolomBibliografi($isian)]);
            $this->simpan($buku);

            $this->catat($aktor, 'buku.dibuat', 'book', $buku->id, [
                'kode_buku' => $buku->book_code,
                'jumlah_eksemplar' => count($kodeEksemplar),
            ]);

            $this->tambahEksemplar($buku, $kodeEksemplar, $aktor);

            return $buku->refresh();
        });
    }

    /**
     * @param  array{judul: string, penulis: string, penerbit: string, tahunTerbit: int|string, kategori: string}  $isian
     * @param  list<string>  $kodeEksemplar  eksemplar BARU (boleh kosong)
     * @return array{0: Book, 1: int} buku terkini dan jumlah eksemplar baru
     */
    public function ubah(string $kodeBuku, array $isian, array $kodeEksemplar, User $aktor): array
    {
        return DB::transaction(function () use ($kodeBuku, $isian, $kodeEksemplar, $aktor) {
            // Kunci baris buku, lalu baca ulang data terkini. Kode Buku dari URL — tidak pernah diubah.
            $buku = Book::query()->where('book_code', $kodeBuku)->lockForUpdate()->firstOrFail();

            if ($kodeEksemplar === [] && ! $buku->copies()->exists()) {
                throw ValidationException::withMessages(['eksemplar' => self::PESAN_MINIMAL_EKSEMPLAR]);
            }

            $buku->forceFill($this->kolomBibliografi($isian));
            $berubah = array_keys($buku->getDirty());

            if ($berubah !== []) {
                $buku->save();
                // Hanya nama kolom yang berubah (tanpa nilai lama/baru).
                $this->catat($aktor, 'buku.diubah', 'book', $buku->id, ['kode_buku' => $buku->book_code, 'kolom' => $berubah]);
            }

            $this->tambahEksemplar($buku, $kodeEksemplar, $aktor);

            return [$buku, count($kodeEksemplar)];
        });
    }

    /** @param  list<string>  $kodeEksemplar */
    private function tambahEksemplar(Book $buku, array $kodeEksemplar, User $aktor): void
    {
        foreach ($kodeEksemplar as $kode) {
            $eksemplar = new BookCopy;
            // Status ditetapkan server: eksemplar baru selalu Tersedia (status tidak fillable dan tidak dari klien).
            $eksemplar->forceFill(['book_id' => $buku->id, 'copy_code' => $kode, 'status' => StatusEksemplar::Tersedia]);
            $this->simpan($eksemplar);

            $this->catat($aktor, 'eksemplar.dibuat', 'book_copy', $eksemplar->id, [
                'kode_eksemplar' => $eksemplar->copy_code,
                'kode_buku' => $buku->book_code,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function kolomBibliografi(array $isian): array
    {
        return [
            'title' => $isian['judul'],
            'author' => $isian['penulis'],
            'publisher' => $isian['penerbit'],
            'publication_year' => (int) $isian['tahunTerbit'],
            'category' => $isian['kategori'],
        ];
    }

    /** Simpan; bentrok UNIQUE (1062) saat balapan → galat validasi pada field yang tepat. */
    private function simpan(Book|BookCopy $model): void
    {
        try {
            $model->save();
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }

            throw $model instanceof Book
                ? ValidationException::withMessages(['kodeBuku' => self::PESAN_KODE_BUKU_DIPAKAI])
                : ValidationException::withMessages(['eksemplar' => "Kode Eksemplar {$model->copy_code} sudah digunakan. Gunakan kode lain."]);
        }
    }

    /** Audit: aksi, aktor, subjek, kode — tanpa data pribadi atau rahasia. */
    private function catat(User $aktor, string $aksi, string $jenis, int $id, array $metadata): void
    {
        (new AuditLog)->forceFill([
            'actor_id' => $aktor->id,
            'action' => $aksi,
            'subject_type' => $jenis,
            'subject_id' => $id,
            'metadata' => $metadata,
        ])->save();
    }
}
