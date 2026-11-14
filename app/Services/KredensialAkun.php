<?php

namespace App\Services;

use App\Enums\Peran;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Phase 8C — mutasi kredensial: buat akses login Anggota, reset kata sandi
 * Anggota oleh Admin, dan ganti kata sandi Admin sendiri.
 *
 * Aturan:
 * - Semua perubahan dalam transaksi; baris Anggota dikunci (lockForUpdate)
 *   sehingga dua permintaan bersamaan tidak dapat membuat dua akun.
 * - Nilai istimewa (role, name, is_active, user_id) ditetapkan di sini, tidak
 *   pernah dari input klien. Akun Anggota: role=anggota, name=NULL (D-01),
 *   is_active=true (D-03). Status Anggota TIDAK diubah.
 * - Kata sandi di-hash oleh cast `hashed` model User; tidak pernah dicatat,
 *   dikembalikan, atau dimasukkan ke audit log (audit hanya aksi + aktor).
 * - Setelah kata sandi berubah, sesi lain akun tersebut berakhir: baris sesi
 *   database dihapus dan middleware auth.session menolak sesi bertanda hash lama.
 */
class KredensialAkun
{
    public const PESAN_USERNAME_DIPAKAI = 'Username sudah digunakan. Gunakan username lain.';

    public const PESAN_SUDAH_PUNYA_AKUN = 'Anggota ini sudah memiliki akses login. Satu anggota hanya dapat memiliki satu akun login.';

    public const PESAN_BELUM_PUNYA_AKUN = 'Anggota ini belum memiliki akses login. Buat akses login terlebih dahulu.';

    public function buatAksesLogin(string $kodeAnggota, string $username, string $sandi, User $aktor): User
    {
        return DB::transaction(function () use ($kodeAnggota, $username, $sandi, $aktor) {
            $anggota = $this->kunciAnggota($kodeAnggota);

            if ($anggota->user_id !== null) {
                throw ValidationException::withMessages(['akses' => self::PESAN_SUDAH_PUNYA_AKUN]);
            }

            $akun = new User;
            $akun->forceFill([
                'username' => $username,
                'name' => null,
                'password' => $sandi,
                'role' => Peran::Anggota,
                'is_active' => true,
            ]);

            try {
                $akun->save();
            } catch (QueryException $e) {
                // Balapan dengan permintaan lain yang memakai username sama (uq_users_username).
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    throw ValidationException::withMessages(['username' => self::PESAN_USERNAME_DIPAKAI]);
                }
                throw $e;
            }

            $anggota->forceFill(['user_id' => $akun->id])->save();

            $this->catat($aktor, 'anggota.akses_login.dibuat', 'member', $anggota->id, ['kode_anggota' => $anggota->member_code, 'username' => $akun->username]);

            return $akun;
        });
    }

    public function resetKataSandiAnggota(string $kodeAnggota, string $sandi, User $aktor): User
    {
        return DB::transaction(function () use ($kodeAnggota, $sandi, $aktor) {
            $anggota = $this->kunciAnggota($kodeAnggota);
            $akun = $anggota->user_id ? User::query()->lockForUpdate()->find($anggota->user_id) : null;

            if ($akun === null || ! $akun->isAnggota()) {
                throw ValidationException::withMessages(['akses' => self::PESAN_BELUM_PUNYA_AKUN]);
            }

            $this->gantiSandi($akun, $sandi);
            $this->akhiriSesi($akun);

            $this->catat($aktor, 'anggota.kata_sandi.direset', 'member', $anggota->id, ['kode_anggota' => $anggota->member_code]);

            return $akun;
        });
    }

    /**
     * Ganti kata sandi akun yang sedang masuk. Sesi saat ini TETAP berlaku
     * (controller meregenerasi ID sesi; auth.session menyimpan hash baru),
     * semua sesi lain akun ini diakhiri.
     */
    public function gantiKataSandiSendiri(User $akun, string $sandiBaru, ?string $idSesiSaatIni): void
    {
        DB::transaction(function () use ($akun, $sandiBaru, $idSesiSaatIni) {
            $this->gantiSandi($akun, $sandiBaru);
            $this->akhiriSesi($akun, kecuali: $idSesiSaatIni);

            $this->catat($akun, 'akun.kata_sandi.diubah', 'user', $akun->id, []);
        });
    }

    private function kunciAnggota(string $kodeAnggota): Member
    {
        return Member::query()->where('member_code', $kodeAnggota)->lockForUpdate()->firstOrFail();
    }

    private function gantiSandi(User $akun, string $sandi): void
    {
        $akun->forceFill(['password' => $sandi]);
        // Token "ingat saya" (tidak dipakai, D-05) tetap diputar agar tidak ada sisa kredensial lama.
        $akun->setRememberToken(Str::random(60));
        $akun->save();
    }

    /** Hapus baris sesi database milik akun (bila driver sesi = database). */
    private function akhiriSesi(User $akun, ?string $kecuali = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $akun->id)
            ->when($kecuali, fn ($q) => $q->where('id', '<>', $kecuali))
            ->delete();
    }

    /** Audit: aksi + aktor + subjek. Tidak pernah memuat kata sandi atau hash. */
    private function catat(User $aktor, string $aksi, string $jenis, int $id, array $metadata): void
    {
        (new AuditLog)->forceFill([
            'actor_id' => $aktor->id,
            'action' => $aksi,
            'subject_type' => $jenis,
            'subject_id' => $id,
            'metadata' => $metadata === [] ? null : $metadata,
        ])->save();
    }
}
