/**
 * Anggota — SRS 6.1 `members` (member_code, name, contact, status),
 * Class Diagram `Anggota` (idAnggota, kontak, statusAnggota) + `Pengguna.nama`.
 *
 * Label UI "Kode Anggota" = `kodeAnggota` (= member_code = idAnggota).
 * Status: Aktif | Nonaktif. Anggota Nonaktif tetap disimpan beserta riwayatnya (FR-022).
 */
import { bekukan } from './util.js';

export const ANGGOTA = bekukan([
    { kodeAnggota: 'AG-001', idPengguna: 'USR-002', nama: 'Nadia Rahmawati', kontak: '0812-3456-7801', status: 'Aktif' },
    { kodeAnggota: 'AG-002', idPengguna: 'USR-003', nama: 'Rizky Pratama', kontak: '0813-2214-5502', status: 'Aktif' },
    { kodeAnggota: 'AG-003', idPengguna: 'USR-004', nama: 'Siti Aisyah Putri', kontak: '0857-1120-3303', status: 'Aktif' },
    { kodeAnggota: 'AG-004', idPengguna: 'USR-005', nama: 'Bagas Saputra', kontak: '0821-7765-4404', status: 'Aktif' },
    { kodeAnggota: 'AG-005', idPengguna: 'USR-006', nama: 'Intan Permatasari', kontak: '0838-5521-9805', status: 'Aktif' },
    { kodeAnggota: 'AG-006', idPengguna: 'USR-007', nama: 'Fajar Nugroho', kontak: '0812-9087-6606', status: 'Aktif' },
    { kodeAnggota: 'AG-007', idPengguna: 'USR-008', nama: 'Melati Kusuma', kontak: '0852-6634-1207', status: 'Nonaktif' },
    { kodeAnggota: 'AG-008', idPengguna: 'USR-009', nama: 'Yoga Aditya', kontak: '0819-4452-7708', status: 'Aktif' },
    { kodeAnggota: 'AG-009', idPengguna: 'USR-010', nama: 'Larasati Wibowo', kontak: '0877-3309-8809', status: 'Nonaktif' },
    { kodeAnggota: 'AG-010', idPengguna: 'USR-011', nama: 'Rafi Ananda', kontak: '0896-2201-4410', status: 'Aktif' },
]);

/** Kode Anggota yang dipakai di halaman Masuk pratinjau (akun Anggota). */
export const KODE_ANGGOTA_DEMO = 'AG-001';
