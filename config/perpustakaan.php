<?php

/*
 * Konfigurasi domain SIM Perpustakaan (Phase 7A).
 *
 * Kata sandi seeder hanya dibaca dari environment lokal (.env yang tidak
 * di-commit). Tidak ada kata sandi bawaan di source code.
 */

return [

    /*
     * Zona waktu TAMPILAN dan kalender bisnis (D-02). Penyimpanan dan
     * pemrosesan timestamp memakai UTC (config/app.php). Zona ini dipakai untuk
     * "hari ini", batas hari filter laporan, dan kolom tanggal-saja (DATE).
     * Lihat App\Support\WaktuPerpustakaan. Asia/Jakarta = WIB, UTC+7, tanpa DST.
     */
    'zona_waktu_tampilan' => 'Asia/Jakarta',

    'seed' => [
        // Provisioning kredensial Admin awal (D-08): wajib saat menjalankan
        // DemoPerpustakaanSeeder; panjang 6–64 karakter (aturan L.3). Hanya dipakai
        // saat akun Admin BELUM ada; setelah seeding pertama boleh dihapus dari .env.
        'admin_password' => env('SEED_ADMIN_PASSWORD'),
    ],

];
