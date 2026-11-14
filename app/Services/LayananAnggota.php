<?php

namespace App\Services;

use App\Enums\StatusAnggota;
use App\Enums\StatusBooking;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 9A — mutasi data Anggota oleh Admin: tambah (opsional dengan akses
 * login), ubah profil, aktifkan/nonaktifkan.
 *
 * Aturan:
 * - Semua dalam transaksi; audit ditulis di transaksi yang sama sehingga tidak
 *   ada catatan "berhasil" bila transaksi gagal.
 * - Kode Anggota diisi Admin (tidak dibangkitkan); keunikan dijaga validasi dan
 *   UNIQUE `uq_members_member_code` (bentrok balapan → galat validasi, bukan SQL).
 * - Akses login saat tambah memakai KredensialAkun (Phase 8C/8D): role=anggota,
 *   name=NULL, is_active=true, must_change_password=true. Gagal → Anggota ikut
 *   di-rollback.
 * - Ubah profil hanya nama, kontak, status. Kode Anggota, akun tertaut,
 *   username, dan hash kata sandi TIDAK pernah diubah di sini.
 * - Satu Status Anggota (D-03). Penonaktifan mengikuti F-01 opsi A: ditolak bila
 *   Anggota masih memiliki booking `Disetujui`. Tidak ada booking/peminjaman
 *   yang dibatalkan atau diubah.
 *
 * Urutan kunci (desain Phase 7 §7): member → copy → booking → loan → return.
 * Penonaktifan mengunci baris `members` (FOR UPDATE) SEBELUM memeriksa booking.
 * Persetujuan booking (Phase 11) WAJIB mengunci baris member yang sama lebih
 * dulu, sehingga tidak ada persetujuan yang lolos di sela pemeriksaan & commit.
 */
class LayananAnggota
{
    public const PESAN_F01 = 'Anggota tidak dapat dinonaktifkan karena masih memiliki booking Disetujui. Selesaikan booking tersebut terlebih dahulu.';

    public const PESAN_KODE_DIPAKAI = 'Kode Anggota sudah digunakan anggota lain.';

    public function __construct(private readonly KredensialAkun $kredensial) {}

    /**
     * @param  array{kodeAnggota: string, nama: string, kontak: string, status: string}  $profil
     * @param  array{username: string, password: string}|null  $akses  null = tanpa akun login
     */
    public function tambah(array $profil, ?array $akses, User $aktor): Member
    {
        return DB::transaction(function () use ($profil, $akses, $aktor) {
            $anggota = new Member;
            $anggota->forceFill([
                'member_code' => $profil['kodeAnggota'],
                'name' => $profil['nama'],
                'contact' => $profil['kontak'],
                'status' => StatusAnggota::from($profil['status']),
                'user_id' => null,
            ]);

            try {
                $anggota->save();
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    throw ValidationException::withMessages(['kodeAnggota' => self::PESAN_KODE_DIPAKAI]);
                }
                throw $e;
            }

            $this->catat($aktor, 'anggota.dibuat', $anggota, ['kode_anggota' => $anggota->member_code, 'status' => $anggota->status->value]);

            if ($akses !== null) {
                // Transaksi bersarang (savepoint); kegagalan membatalkan Anggota juga.
                $this->kredensial->buatAksesLogin($anggota->member_code, $akses['username'], $akses['password'], $aktor);
            }

            return $anggota->refresh();
        });
    }

    /**
     * @param  array{nama: string, kontak: string, status: string}  $profil
     */
    public function ubah(string $kodeAnggota, array $profil, User $aktor): Member
    {
        return DB::transaction(function () use ($kodeAnggota, $profil, $aktor) {
            // Kunci member terlebih dahulu (urutan kunci standar), lalu baca ulang data terkini.
            $anggota = Member::query()->where('member_code', $kodeAnggota)->lockForUpdate()->firstOrFail();

            $statusLama = $anggota->status;
            $statusBaru = StatusAnggota::from($profil['status']);

            if ($statusLama === StatusAnggota::Aktif && $statusBaru === StatusAnggota::Nonaktif && $this->punyaBookingDisetujui($anggota)) {
                throw ValidationException::withMessages(['status' => self::PESAN_F01]);
            }

            $anggota->forceFill(['name' => $profil['nama'], 'contact' => $profil['kontak'], 'status' => $statusBaru]);
            $berubah = array_keys($anggota->getDirty());

            if ($berubah === []) {
                return $anggota;
            }

            $anggota->save();

            $kolomProfil = array_values(array_intersect($berubah, ['name', 'contact']));
            if ($kolomProfil !== []) {
                // Hanya nama kolom yang berubah — bukan nilainya (minim data pribadi di audit).
                $this->catat($aktor, 'anggota.diubah', $anggota, ['kode_anggota' => $anggota->member_code, 'kolom' => $kolomProfil]);
            }

            if ($statusLama !== $statusBaru) {
                $this->catat($aktor, $statusBaru === StatusAnggota::Nonaktif ? 'anggota.dinonaktifkan' : 'anggota.diaktifkan', $anggota, ['kode_anggota' => $anggota->member_code]);

                if ($statusBaru === StatusAnggota::Nonaktif && $anggota->user_id !== null) {
                    // Sesi lama ditolak middleware akun.aktif; baris sesi database juga dibersihkan.
                    $this->hapusSesi($anggota->user_id);
                }
            }

            return $anggota;
        });
    }

    /** F-01: booking Disetujui milik anggota (dibaca SETELAH baris member dikunci). */
    private function punyaBookingDisetujui(Member $anggota): bool
    {
        return Booking::query()
            ->where('member_id', $anggota->id)
            ->where('status', StatusBooking::Disetujui->value)
            ->exists();
    }

    private function hapusSesi(int $idAkun): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $idAkun)
            ->delete();
    }

    /** Audit: aksi, aktor, subjek, kode — tanpa kata sandi, hash, token, atau kontak. */
    private function catat(User $aktor, string $aksi, Member $anggota, array $metadata): void
    {
        (new AuditLog)->forceFill([
            'actor_id' => $aktor->id,
            'action' => $aksi,
            'subject_type' => 'member',
            'subject_id' => $anggota->id,
            'metadata' => $metadata,
        ])->save();
    }
}
