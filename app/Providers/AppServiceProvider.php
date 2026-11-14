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
    }
}
