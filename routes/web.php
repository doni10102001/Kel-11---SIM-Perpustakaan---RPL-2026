<?php

use App\Http\Controllers\AnggotaController;
use App\Http\Controllers\Auth\SesiController;
use App\Http\Controllers\BukuController;
use App\Http\Controllers\HalamanController;
use App\Http\Controllers\Kredensial\AksesLoginAnggotaController;
use App\Http\Controllers\Kredensial\KataSandiController;
use App\Http\Controllers\Transaksi\BookingAdminController;
use App\Http\Controllers\Transaksi\BookingAnggotaController;
use App\Http\Controllers\Transaksi\PeminjamanController;
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
| (auth.session), kelayakan akun diperiksa ulang dari DB (akun.aktif), kewajiban
| ganti kata sandi Anggota (wajib.ganti.sandi, Phase 8D), lalu peran dicek di
| server (peran:*). Daftar halaman: App\Support\NavigasiDemo.
*/
// Phase 8D: wajib.ganti.sandi — Anggota yang wajib ganti kata sandi hanya dapat membuka /anggota/kata-sandi.
Route::middleware(['auth', 'auth.session', 'akun.aktif', 'wajib.ganti.sandi'])->group(function () {
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

    // Phase 8C — mutasi kredensial (Admin saja; CSRF dari grup web; Form Request + Gate kelola-kredensial).
    // Phase 9A — tulis data Anggota (Form Request + Gate kelola-anggota + LayananAnggota).
    // Phase 9B — tulis data Buku & eksemplar.
    Route::prefix('admin')->name('admin.')->middleware('peran:admin')->group(function () {
        Route::post('anggota', [AnggotaController::class, 'store'])->middleware('throttle:30,1')->name('anggota.store');
        Route::put('anggota/{kodeAnggota}', [AnggotaController::class, 'update'])
            ->where('kodeAnggota', NavigasiDemo::POLA_KODE)->middleware('throttle:30,1')->name('anggota.update');

        // Phase 9B — tulis data Buku & eksemplar (Form Request + Gate kelola-buku + LayananBuku). Tanpa rute hapus.
        Route::post('buku', [BukuController::class, 'store'])->middleware('throttle:30,1')->name('buku.store');
        Route::put('buku/{kodeBuku}', [BukuController::class, 'update'])
            ->where('kodeBuku', NavigasiDemo::POLA_KODE)->middleware('throttle:30,1')->name('buku.update');

        // Batch 2 — transaksi (Form Request/Gate kelola-transaksi + service transaksi terkunci).
        Route::post('peminjaman', [PeminjamanController::class, 'store'])->middleware('throttle:30,1')->name('peminjaman.store');
        Route::post('pengembalian', [PeminjamanController::class, 'kembalikan'])->middleware('throttle:30,1')->name('pengembalian.store');
        Route::post('booking/{kodeBooking}/setujui', [BookingAdminController::class, 'setujui'])
            ->where('kodeBooking', NavigasiDemo::POLA_KODE)->middleware('throttle:30,1')->name('booking.setujui');
        Route::post('booking/{kodeBooking}/tolak', [BookingAdminController::class, 'tolak'])
            ->where('kodeBooking', NavigasiDemo::POLA_KODE)->middleware('throttle:30,1')->name('booking.tolak');
        Route::post('booking/{kodeBooking}/realisasi', [BookingAdminController::class, 'realisasikan'])
            ->where('kodeBooking', NavigasiDemo::POLA_KODE)->middleware('throttle:30,1')->name('booking.realisasikan');

        Route::post('anggota/{kodeAnggota}/akses-login', [AksesLoginAnggotaController::class, 'store'])
            ->where('kodeAnggota', NavigasiDemo::POLA_KODE)->middleware('throttle:20,1')->name('anggota.akses-login.store');
        Route::put('anggota/{kodeAnggota}/kata-sandi', [AksesLoginAnggotaController::class, 'resetKataSandi'])
            ->where('kodeAnggota', NavigasiDemo::POLA_KODE)->middleware('throttle:20,1')->name('anggota.kata-sandi.reset');
        // Verifikasi kata sandi saat ini dibatasi 6 percobaan/menit per akun.
        Route::put('kata-sandi', [KataSandiController::class, 'update'])->middleware('throttle:6,1')->name('kata-sandi.update');
    });

    // Phase 8D — Anggota mengganti kata sandinya sendiri (termasuk kewajiban pada login pertama).
    Route::prefix('anggota')->name('anggota.')->middleware('peran:anggota')->group(function () {
        Route::put('kata-sandi', [KataSandiController::class, 'update'])->middleware('throttle:6,1')->name('kata-sandi.update');

        // Batch 2 — booking milik sendiri (pemilik dari sesi; Gate booking-anggota).
        Route::post('booking', [BookingAnggotaController::class, 'store'])->middleware('throttle:20,1')->name('booking.store');
        Route::post('booking/{kodeBooking}/batalkan', [BookingAnggotaController::class, 'batalkan'])
            ->where('kodeBooking', NavigasiDemo::POLA_KODE)->middleware('throttle:20,1')->name('booking.batalkan');
    });

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
