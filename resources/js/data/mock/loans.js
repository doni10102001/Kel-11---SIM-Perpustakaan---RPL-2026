/**
 * Peminjaman — SRS 6.1 `loans` (member_id, copy_id, staff_id, loaned_at,
 * due_at, status), Class Diagram `Peminjaman` (idPeminjaman, tanggalPinjam,
 * statusPeminjaman). Status: Aktif | Selesai.
 *
 * - `diprosesOleh`      = idPengguna Admin yang mencatat (staff_id, FR-024).
 * - `tanggalJatuhTempo` = diisi manual saat pencatatan; durasi pinjam belum
 *   ditetapkan (TBD-05), jadi rentang di bawah hanya CONTOH dan sengaja bervariasi.
 * - Tanggal kembali TIDAK disimpan di sini; berada di returns.js (0..1 per peminjaman).
 * - Peminjaman hasil realisasi booking dihubungkan dari bookings.js (`idPeminjaman`).
 *
 * PJ-001 mengikuti Object Diagram (AG-001, EK-001, 2026-10-15); keadaan akhirnya
 * Selesai sesuai Object Diagram "Pengembalian Selesai".
 */
import { bekukan } from './util.js';

export const PEMINJAMAN = bekukan([
    { idPeminjaman: 'PJ-001', kodeAnggota: 'AG-001', kodeEksemplar: 'EK-001', tanggalPinjam: '2026-10-15', tanggalJatuhTempo: '2026-10-22', diprosesOleh: 'USR-001', status: 'Selesai' },
    { idPeminjaman: 'PJ-002', kodeAnggota: 'AG-007', kodeEksemplar: 'EK-016', tanggalPinjam: '2026-10-16', tanggalJatuhTempo: '2026-10-30', diprosesOleh: 'USR-001', status: 'Selesai' },
    { idPeminjaman: 'PJ-003', kodeAnggota: 'AG-003', kodeEksemplar: 'EK-006', tanggalPinjam: '2026-10-20', tanggalJatuhTempo: '2026-11-03', diprosesOleh: 'USR-001', status: 'Selesai' },
    { idPeminjaman: 'PJ-004', kodeAnggota: 'AG-004', kodeEksemplar: 'EK-008', tanggalPinjam: '2026-10-21', tanggalJatuhTempo: '2026-10-28', diprosesOleh: 'USR-001', status: 'Selesai' },
    { idPeminjaman: 'PJ-005', kodeAnggota: 'AG-001', kodeEksemplar: 'EK-011', tanggalPinjam: '2026-10-24', tanggalJatuhTempo: '2026-11-07', diprosesOleh: 'USR-001', status: 'Selesai' },
    { idPeminjaman: 'PJ-006', kodeAnggota: 'AG-002', kodeEksemplar: 'EK-003', tanggalPinjam: '2026-11-02', tanggalJatuhTempo: '2026-11-16', diprosesOleh: 'USR-001', status: 'Aktif' },
    { idPeminjaman: 'PJ-007', kodeAnggota: 'AG-001', kodeEksemplar: 'EK-017', tanggalPinjam: '2026-11-03', tanggalJatuhTempo: '2026-11-17', diprosesOleh: 'USR-001', status: 'Aktif' },
    { idPeminjaman: 'PJ-008', kodeAnggota: 'AG-005', kodeEksemplar: 'EK-010', tanggalPinjam: '2026-11-04', tanggalJatuhTempo: '2026-11-11', diprosesOleh: 'USR-001', status: 'Aktif' },
    { idPeminjaman: 'PJ-009', kodeAnggota: 'AG-006', kodeEksemplar: 'EK-014', tanggalPinjam: '2026-11-05', tanggalJatuhTempo: '2026-11-19', diprosesOleh: 'USR-001', status: 'Aktif' },
    { idPeminjaman: 'PJ-010', kodeAnggota: 'AG-008', kodeEksemplar: 'EK-012', tanggalPinjam: '2026-11-06', tanggalJatuhTempo: '2026-11-13', diprosesOleh: 'USR-001', status: 'Aktif' },
]);
