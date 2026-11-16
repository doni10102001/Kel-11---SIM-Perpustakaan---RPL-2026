<?php

namespace App\Queries;

use App\Enums\StatusBooking;
use App\Models\Booking;
use App\Models\Loan;
use App\Models\Member;
use App\Support\WaktuPerpustakaan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Batch 3 — ringkasan dashboard dengan AGREGAT SQL (COUNT / SUM bersyarat /
 * GROUP BY). Tidak memuat tabel penuh hanya untuk menghitung; jumlah query
 * tetap (tidak bertambah seiring data).
 *
 * - Admin: seluruh perpustakaan.
 * - Anggota: HANYA data milik anggota sesi (member_id dari relasi akun di
 *   server, tidak pernah dari parameter request).
 * - "Hari ini" = kalender WIB, dikonversi ke rentang UTC setengah-terbuka
 *   (WaktuPerpustakaan::rentangHariUtc) — tanpa DATE(kolom).
 * - Daftar ringkas (peminjaman aktif, booking perlu tindak lanjut) dibatasi
 *   BATAS_DAFTAR baris dengan eager loading; total tetap dari agregat.
 */
class RingkasanPerpustakaan
{
    public const BATAS_DAFTAR = 10;

    /** @return array<string, mixed> */
    public function admin(): array
    {
        [$mulai, $sebelum] = WaktuPerpustakaan::rentangHariUtc(WaktuPerpustakaan::hariIni(), WaktuPerpustakaan::hariIni());

        $anggota = DB::table('members')->selectRaw("COUNT(*) AS total, COALESCE(SUM(status = 'Aktif'), 0) AS aktif, COALESCE(SUM(status = 'Nonaktif'), 0) AS nonaktif")->first();
        $buku = DB::table('books')->selectRaw('COUNT(*) AS total, COUNT(DISTINCT category) AS kategori')->first();
        $eksemplar = DB::table('book_copies')->selectRaw(
            "COUNT(*) AS total, COALESCE(SUM(status = 'Tersedia'), 0) AS tersedia, COALESCE(SUM(status = 'Dipesan'), 0) AS dipesan, COALESCE(SUM(status = 'Dipinjam'), 0) AS dipinjam"
        )->first();
        $pinjam = DB::table('loans')->selectRaw(
            "COALESCE(SUM(status = 'Aktif'), 0) AS aktif, COALESCE(SUM(status = 'Selesai'), 0) AS selesai, COALESCE(SUM(loaned_at >= ? AND loaned_at < ?), 0) AS hari_ini",
            [$mulai, $sebelum]
        )->first();
        $booking = $this->perStatusBooking(null);
        $bookingHariIni = DB::table('bookings')->where('submitted_at', '>=', $mulai)->where('submitted_at', '<', $sebelum)->count();
        $kembaliHariIni = DB::table('returns')->where('returned_at', '>=', $mulai)->where('returned_at', '<', $sebelum)->count();

        return [
            'ringkasan' => [
                'anggota' => ['total' => (int) $anggota->total, 'Aktif' => (int) $anggota->aktif, 'Nonaktif' => (int) $anggota->nonaktif],
                'buku' => ['total' => (int) $buku->total, 'kategori' => (int) $buku->kategori],
                'eksemplar' => [
                    'total' => (int) $eksemplar->total,
                    'Tersedia' => (int) $eksemplar->tersedia,
                    'Dipesan' => (int) $eksemplar->dipesan,
                    'Dipinjam' => (int) $eksemplar->dipinjam,
                ],
                'peminjaman' => ['Aktif' => (int) $pinjam->aktif, 'Selesai' => (int) $pinjam->selesai],
                'booking' => $booking,
                'hariIni' => ['peminjaman' => (int) $pinjam->hari_ini, 'pengembalian' => $kembaliHariIni, 'booking' => $bookingHariIni],
            ],
            'peminjamanAktif' => $this->barisPeminjamanAktif(null),
            'bookingBerjalan' => $this->barisBookingBerjalan(null),
        ];
    }

    /** @return array<string, mixed> */
    public function anggota(Member $anggota): array
    {
        $pinjam = DB::table('loans')->where('member_id', $anggota->id)
            ->selectRaw("COALESCE(SUM(status = 'Aktif'), 0) AS aktif, COALESCE(SUM(status = 'Selesai'), 0) AS selesai")->first();
        $booking = $this->perStatusBooking($anggota->id);

        return [
            'anggota' => [[
                'kodeAnggota' => $anggota->member_code,
                'idPengguna' => null,
                'nama' => $anggota->name,
                'kontak' => $anggota->contact,
                'status' => $anggota->status->value,
            ]],
            'ringkasan' => [
                'bookingAktif' => $booking['Diajukan'] + $booking['Disetujui'],
                'bookingDiajukan' => $booking['Diajukan'],
                'bookingDisetujui' => $booking['Disetujui'],
                'bookingKedaluwarsa' => $booking['Kedaluwarsa'],
                'peminjamanAktif' => (int) $pinjam->aktif,
                'riwayatSelesai' => (int) $pinjam->selesai,
            ],
            'peminjamanAktif' => $this->barisPeminjamanAktif($anggota->id),
            'bookingBerjalan' => $this->barisBookingBerjalan($anggota->id),
        ];
    }

    /** Jumlah booking per status (GROUP BY), semua status resmi selalu ada (0 bila kosong). */
    private function perStatusBooking(?int $idAnggota): array
    {
        $hitung = DB::table('bookings')
            ->when($idAnggota !== null, fn ($q) => $q->where('member_id', $idAnggota))
            ->groupBy('status')->selectRaw('status, COUNT(*) AS jumlah')->pluck('jumlah', 'status');

        $hasil = [];
        foreach (StatusBooking::cases() as $s) {
            $hasil[$s->value] = (int) ($hitung[$s->value] ?? 0);
        }

        return $hasil;
    }

    /** Peminjaman Aktif, jatuh tempo terdekat (Admin: maks. BATAS_DAFTAR; Anggota: semua miliknya). */
    private function barisPeminjamanAktif(?int $idAnggota): array
    {
        return Loan::query()
            ->with(['member:id,member_code,name', 'copy:id,book_id,copy_code', 'copy.book:id,title'])
            ->where('status', 'Aktif')
            ->when($idAnggota !== null, fn (Builder $q) => $q->where('member_id', $idAnggota), fn (Builder $q) => $q->limit(self::BATAS_DAFTAR))
            ->orderBy('due_date')->orderBy('loan_code')
            ->get()
            ->map(fn (Loan $p) => [
                'idPeminjaman' => $p->loan_code,
                'anggota' => ['kodeAnggota' => $p->member->member_code, 'nama' => $p->member->name],
                'buku' => ['judul' => $p->copy->book->title],
                'kodeEksemplar' => $p->copy->copy_code,
                'tanggalPinjam' => WaktuPerpustakaan::tanggalLokal($p->loaned_at),
                'tanggalJatuhTempo' => $p->getRawOriginal('due_date'),
                'status' => $p->status->value,
            ])->all();
    }

    /** Booking Diajukan lalu Disetujui (perlu tindak lanjut), terlama lebih dulu. */
    private function barisBookingBerjalan(?int $idAnggota): array
    {
        return Booking::query()
            ->with(['member:id,member_code,name', 'copy:id,book_id,copy_code', 'copy.book:id,title'])
            ->whereIn('status', [StatusBooking::Diajukan->value, StatusBooking::Disetujui->value])
            ->when($idAnggota !== null, fn (Builder $q) => $q->where('member_id', $idAnggota), fn (Builder $q) => $q->limit(self::BATAS_DAFTAR))
            ->orderByRaw("CASE status WHEN 'Diajukan' THEN 0 ELSE 1 END")
            ->orderBy($idAnggota === null ? 'submitted_at' : 'id', $idAnggota === null ? 'asc' : 'desc')
            ->get()
            ->map(fn (Booking $b) => [
                'idBooking' => $b->booking_code,
                'anggota' => ['kodeAnggota' => $b->member->member_code, 'nama' => $b->member->name],
                'buku' => ['judul' => $b->copy->book->title],
                'kodeEksemplar' => $b->copy->copy_code,
                'tanggalBooking' => WaktuPerpustakaan::tanggalLokal($b->submitted_at),
                'status' => $b->status->value,
                'batasPengambilan' => $b->expires_at ? WaktuPerpustakaan::isoUtc($b->expires_at) : null,
            ])->all();
    }
}
