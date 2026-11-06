/**
 * Pengguna (akun) — SRS 6.1 `users`, Class Diagram `Pengguna`.
 *
 * Prototipe memakai dua peran: `admin` dan `anggota`. Kata sandi tidak disimpan
 * (tidak ada autentikasi). Identitas Pengguna untuk Masuk:
 *  - Admin  : `username`
 *  - Anggota: Kode Anggota (lihat members.js) — metode final masih TBD-02.
 *
 * idPengguna USR-002 dan USR-003 mengikuti Object Diagram (Anggota A dan B).
 */
import { bekukan } from './util.js';

export const PENGGUNA = bekukan([
    { idPengguna: 'USR-001', username: 'admin', nama: 'Rina Wulandari', peran: 'admin', statusAkun: 'Aktif' },
    { idPengguna: 'USR-002', username: 'AG-001', nama: 'Nadia Rahmawati', peran: 'anggota', statusAkun: 'Aktif' },
    { idPengguna: 'USR-003', username: 'AG-002', nama: 'Rizky Pratama', peran: 'anggota', statusAkun: 'Aktif' },
    { idPengguna: 'USR-004', username: 'AG-003', nama: 'Siti Aisyah Putri', peran: 'anggota', statusAkun: 'Aktif' },
    { idPengguna: 'USR-005', username: 'AG-004', nama: 'Bagas Saputra', peran: 'anggota', statusAkun: 'Aktif' },
    { idPengguna: 'USR-006', username: 'AG-005', nama: 'Intan Permatasari', peran: 'anggota', statusAkun: 'Aktif' },
    { idPengguna: 'USR-007', username: 'AG-006', nama: 'Fajar Nugroho', peran: 'anggota', statusAkun: 'Aktif' },
    { idPengguna: 'USR-008', username: 'AG-007', nama: 'Melati Kusuma', peran: 'anggota', statusAkun: 'Nonaktif' },
    { idPengguna: 'USR-009', username: 'AG-008', nama: 'Yoga Aditya', peran: 'anggota', statusAkun: 'Aktif' },
    { idPengguna: 'USR-010', username: 'AG-009', nama: 'Larasati Wibowo', peran: 'anggota', statusAkun: 'Nonaktif' },
    { idPengguna: 'USR-011', username: 'AG-010', nama: 'Rafi Ananda', peran: 'anggota', statusAkun: 'Aktif' },
]);

/** idPengguna akun Admin yang dipakai di halaman Masuk pratinjau. */
export const ID_ADMIN_DEMO = 'USR-001';
