<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Pemeriksaan invarian lintas tabel yang tidak dapat diekspresikan dengan
 * FK/CHECK MySQL (desain Phase 7 §6: INV-02, INV-06..INV-11).
 *
 * Hanya membaca data. Mengembalikan daftar pelanggaran (kosong = konsisten).
 * Dipakai seeder development untuk menolak data tidak konsisten dan dapat
 * dipakai sebagai audit rekonsiliasi (DB-17).
 */
class PemeriksaInvarianData
{
    /**
     * @return list<string>
     */
    public function periksa(): array
    {
        $pelanggaran = [];

        // INV-06/INV-07: status eksemplar = turunan transaksi saat ini.
        $salahStatus = DB::select(<<<'SQL'
            SELECT x.copy_code, x.status, x.seharusnya FROM (
                SELECT c.copy_code, c.status,
                    CASE
                        WHEN EXISTS (SELECT 1 FROM loans l WHERE l.copy_id = c.id AND l.status = 'Aktif') THEN 'Dipinjam'
                        WHEN EXISTS (SELECT 1 FROM bookings b WHERE b.copy_id = c.id AND b.status = 'Disetujui') THEN 'Dipesan'
                        ELSE 'Tersedia'
                    END AS seharusnya
                FROM book_copies c
            ) x
            WHERE x.status <> x.seharusnya
            SQL);
        foreach ($salahStatus as $baris) {
            $pelanggaran[] = "Eksemplar {$baris->copy_code} berstatus {$baris->status}, seharusnya {$baris->seharusnya}.";
        }

        // Copy tidak boleh sekaligus punya loan Aktif dan booking Disetujui.
        foreach (DB::select(<<<'SQL'
            SELECT c.copy_code FROM book_copies c
            WHERE EXISTS (SELECT 1 FROM loans l WHERE l.copy_id = c.id AND l.status = 'Aktif')
              AND EXISTS (SELECT 1 FROM bookings b WHERE b.copy_id = c.id AND b.status = 'Disetujui')
            SQL) as $baris) {
            $pelanggaran[] = "Eksemplar {$baris->copy_code} memiliki peminjaman Aktif dan booking Disetujui sekaligus.";
        }

        // Loan Selesai <=> punya tepat satu return (UNIQUE menjaga "maksimal satu").
        foreach (DB::select(<<<'SQL'
            SELECT l.loan_code, l.status FROM loans l
            LEFT JOIN `returns` r ON r.loan_id = l.id
            WHERE (l.status = 'Selesai' AND r.id IS NULL) OR (l.status = 'Aktif' AND r.id IS NOT NULL)
            SQL) as $baris) {
            $pelanggaran[] = "Peminjaman {$baris->loan_code} ({$baris->status}) tidak sesuai dengan data pengembalian.";
        }

        // INV-09: booking Direalisasikan <=> ada loan dengan booking_id tersebut.
        foreach (DB::select(<<<'SQL'
            SELECT b.booking_code, b.status FROM bookings b
            LEFT JOIN loans l ON l.booking_id = b.id
            WHERE (b.status = 'Direalisasikan' AND l.id IS NULL) OR (b.status <> 'Direalisasikan' AND l.id IS NOT NULL)
            SQL) as $baris) {
            $pelanggaran[] = "Booking {$baris->booking_code} ({$baris->status}) tidak sesuai dengan tautan peminjaman.";
        }

        // Loan hasil realisasi harus untuk anggota dan eksemplar yang sama dengan booking-nya.
        foreach (DB::select(<<<'SQL'
            SELECT l.loan_code FROM loans l JOIN bookings b ON b.id = l.booking_id
            WHERE l.member_id <> b.member_id OR l.copy_id <> b.copy_id
            SQL) as $baris) {
            $pelanggaran[] = "Peminjaman {$baris->loan_code} tidak cocok dengan anggota/eksemplar booking asalnya.";
        }

        // F-01 opsi A / INV-10, INV-11: tidak ada booking Disetujui milik anggota Nonaktif.
        foreach (DB::select(<<<'SQL'
            SELECT b.booking_code, m.member_code FROM bookings b JOIN members m ON m.id = b.member_id
            WHERE b.status = 'Disetujui' AND m.status = 'Nonaktif'
            SQL) as $baris) {
            $pelanggaran[] = "Booking {$baris->booking_code} Disetujui milik anggota Nonaktif {$baris->member_code}.";
        }

        // INV-02: akun Anggota terhubung ke tepat satu anggota; akun Admin bukan anggota.
        foreach (DB::select(<<<'SQL'
            SELECT u.username, u.role FROM users u LEFT JOIN members m ON m.user_id = u.id
            WHERE (u.role = 'anggota' AND m.id IS NULL) OR (u.role = 'admin' AND m.id IS NOT NULL)
            SQL) as $baris) {
            $pelanggaran[] = "Akun {$baris->username} ({$baris->role}) tidak sesuai relasi anggota.";
        }

        // Pemroses transaksi harus akun Admin (staff_id merujuk users.id ber-role admin).
        foreach (['loans' => 'loan_code', 'returns' => 'return_code', 'bookings' => 'booking_code'] as $tabel => $kode) {
            foreach (DB::select("SELECT t.{$kode} AS kode FROM `{$tabel}` t JOIN users u ON u.id = t.staff_id WHERE u.role <> 'admin'") as $baris) {
                $pelanggaran[] = "Transaksi {$baris->kode} diproses oleh akun yang bukan Admin.";
            }
        }

        return $pelanggaran;
    }
}
