<?php

use App\Http\Controllers\Auth\SesiController;
use App\Http\Controllers\HalamanController;
use App\Support\NavigasiDemo;
use Illuminate\Support\Facades\Route;

// Uji integrasi teknis Laravel → Inertia → Vue → Vite (Task 00). Jangan dihapus.
// Phase 8A: hanya di luar production (tidak memuat data aplikasi).
if (! app()->isProduction()) {
    Route::inertia('/hello/echo', 'HelloEcho')->name('hello.echo');
}

Route::redirect('/', '/login');

/*
|--------------------------------------------------------------------------
| Autentikasi (Phase 8A)
|--------------------------------------------------------------------------
| Username + kata sandi terhadap tabel `users` MySQL, guard `web`, sesi server.
| Tanpa registrasi mandiri dan tanpa "Ingat saya".
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [SesiController::class, 'create'])->name('login');
    Route::post('/login', [SesiController::class, 'store'])->name('login.store');
});

Route::post('/logout', [SesiController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Rute aplikasi terlindungi
|--------------------------------------------------------------------------
|   /admin/*    — hanya akun berperan admin   (nama rute admin.*)
|   /anggota/*  — hanya akun berperan anggota (nama rute anggota.*)
| Setiap request: wajib masuk (auth), sesi terikat hash kata sandi
| (auth.session), kelayakan akun diperiksa ulang dari DB (akun.aktif), lalu
| peran dicek di server (peran:*). Daftar halaman: App\Support\NavigasiDemo.
*/
Route::middleware(['auth', 'auth.session', 'akun.aktif'])->group(function () {
    foreach (NavigasiDemo::PERAN as $peran) {
        Route::prefix($peran)
            ->name("{$peran}.")
            ->middleware("peran:{$peran}")
            ->group(function () use ($peran) {
                foreach (NavigasiDemo::halaman($peran) as $kunci => $halaman) {
                    // Phase 8B: data halaman dibaca dari MySQL oleh HalamanController.
                    Route::get($halaman['uri'], HalamanController::class)
                        ->defaults('peran', $peran)
                        ->defaults('halaman', $kunci)
                        ->name($kunci)
                        ->where(['kodeAnggota' => NavigasiDemo::POLA_KODE, 'kodeBuku' => NavigasiDemo::POLA_KODE]);
                }
            });
    }

    // Halaman pengembang: katalog komponen UI. Tidak ditautkan dari navigasi; khusus Admin, di luar production.
    if (! app()->isProduction()) {
        Route::inertia('admin/komponen', 'Sistem/PratinjauKomponen')->middleware('peran:admin')->name('admin.komponen');
    }
});

/*
|--------------------------------------------------------------------------
| Kompatibilitas tautan lama /demo/* (prototipe pra-Phase 8A)
|--------------------------------------------------------------------------
| Hanya mengalihkan ke rute baru; TIDAK menyajikan halaman. Rute tujuan tetap
| wajib masuk dan peran yang sesuai, sehingga tidak ada jalan pintas demo.
*/
Route::get('/demo/komponen', fn () => redirect('/admin/komponen'));
Route::get('/demo/{peran}/{jalur?}', fn (string $peran, string $jalur = '') => redirect(url("/{$peran}/{$jalur}")))
    ->where(['peran' => implode('|', NavigasiDemo::PERAN), 'jalur' => '.*']);
