/**
 * Pengembalian — SRS 6.1 `returns` (loan_id, staff_id, returned_at),
 * Class Diagram `Pengembalian` (idPengembalian, tanggalKembali).
 *
 * Relasi Peminjaman 1 → 0..1 Pengembalian. Setiap peminjaman Selesai punya
 * tepat satu pengembalian; peminjaman Aktif tidak punya (FR-027, FR-028).
 * KB-001 mengikuti Object Diagram (PJ-001, 2026-10-18).
 */
import { bekukan } from './util.js';

export const PENGEMBALIAN = bekukan([
    { idPengembalian: 'KB-001', idPeminjaman: 'PJ-001', tanggalKembali: '2026-10-18', diprosesOleh: 'USR-001' },
    { idPengembalian: 'KB-002', idPeminjaman: 'PJ-002', tanggalKembali: '2026-10-27', diprosesOleh: 'USR-001' },
    { idPengembalian: 'KB-003', idPeminjaman: 'PJ-004', tanggalKembali: '2026-10-28', diprosesOleh: 'USR-001' },
    { idPengembalian: 'KB-004', idPeminjaman: 'PJ-005', tanggalKembali: '2026-11-01', diprosesOleh: 'USR-001' },
    { idPengembalian: 'KB-005', idPeminjaman: 'PJ-003', tanggalKembali: '2026-11-02', diprosesOleh: 'USR-001' },
]);
