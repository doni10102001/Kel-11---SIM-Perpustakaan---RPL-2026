/**
 * Eksemplar buku (salinan fisik) — SRS 6.1 `book_copies` (book_id, copy_code,
 * status), Class Diagram `EksemplarBuku` (idEksemplar, statusEksemplar).
 *
 * Label UI "Kode Eksemplar" = `kodeEksemplar`. Relasi ke buku lewat `kodeBuku`.
 * EK-001 (BK-001) dan EK-002 (BK-002) mengikuti Object Diagram.
 *
 * `status` adalah keadaan SAAT INI (per TANGGAL_DEMO) dan harus sama dengan
 * status yang diturunkan dari transaksi (diuji di validasi.js):
 *   Dipinjam  ← tepat satu peminjaman Aktif
 *   Dipesan   ← tepat satu booking Disetujui yang belum direalisasikan
 *   Tersedia  ← selain itu
 */
import { bekukan } from './util.js';

export const EKSEMPLAR = bekukan([
    { kodeEksemplar: 'EK-001', kodeBuku: 'BK-001', status: 'Tersedia' },
    { kodeEksemplar: 'EK-002', kodeBuku: 'BK-002', status: 'Dipesan' },
    { kodeEksemplar: 'EK-003', kodeBuku: 'BK-001', status: 'Dipinjam' },
    { kodeEksemplar: 'EK-004', kodeBuku: 'BK-001', status: 'Tersedia' },
    { kodeEksemplar: 'EK-005', kodeBuku: 'BK-002', status: 'Tersedia' },
    { kodeEksemplar: 'EK-006', kodeBuku: 'BK-003', status: 'Tersedia' },
    { kodeEksemplar: 'EK-007', kodeBuku: 'BK-003', status: 'Tersedia' },
    { kodeEksemplar: 'EK-008', kodeBuku: 'BK-004', status: 'Tersedia' },
    { kodeEksemplar: 'EK-009', kodeBuku: 'BK-004', status: 'Tersedia' },
    { kodeEksemplar: 'EK-010', kodeBuku: 'BK-005', status: 'Dipinjam' },
    { kodeEksemplar: 'EK-011', kodeBuku: 'BK-006', status: 'Tersedia' },
    { kodeEksemplar: 'EK-012', kodeBuku: 'BK-006', status: 'Dipinjam' },
    { kodeEksemplar: 'EK-013', kodeBuku: 'BK-007', status: 'Dipesan' },
    { kodeEksemplar: 'EK-014', kodeBuku: 'BK-008', status: 'Dipinjam' },
    { kodeEksemplar: 'EK-015', kodeBuku: 'BK-008', status: 'Tersedia' },
    { kodeEksemplar: 'EK-016', kodeBuku: 'BK-009', status: 'Tersedia' },
    { kodeEksemplar: 'EK-017', kodeBuku: 'BK-010', status: 'Dipinjam' },
    { kodeEksemplar: 'EK-018', kodeBuku: 'BK-010', status: 'Tersedia' },
]);
