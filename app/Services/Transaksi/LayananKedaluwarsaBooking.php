<?php

namespace App\Services\Transaksi;

use App\Enums\StatusBooking;
use App\Enums\StatusEksemplar;
use App\Enums\StatusPeminjaman;
use App\Models\BookCopy;
use App\Models\Booking;
use App\Models\Loan;
use App\Models\Member;
use App\Support\WaktuPerpustakaan;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Batch 2 — kedaluwarsa booking (kebijakan disetujui).
 *
 * - Booking Disetujui kedaluwarsa TEPAT 48 jam setelah persetujuan:
 *   expires_at = decided_at + 48 jam (instant UTC). Kedaluwarsa bila
 *   expires_at <= sekarang (tepat pada batas sudah kedaluwarsa).
 * - Transisi: status → Kedaluwarsa, expired_at = waktu pemrosesan (UTC),
 *   reservasi dilepas (reserved_copy_id generated → NULL). Eksemplar kembali
 *   Tersedia HANYA bila masih Dipesan, tanpa peminjaman Aktif, dan tanpa booking
 *   Disetujui lain — tidak pernah melepas eksemplar milik transaksi lain.
 * - Audit `booking.kedaluwarsa` dengan aktor NULL (kejadian sistem; tidak
 *   menyamar sebagai Admin). Metadata `pemicu` mencatat asal pemrosesan.
 * - Idempoten: booking yang sudah tidak Disetujui / belum jatuh tempo dilewati
 *   tanpa audit.
 *
 * Urutan kunci: member → booking → copy (→ cek loan).
 */
class LayananKedaluwarsaBooking extends DasarTransaksi
{
    public const JAM_BATAS_PENGAMBILAN = 48;

    public const BATCH_BAWAAN = 100;

    public const PESAN_KEDALUWARSA = 'Booking ini sudah melewati Batas Pengambilan (48 jam sejak disetujui) dan kini berstatus Kedaluwarsa. Reservasi eksemplar telah dilepas.';

    public static function batasPengambilan(CarbonImmutable $diputuskan): CarbonImmutable
    {
        return $diputuskan->utc()->addHours(self::JAM_BATAS_PENGAMBILAN);
    }

    public static function sudahLewat(Booking $booking, CarbonImmutable $sekarang): bool
    {
        return $booking->status === StatusBooking::Disetujui
            && $booking->expires_at !== null
            && $booking->expires_at->lessThanOrEqualTo($sekarang);
    }

    /**
     * Terapkan kedaluwarsa pada booking yang SUDAH dikunci bersama baris member-nya
     * (dipanggil dari dalam transaksi realisasi/pembatalan/kedaluwarsa).
     */
    public function terapkanTerkunci(Booking $booking, CarbonImmutable $sekarang, string $pemicu): void
    {
        $eksemplar = BookCopy::query()->whereKey($booking->copy_id)->lockForUpdate()->firstOrFail();

        $booking->forceFill(['status' => StatusBooking::Kedaluwarsa, 'expired_at' => $sekarang])->save();

        $dilepas = false;
        if ($eksemplar->status === StatusEksemplar::Dipesan && ! $this->dimilikiTransaksiLain($eksemplar)) {
            $eksemplar->forceFill(['status' => StatusEksemplar::Tersedia])->save();
            $dilepas = true;
        }

        $this->catat(null, 'booking.kedaluwarsa', 'booking', $booking->id, [
            'kode_booking' => $booking->booking_code,
            'kode_eksemplar' => $eksemplar->copy_code,
            'batas_pengambilan' => $booking->expires_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'eksemplar_dilepas' => $dilepas,
            'pemicu' => $pemicu,
        ]);
    }

    /**
     * Proses satu booking (transaksi sendiri). Mengembalikan true bila booking
     * dikedaluwarsakan, false bila dilewati (sudah diproses / belum jatuh tempo).
     */
    public function kedaluwarsakan(int $idBooking, string $pemicu = 'penjadwal', ?CarbonImmutable $sekarang = null): bool
    {
        $sekarang ??= WaktuPerpustakaan::sekarangUtc();

        return $this->transaksi(function () use ($idBooking, $pemicu, $sekarang) {
            $memberId = Booking::query()->whereKey($idBooking)->value('member_id');
            if ($memberId === null) {
                return false;
            }

            Member::query()->whereKey($memberId)->lockForUpdate()->first();
            $booking = Booking::query()->whereKey($idBooking)->lockForUpdate()->first();

            if ($booking === null || ! self::sudahLewat($booking, $sekarang)) {
                return false; // idempoten: sudah diproses transaksi lain atau belum jatuh tempo
            }

            $this->terapkanTerkunci($booking, $sekarang, $pemicu);

            return true;
        });
    }

    /**
     * Rekonsiliasi sebelum transaksi lain memakai eksemplar: bila eksemplar
     * direservasi booking Disetujui yang sudah lewat batas, kedaluwarsakan dulu
     * (transaksi terpisah, urutan kunci standar). Tidak melakukan apa pun bila
     * tidak ada booking jatuh tempo.
     */
    public function rekonsiliasiEksemplar(int $idEksemplar, string $pemicu): void
    {
        $sekarang = WaktuPerpustakaan::sekarangUtc();
        $jatuhTempo = Booking::query()
            ->where('copy_id', $idEksemplar)
            ->where('status', StatusBooking::Disetujui->value)
            ->where('expires_at', '<=', $sekarang)
            ->value('id');

        if ($jatuhTempo !== null) {
            $this->kedaluwarsakan((int) $jatuhTempo, $pemicu, $sekarang);
        }
    }

    /** Rekonsiliasi semua booking Disetujui milik satu anggota yang sudah lewat batas (mis. sebelum penonaktifan). */
    public function rekonsiliasiAnggota(int $idAnggota, string $pemicu): void
    {
        $sekarang = WaktuPerpustakaan::sekarangUtc();
        $jatuhTempo = Booking::query()
            ->where('member_id', $idAnggota)
            ->where('status', StatusBooking::Disetujui->value)
            ->where('expires_at', '<=', $sekarang)
            ->pluck('id');

        foreach ($jatuhTempo as $id) {
            $this->kedaluwarsakan((int) $id, $pemicu, $sekarang);
        }
    }

    /**
     * Proses semua booking jatuh tempo per batch (tanpa memuat seluruh riwayat).
     * Kegagalan satu booking dicatat dan TIDAK menghentikan booking lain; booking
     * yang gagal tidak diulang dalam proses yang sama (dicoba lagi pada jadwal berikutnya).
     *
     * @return array{diproses: int, dilewati: int, gagal: int}
     */
    public function prosesJatuhTempo(int $ukuranBatch = self::BATCH_BAWAAN, ?CarbonImmutable $sekarang = null, int $maksBatch = 50): array
    {
        $sekarang ??= WaktuPerpustakaan::sekarangUtc();
        $hasil = ['diproses' => 0, 'dilewati' => 0, 'gagal' => 0];
        $gagal = [];

        for ($putaran = 0; $putaran < $maksBatch; $putaran++) {
            $id = Booking::query()
                ->where('status', StatusBooking::Disetujui->value)
                ->where('expires_at', '<=', $sekarang)
                ->when($gagal !== [], fn ($q) => $q->whereNotIn('id', $gagal))
                ->orderBy('expires_at')->orderBy('id')
                ->limit(max(1, $ukuranBatch))
                ->pluck('id');

            if ($id->isEmpty()) {
                break;
            }

            foreach ($id as $idBooking) {
                try {
                    // $sekarang hanya batas seleksi; expired_at memakai waktu pemrosesan aktual tiap item.
                    $this->kedaluwarsakan((int) $idBooking, 'penjadwal') ? $hasil['diproses']++ : $hasil['dilewati']++;
                } catch (Throwable $e) {
                    $hasil['gagal']++;
                    $gagal[] = (int) $idBooking;
                    // Tanpa data pribadi/kredensial: hanya ID booking & jenis galat.
                    Log::warning('bookings:expire gagal memproses booking', [
                        'booking_id' => (int) $idBooking,
                        'galat' => $e::class,
                        'kode' => $e instanceof QueryException ? ($e->errorInfo[1] ?? null) : null,
                    ]);
                }
            }
        }

        return $hasil;
    }

    /** Eksemplar masih dimiliki transaksi sah lain: peminjaman Aktif atau booking Disetujui lain. */
    private function dimilikiTransaksiLain(BookCopy $eksemplar): bool
    {
        return Loan::query()->where('copy_id', $eksemplar->id)->where('status', StatusPeminjaman::Aktif->value)->exists()
            || Booking::query()->where('copy_id', $eksemplar->id)->where('status', StatusBooking::Disetujui->value)->exists();
    }
}
