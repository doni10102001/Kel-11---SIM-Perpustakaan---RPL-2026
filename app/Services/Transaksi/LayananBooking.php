<?php

namespace App\Services\Transaksi;

use App\Enums\StatusAnggota;
use App\Enums\StatusBooking;
use App\Enums\StatusEksemplar;
use App\Enums\StatusPeminjaman;
use App\Models\BookCopy;
use App\Models\Booking;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use App\Support\WaktuPerpustakaan;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Batch 2 — siklus booking: ajukan & batalkan (Anggota), setujui, tolak,
 * realisasi (Admin). Urutan kunci: member → booking → copy → loan.
 *
 * Aturan yang dipertahankan dari perilaku teruji (fixture & KEPUTUSAN_DESAIN):
 * - Pengajuan: anggota Aktif; eksemplar Tersedia tanpa peminjaman Aktif & tanpa
 *   booking Disetujui; Diajukan TIDAK mereservasi eksemplar; pengajuan ganda
 *   (anggota & eksemplar sama dengan booking Diajukan/Disetujui) ditolak;
 *   anggota lain boleh mengajukan eksemplar yang sama selama belum Dipesan;
 *   tanpa kuota/antrean (TBD-06).
 * - Persetujuan: booking Diajukan, anggota Aktif (F-01), eksemplar Tersedia →
 *   Disetujui, decided_at = sekarang, expires_at = +48 jam, eksemplar Dipesan.
 * - Penolakan: booking Diajukan + alasan wajib; eksemplar tidak berubah.
 * - Realisasi: booking Disetujui & belum lewat batas, anggota Aktif, eksemplar
 *   Dipesan untuk booking ini, tanpa peminjaman Aktif → satu loan
 *   (UNIQUE loans.booking_id). Booking yang sudah lewat batas dikedaluwarsakan
 *   & di-COMMIT, lalu realisasi ditolak setelah commit.
 * - Pembatalan: hanya pemilik; Diajukan/Disetujui (belum lewat batas) →
 *   Dibatalkan; Disetujui melepas reservasi. Sudah lewat batas → Kedaluwarsa.
 */
class LayananBooking extends DasarTransaksi
{
    public function __construct(private readonly LayananKedaluwarsaBooking $kedaluwarsa) {}

    // ------------------------------------------------------------------ Anggota

    /** @param  array{kodeEksemplar: string, kodeBuku?: ?string}  $isian */
    public function ajukan(Member $pemilik, array $isian, User $akun): Booking
    {
        $idEksemplar = BookCopy::query()->where('copy_code', $isian['kodeEksemplar'])->value('id');
        if ($idEksemplar !== null) {
            $this->kedaluwarsa->rekonsiliasiEksemplar((int) $idEksemplar, 'pengajuan');
        }

        return $this->transaksi(function () use ($pemilik, $isian, $akun) {
            // Pemilik dari sesi; baris member dikunci → pengajuan ganda serentak oleh anggota yang sama berurutan.
            $anggota = Member::query()->whereKey($pemilik->id)->lockForUpdate()->firstOrFail();
            if ($anggota->status !== StatusAnggota::Aktif) {
                self::tolak('umum', 'Booking ditolak: keanggotaan Anda berstatus Nonaktif. Hubungi Admin perpustakaan.');
            }

            $eksemplar = BookCopy::query()->with('book:id,book_code')->where('copy_code', $isian['kodeEksemplar'])->lockForUpdate()->first();
            if ($eksemplar === null) {
                self::tolak('kodeEksemplar', 'Kode Eksemplar tidak terdaftar.');
            }
            if (($isian['kodeBuku'] ?? null) && strcasecmp($eksemplar->book->book_code, (string) $isian['kodeBuku']) !== 0) {
                self::tolak('kodeEksemplar', 'Kode Eksemplar tidak terdaftar untuk buku ini.');
            }
            if (! $this->dapatDipesan($eksemplar)) {
                self::tolak('kodeEksemplar', "Booking ditolak: eksemplar {$eksemplar->copy_code} berstatus {$eksemplar->status->value}. Hanya eksemplar Tersedia yang dapat dibooking.");
            }

            $ganda = Booking::query()
                ->where('member_id', $anggota->id)->where('copy_id', $eksemplar->id)
                ->whereIn('status', [StatusBooking::Diajukan->value, StatusBooking::Disetujui->value])
                ->first(['booking_code', 'status']);
            if ($ganda !== null) {
                self::tolak('kodeEksemplar', "Anda sudah memiliki booking {$ganda->booking_code} ({$ganda->status->value}) untuk eksemplar ini.");
            }

            $booking = new Booking;
            $booking->forceFill([
                'member_id' => $anggota->id,
                'copy_id' => $eksemplar->id,
                'status' => StatusBooking::Diajukan,
                'submitted_at' => WaktuPerpustakaan::sekarangUtc(),
            ]);
            $this->simpanDenganKode($booking, 'BP');
            // Diajukan TIDAK mengubah status eksemplar (reservasi baru saat disetujui).

            $this->catat($akun, 'booking.diajukan', 'booking', $booking->id, [
                'kode_booking' => $booking->booking_code,
                'kode_eksemplar' => $eksemplar->copy_code,
            ]);

            return $booking;
        });
    }

    public function batalkan(Member $pemilik, string $kodeBooking, User $akun): Booking
    {
        $hasil = $this->transaksi(function () use ($pemilik, $kodeBooking, $akun) {
            Member::query()->whereKey($pemilik->id)->lockForUpdate()->firstOrFail();
            // Kepemilikan dari sesi: booking anggota lain diperlakukan "tidak ditemukan".
            $booking = Booking::query()->where('booking_code', $kodeBooking)->where('member_id', $pemilik->id)->lockForUpdate()->first();
            if ($booking === null) {
                self::tolak('umum', "Booking {$kodeBooking} tidak ditemukan pada akun Anda.");
            }

            $sekarang = WaktuPerpustakaan::sekarangUtc();
            if (LayananKedaluwarsaBooking::sudahLewat($booking, $sekarang)) {
                $this->kedaluwarsa->terapkanTerkunci($booking, $sekarang, 'pembatalan');

                return ['kedaluwarsa', $booking]; // di-commit; penolakan disampaikan setelah commit
            }

            if (! in_array($booking->status, [StatusBooking::Diajukan, StatusBooking::Disetujui], true)) {
                self::tolak('umum', "Booking {$booking->booking_code} berstatus {$booking->status->value} dan tidak dapat dibatalkan.");
            }

            $melepas = $booking->status === StatusBooking::Disetujui;
            $eksemplar = $melepas ? BookCopy::query()->whereKey($booking->copy_id)->lockForUpdate()->firstOrFail() : null;

            $booking->forceFill(['status' => StatusBooking::Dibatalkan])->save();

            $dilepas = false;
            if ($eksemplar !== null && $eksemplar->status === StatusEksemplar::Dipesan && ! $this->dimilikiTransaksiLain($eksemplar)) {
                $eksemplar->forceFill(['status' => StatusEksemplar::Tersedia])->save();
                $dilepas = true;
            }

            $this->catat($akun, 'booking.dibatalkan', 'booking', $booking->id, [
                'kode_booking' => $booking->booking_code,
                'status_sebelumnya' => $melepas ? StatusBooking::Disetujui->value : StatusBooking::Diajukan->value,
                'eksemplar_dilepas' => $dilepas,
            ]);

            return ['dibatalkan', $booking];
        });

        if ($hasil[0] === 'kedaluwarsa') {
            throw ValidationException::withMessages(['umum' => LayananKedaluwarsaBooking::PESAN_KEDALUWARSA]);
        }

        return $hasil[1];
    }

    // ------------------------------------------------------------------ Admin

    public function setujui(string $kodeBooking, User $admin): Booking
    {
        $awal = Booking::query()->where('booking_code', $kodeBooking)->first(['id', 'member_id', 'copy_id']);
        if ($awal === null) {
            self::tolak('umum', "Booking {$kodeBooking} tidak ditemukan.");
        }
        // Reservasi lama yang sudah lewat batas pada eksemplar ini dilepas dulu.
        $this->kedaluwarsa->rekonsiliasiEksemplar((int) $awal->copy_id, 'persetujuan');

        return $this->transaksi(function () use ($awal, $admin) {
            // F-01: kunci member LEBIH DULU (sama dengan penonaktifan di LayananAnggota).
            $anggota = Member::query()->whereKey($awal->member_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($awal->id)->lockForUpdate()->firstOrFail();

            if ($booking->status !== StatusBooking::Diajukan) {
                self::tolak('umum', "Booking {$booking->booking_code} berstatus {$booking->status->value}; hanya booking Diajukan yang dapat disetujui.");
            }
            if ($anggota->status !== StatusAnggota::Aktif) {
                self::tolak('umum', "Booking tidak dapat disetujui karena status anggota {$anggota->member_code} Nonaktif.");
            }

            $eksemplar = BookCopy::query()->whereKey($booking->copy_id)->lockForUpdate()->firstOrFail();
            if (! $this->dapatDipesan($eksemplar)) {
                self::tolak('umum', "Eksemplar {$eksemplar->copy_code} tidak tersedia (status {$eksemplar->status->value}); booking hanya dapat ditolak.");
            }

            $sekarang = WaktuPerpustakaan::sekarangUtc();
            $booking->forceFill([
                'status' => StatusBooking::Disetujui,
                'decided_at' => $sekarang,
                'expires_at' => LayananKedaluwarsaBooking::batasPengambilan($sekarang),
                'expired_at' => null,
                'staff_id' => $admin->id,
            ]);
            try {
                $booking->save();
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) { // uq_bookings_reserved_copy
                    self::tolak('umum', "Eksemplar {$eksemplar->copy_code} sudah direservasi booking lain.");
                }
                throw $e;
            }
            $eksemplar->forceFill(['status' => StatusEksemplar::Dipesan])->save();

            $this->catat($admin, 'booking.disetujui', 'booking', $booking->id, [
                'kode_booking' => $booking->booking_code,
                'kode_eksemplar' => $eksemplar->copy_code,
                'batas_pengambilan' => $booking->expires_at->utc()->format('Y-m-d\TH:i:s\Z'),
            ]);

            return $booking;
        });
    }

    public function tolakBooking(string $kodeBooking, string $alasan, User $admin): Booking
    {
        return $this->transaksi(function () use ($kodeBooking, $alasan, $admin) {
            $memberId = Booking::query()->where('booking_code', $kodeBooking)->value('member_id');
            if ($memberId === null) {
                self::tolak('umum', "Booking {$kodeBooking} tidak ditemukan.");
            }
            Member::query()->whereKey($memberId)->lockForUpdate()->first();
            $booking = Booking::query()->where('booking_code', $kodeBooking)->lockForUpdate()->firstOrFail();

            if ($booking->status !== StatusBooking::Diajukan) {
                self::tolak('umum', "Booking {$booking->booking_code} berstatus {$booking->status->value}; hanya booking Diajukan yang dapat ditolak.");
            }

            $booking->forceFill([
                'status' => StatusBooking::Ditolak,
                'rejection_reason' => $alasan,
                'decided_at' => WaktuPerpustakaan::sekarangUtc(),
                'staff_id' => $admin->id,
            ])->save();
            // Eksemplar tidak berubah (Diajukan tidak pernah mereservasi).

            // Alasan tidak disalin ke audit (bisa memuat data pribadi); cukup panjangnya.
            $this->catat($admin, 'booking.ditolak', 'booking', $booking->id, [
                'kode_booking' => $booking->booking_code,
                'panjang_alasan' => mb_strlen($alasan),
            ]);

            return $booking;
        });
    }

    public function realisasikan(string $kodeBooking, string $tanggalJatuhTempo, User $admin): Loan
    {
        $hasil = $this->transaksi(function () use ($kodeBooking, $tanggalJatuhTempo, $admin) {
            $memberId = Booking::query()->where('booking_code', $kodeBooking)->value('member_id');
            if ($memberId === null) {
                self::tolak('umum', "Booking {$kodeBooking} tidak ditemukan.");
            }
            $anggota = Member::query()->whereKey($memberId)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->where('booking_code', $kodeBooking)->lockForUpdate()->firstOrFail();

            $sekarang = WaktuPerpustakaan::sekarangUtc();
            if (LayananKedaluwarsaBooking::sudahLewat($booking, $sekarang)) {
                // Penjadwal belum memproses: kedaluwarsakan & COMMIT, realisasi ditolak setelah commit.
                $this->kedaluwarsa->terapkanTerkunci($booking, $sekarang, 'realisasi');

                return ['kedaluwarsa', null];
            }

            if ($booking->status !== StatusBooking::Disetujui) {
                self::tolak('umum', "Booking {$booking->booking_code} berstatus {$booking->status->value}; hanya booking Disetujui yang dapat direalisasikan.");
            }
            if ($anggota->status !== StatusAnggota::Aktif) {
                self::tolak('umum', "Anggota {$anggota->member_code} tidak berstatus Aktif; booking tidak dapat direalisasikan.");
            }

            $eksemplar = BookCopy::query()->whereKey($booking->copy_id)->lockForUpdate()->firstOrFail();
            $reservasi = Booking::query()->where('copy_id', $eksemplar->id)->where('status', StatusBooking::Disetujui->value)->value('id');
            if ($eksemplar->status !== StatusEksemplar::Dipesan || (int) $reservasi !== $booking->id) {
                self::tolak('umum', "Eksemplar {$eksemplar->copy_code} tidak dipesan untuk booking ini.");
            }
            if (Loan::query()->where('copy_id', $eksemplar->id)->where('status', StatusPeminjaman::Aktif->value)->exists()) {
                self::tolak('umum', "Eksemplar {$eksemplar->copy_code} masih dalam peminjaman Aktif.");
            }

            $pinjam = new Loan;
            $pinjam->forceFill([
                'member_id' => $anggota->id,
                'copy_id' => $eksemplar->id,
                'staff_id' => $admin->id,
                'booking_id' => $booking->id,
                'loaned_at' => $sekarang,
                'due_date' => $tanggalJatuhTempo,
                'status' => StatusPeminjaman::Aktif,
            ]);
            try {
                $this->simpanDenganKode($pinjam, 'PJ');
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) { // uq_loans_booking_id / uq_loans_active_copy
                    self::tolak('umum', "Booking {$booking->booking_code} sudah direalisasikan atau eksemplarnya sedang dipinjam.");
                }
                throw $e;
            }

            // expires_at dipertahankan sebagai riwayat batas pengambilan.
            $booking->forceFill(['status' => StatusBooking::Direalisasikan])->save();
            $eksemplar->forceFill(['status' => StatusEksemplar::Dipinjam])->save();

            $this->catat($admin, 'peminjaman.dibuat', 'loan', $pinjam->id, [
                'kode_peminjaman' => $pinjam->loan_code,
                'kode_anggota' => $anggota->member_code,
                'kode_eksemplar' => $eksemplar->copy_code,
                'jatuh_tempo' => $tanggalJatuhTempo,
                'sumber' => 'realisasi',
            ]);
            $this->catat($admin, 'booking.direalisasikan', 'booking', $booking->id, [
                'kode_booking' => $booking->booking_code,
                'kode_peminjaman' => $pinjam->loan_code,
            ]);

            return ['direalisasikan', $pinjam];
        });

        if ($hasil[0] === 'kedaluwarsa') {
            throw ValidationException::withMessages(['umum' => LayananKedaluwarsaBooking::PESAN_KEDALUWARSA.' Booking tidak dapat direalisasikan.']);
        }

        return $hasil[1];
    }

    // ------------------------------------------------------------------ Bantuan

    /** Eksemplar terkunci dapat dipesan: Tersedia, tanpa peminjaman Aktif, tanpa booking Disetujui. */
    private function dapatDipesan(BookCopy $eksemplar): bool
    {
        return $eksemplar->status === StatusEksemplar::Tersedia && ! $this->dimilikiTransaksiLain($eksemplar);
    }

    private function dimilikiTransaksiLain(BookCopy $eksemplar): bool
    {
        return Loan::query()->where('copy_id', $eksemplar->id)->where('status', StatusPeminjaman::Aktif->value)->exists()
            || Booking::query()->where('copy_id', $eksemplar->id)->where('status', StatusBooking::Disetujui->value)->exists();
    }
}
