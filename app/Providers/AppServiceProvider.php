<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Phase 8B — otorisasi halaman berlapis (selain middleware peran):
        // hanya akun yang layak masuk dan berperan sama dengan halaman.
        Gate::define('buka-halaman', fn (User $akun, string $peran): bool => $akun->role?->value === $peran && $akun->bolehMasuk());

        // Phase 8C — mutasi kredensial (buat akses login, reset kata sandi Anggota,
        // ganti kata sandi sendiri) hanya oleh Admin yang layak masuk.
        Gate::define('kelola-kredensial', fn (User $akun): bool => $akun->isAdmin() && $akun->bolehMasuk());

        // Phase 9A — tulis data Anggota (tambah, ubah profil, aktif/nonaktif): Admin yang layak masuk.
        Gate::define('kelola-anggota', fn (User $akun): bool => $akun->isAdmin() && $akun->bolehMasuk());

        // Phase 9B — tulis data Buku & eksemplar (tambah, ubah data buku, tambah eksemplar): Admin yang layak masuk.
        Gate::define('kelola-buku', fn (User $akun): bool => $akun->isAdmin() && $akun->bolehMasuk());

        // Batch 2 — peminjaman, pengembalian, verifikasi & realisasi booking: Admin yang layak masuk.
        Gate::define('kelola-transaksi', fn (User $akun): bool => $akun->isAdmin() && $akun->bolehMasuk());

        // Batch 2 — ajukan/batalkan booking milik sendiri: akun Anggota yang layak masuk dan tertaut ke data Anggota.
        Gate::define('booking-anggota', fn (User $akun): bool => $akun->role?->value === 'anggota' && $akun->bolehMasuk() && $akun->member !== null);

        // Phase 8D — ganti kata sandi sendiri: akun mana pun yang layak masuk.
        Gate::define('ubah-kata-sandi-sendiri', fn (User $akun): bool => $akun->bolehMasuk());
    }
}
