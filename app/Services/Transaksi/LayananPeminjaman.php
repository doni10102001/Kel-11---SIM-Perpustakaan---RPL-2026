<?php

namespace App\Services\Transaksi;

use App\Enums\StatusAnggota;
use App\Enums\StatusBooking;
use App\Enums\StatusEksemplar;
use App\Enums\StatusPeminjaman;
use App\Models\BookCopy;
use App\Models\Booking;
use App\Models\BookReturn;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use App\Support\WaktuPerpustakaan;
use Illuminate\Database\QueryException;

/**
 * Batch 2 — peminjaman langsung dan pengembalian oleh Admin.
 *
 * Peminjaman langsung (FR-009/010/024, BR-01/05/07):
 *   kunci member → copy; anggota Aktif; eksemplar Tersedia tanpa peminjaman
 *   Aktif dan tanpa booking Disetujui (eksemplar Dipesan hanya lewat Realisasi
 *   Booking); due_date DATE diisi Admin (tanpa durasi bawaan; TBD-05).
 *
 * Pengembalian (FR-011/012/027/028; kebijakan disetujui Batch 2):
 *   kunci member → copy → loan; loan Aktif tanpa return; eksemplar Dipinjam.
 *   Anggota Aktif MAUPUN Nonaktif boleh dikembalikan Admin; status anggota
 *   tidak diubah. UNIQUE returns.loan_id menolak pengembalian ganda.
 */
class LayananPeminjaman extends DasarTransaksi
{
    public function __construct(private readonly LayananKedaluwarsaBooking $kedaluwarsa) {}

    /**
     * @param  array{kodeAnggota: string, kodeEksemplar: string, kodeBuku?: ?string, tanggalJatuhTempo: string}  $isian
     */
    public function pinjam(array $isian, User $admin): Loan
    {
        $idEksemplar = BookCopy::query()->where('copy_code', $isian['kodeEksemplar'])->value('id');
        if ($idEksemplar !== null) {
            // Reservasi yang sudah lewat batas dilepas dulu (penjadwal bisa tertunda ≤ 5 menit).
            $this->kedaluwarsa->rekonsiliasiEksemplar((int) $idEksemplar, 'peminjaman');
        }

        return $this->transaksi(function () use ($isian, $admin) {
            $anggota = Member::query()->where('member_code', $isian['kodeAnggota'])->lockForUpdate()->first();
            if ($anggota === null) {
                self::tolak('kodeAnggota', 'Anggota tidak valid: kode anggota tidak terdaftar.');
            }
            if ($anggota->status !== StatusAnggota::Aktif) {
                self::tolak('kodeAnggota', 'Anggota tidak valid: hanya anggota berstatus Aktif yang dapat meminjam.');
            }

            $eksemplar = BookCopy::query()->with('book:id,book_code')->where('copy_code', $isian['kodeEksemplar'])->lockForUpdate()->first();
            if ($eksemplar === null || (($isian['kodeBuku'] ?? null) && strcasecmp($eksemplar->book->book_code, (string) $isian['kodeBuku']) !== 0)) {
                self::tolak('kodeEksemplar', 'Kode Eksemplar tidak terdaftar untuk buku ini.');
            }
            $this->pastikanDapatDipinjam($eksemplar);

            $sekarang = WaktuPerpustakaan::sekarangUtc();
            $pinjam = new Loan;
            $pinjam->forceFill([
                'member_id' => $anggota->id,
                'copy_id' => $eksemplar->id,
                'staff_id' => $admin->id,
                'booking_id' => null,
                'loaned_at' => $sekarang,
                'due_date' => $isian['tanggalJatuhTempo'],
                'status' => StatusPeminjaman::Aktif,
            ]);
            $this->simpanPinjaman($pinjam, $eksemplar);

            $eksemplar->forceFill(['status' => StatusEksemplar::Dipinjam])->save();

            $this->catat($admin, 'peminjaman.dibuat', 'loan', $pinjam->id, [
                'kode_peminjaman' => $pinjam->loan_code,
                'kode_anggota' => $anggota->member_code,
                'kode_eksemplar' => $eksemplar->copy_code,
                'jatuh_tempo' => $isian['tanggalJatuhTempo'],
                'sumber' => 'langsung',
            ]);

            return $pinjam;
        });
    }

    public function kembalikan(string $kodePeminjaman, User $admin): BookReturn
    {
        return $this->transaksi(function () use ($kodePeminjaman, $admin) {
            $awal = Loan::query()->where('loan_code', $kodePeminjaman)->first(['id', 'member_id', 'copy_id']);
            if ($awal === null) {
                self::tolak('umum', "Transaksi {$kodePeminjaman} tidak ditemukan.");
            }

            // Urutan kunci: member → copy → loan → return. Status anggota TIDAK diperiksa (kebijakan Batch 2).
            Member::query()->whereKey($awal->member_id)->lockForUpdate()->first();
            $eksemplar = BookCopy::query()->whereKey($awal->copy_id)->lockForUpdate()->firstOrFail();
            $pinjam = Loan::query()->whereKey($awal->id)->lockForUpdate()->firstOrFail();

            if ($pinjam->status !== StatusPeminjaman::Aktif || BookReturn::query()->where('loan_id', $pinjam->id)->exists()) {
                self::tolak('umum', "Pengembalian ditolak: transaksi {$pinjam->loan_code} sudah dikembalikan.");
            }
            if ($eksemplar->status !== StatusEksemplar::Dipinjam) {
                self::tolak('umum', "Pengembalian ditolak: eksemplar {$eksemplar->copy_code} tidak berstatus Dipinjam. Periksa konsistensi data.");
            }

            $kembali = new BookReturn;
            $kembali->forceFill(['loan_id' => $pinjam->id, 'staff_id' => $admin->id, 'returned_at' => WaktuPerpustakaan::sekarangUtc()]);
            try {
                $this->simpanDenganKode($kembali, 'KB');
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    self::tolak('umum', "Pengembalian ditolak: transaksi {$pinjam->loan_code} sudah dikembalikan.");
                }
                throw $e;
            }

            $pinjam->forceFill(['status' => StatusPeminjaman::Selesai])->save();
            $eksemplar->forceFill(['status' => StatusEksemplar::Tersedia])->save();

            $this->catat($admin, 'pengembalian.dibuat', 'return', $kembali->id, [
                'kode_pengembalian' => $kembali->return_code,
                'kode_peminjaman' => $pinjam->loan_code,
                'kode_eksemplar' => $eksemplar->copy_code,
            ]);

            return $kembali;
        });
    }

    /** Eksemplar terkunci harus Tersedia, tanpa peminjaman Aktif, dan tanpa booking Disetujui. */
    private function pastikanDapatDipinjam(BookCopy $eksemplar): void
    {
        $kode = $eksemplar->copy_code;
        if (Loan::query()->where('copy_id', $eksemplar->id)->where('status', StatusPeminjaman::Aktif->value)->exists()) {
            self::tolak('kodeEksemplar', "Buku tidak tersedia: eksemplar {$kode} masih dalam peminjaman Aktif.");
        }
        if ($eksemplar->status === StatusEksemplar::Dipesan || Booking::query()->where('copy_id', $eksemplar->id)->where('status', StatusBooking::Disetujui->value)->exists()) {
            self::tolak('kodeEksemplar', "Buku tidak tersedia: eksemplar {$kode} sedang dipesan dan hanya dapat dipinjam melalui Realisasi Booking.");
        }
        if ($eksemplar->status !== StatusEksemplar::Tersedia) {
            self::tolak('kodeEksemplar', "Buku tidak tersedia: eksemplar {$kode} berstatus {$eksemplar->status->value}.");
        }
    }

    /** Simpan loan; UNIQUE active_copy_id / booking_id (balapan) → galat validasi. */
    private function simpanPinjaman(Loan $pinjam, BookCopy $eksemplar): void
    {
        try {
            $this->simpanDenganKode($pinjam, 'PJ');
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                self::tolak('kodeEksemplar', "Buku tidak tersedia: eksemplar {$eksemplar->copy_code} sudah dipinjam transaksi lain.");
            }
            throw $e;
        }
    }
}
