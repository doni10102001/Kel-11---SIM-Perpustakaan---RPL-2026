<?php

use App\Support\NavigasiDemo;
use Illuminate\Support\Facades\Route;

// Uji integrasi teknis Laravel → Inertia → Vue → Vite (Task 00). Jangan dihapus.
Route::inertia('/hello/echo', 'HelloEcho')->name('hello.echo');

Route::redirect('/', '/login');

// Halaman Masuk bersama — pratinjau statis, BUKAN autentikasi.
Route::inertia('/login', 'Auth/Login')->name('login');

/*
|--------------------------------------------------------------------------
| Rute pratinjau (demo)
|--------------------------------------------------------------------------
| Dipisahkan di bawah /demo dari rute produksi. Dua peran prototipe:
|   /demo/admin/*    — halaman pengelolaan perpustakaan
|   /demo/anggota/*  — halaman layanan anggota
| Tidak ada autentikasi, sesi login, maupun otorisasi server pada tahap ini.
| Daftar halaman dikelola terpusat di App\Support\NavigasiDemo.
*/
Route::prefix('demo')->name('demo.')->group(function () {
    foreach (NavigasiDemo::PERAN as $peran) {
        Route::prefix($peran)
            ->name("{$peran}.")
            ->group(function () use ($peran) {
                foreach (NavigasiDemo::halaman($peran) as $kunci => $halaman) {
                    Route::inertia($halaman['uri'], $halaman['komponen'])->name($kunci);
                }
            });
    }

    // Halaman pengembang: katalog komponen UI. Tidak ditautkan dari navigasi.
    Route::inertia('komponen', 'Sistem/PratinjauKomponen')->name('komponen');
});
