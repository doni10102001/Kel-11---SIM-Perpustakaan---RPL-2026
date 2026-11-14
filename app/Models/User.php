<?php

namespace App\Models;

use App\Enums\Peran;
use App\Enums\StatusAnggota;
use App\Models\Concerns\SimpanWaktuUtc;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Akun login (tabel `users`). Peran: Admin atau Anggota.
 *
 * - `role` dan `is_active` sengaja TIDAK fillable: hanya diisi eksplisit oleh
 *   proses yang berwenang (seeder/service Admin), bukan dari input form.
 * - `is_active` hanya bermakna untuk Admin; Anggota mengikuti `members.status`
 *   (D-03, dijaga CHECK chk_users_anggota_tanpa_status_akses).
 * - `name` hanya untuk identitas Admin (D-01). Akun Anggota wajib name = NULL;
 *   nama Anggota bersumber dari `members.name` (CHECK chk_users_nama_sesuai_peran).
 *   Untuk tampilan pakai atribut `nama_tampilan`, bukan `name`.
 */
#[Fillable(['username', 'name', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SimpanWaktuUtc;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Peran::class,
            'is_active' => 'boolean',
        ];
    }

    /** Data Anggota yang memakai akun ini (0..1). */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    public function bookingsDecided(): HasMany
    {
        return $this->hasMany(Booking::class, 'staff_id');
    }

    public function loansProcessed(): HasMany
    {
        return $this->hasMany(Loan::class, 'staff_id');
    }

    public function returnsProcessed(): HasMany
    {
        return $this->hasMany(BookReturn::class, 'staff_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    /**
     * Nama untuk ditampilkan (Class Diagram `Pengguna.nama`).
     * Admin: users.name. Anggota: members.name (satu sumber, D-01).
     * Hanya-baca; tidak pernah ditulis kembali ke users.name.
     *
     * @return Attribute<string|null, never>
     */
    protected function namaTampilan(): Attribute
    {
        return Attribute::get(fn (): ?string => match ($this->role) {
            Peran::Admin => $this->name,
            Peran::Anggota => $this->member?->name,
            default => null,
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === Peran::Admin;
    }

    public function isAnggota(): bool
    {
        return $this->role === Peran::Anggota;
    }

    /**
     * Kelayakan login berdasarkan data (dipakai autentikasi Phase 8).
     * Admin: is_active. Anggota: harus terhubung ke tepat satu Anggota berstatus Aktif.
     * Verifikasi kata sandi TIDAK dilakukan di sini.
     */
    public function bolehMasuk(): bool
    {
        return match ($this->role) {
            Peran::Admin => $this->is_active,
            Peran::Anggota => $this->member?->status === StatusAnggota::Aktif,
            default => false,
        };
    }
}
