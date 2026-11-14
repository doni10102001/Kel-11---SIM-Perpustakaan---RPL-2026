/**
 * Booking pinjam buku — SRS 6.1 `bookings` (member_id, copy_id, status,
 * submitted_at, decided_at, staff_id?), Class Diagram `BookingPeminjaman`
 * (idBooking, tanggalBooking, statusBooking).
 * Status: Diajukan | Disetujui | Ditolak | Dibatalkan | Direalisasikan.
 *
 * - `tanggalBooking`    = waktu pengajuan (submitted_at). Booking baru selalu Diajukan.
 * - `tanggalKeputusan`, `diputuskanOleh` = waktu & Admin yang menyetujui/menolak
 *   (decided_at, staff_id, FR-033). Null bila belum diputuskan.
 * - `alasanPenolakan`   = hanya untuk status Ditolak (FR-033).
 * - `idPeminjaman`      = peminjaman hasil realisasi; hanya untuk Direalisasikan
 *   (State Machine: peminjamanTersimpan / kaitkanTransaksi).
 *
 * BP-001 mengikuti Object Diagram "Booking Buku" (AG-002, EK-002, 2026-10-16, Disetujui).
 * Masa berlaku, kuota, dan antrean booking belum ditetapkan (TBD-06).
 */
import { bekukan } from './util.js';

export const BOOKING = bekukan([
    {
        idBooking: 'BP-001',
        kodeAnggota: 'AG-002',
        kodeEksemplar: 'EK-002',
        tanggalBooking: '2026-10-16',
        status: 'Disetujui',
        tanggalKeputusan: '2026-10-17',
        diputuskanOleh: 'USR-001',
        alasanPenolakan: null,
        idPeminjaman: null,
    },
    {
        // Eksemplar sudah dipinjam (PJ-003) sebelum pengajuan diputuskan.
        idBooking: 'BP-002',
        kodeAnggota: 'AG-004',
        kodeEksemplar: 'EK-006',
        tanggalBooking: '2026-10-19',
        status: 'Ditolak',
        tanggalKeputusan: '2026-10-20',
        diputuskanOleh: 'USR-001',
        alasanPenolakan: 'Eksemplar sudah dipinjam anggota lain sebelum pengajuan diproses.',
        idPeminjaman: null,
    },
    {
        // Dibatalkan anggota sebelum diputuskan (FR-032).
        idBooking: 'BP-003',
        kodeAnggota: 'AG-001',
        kodeEksemplar: 'EK-004',
        tanggalBooking: '2026-10-28',
        status: 'Dibatalkan',
        tanggalKeputusan: null,
        diputuskanOleh: null,
        alasanPenolakan: null,
        idPeminjaman: null,
    },
    {
        idBooking: 'BP-004',
        kodeAnggota: 'AG-001',
        kodeEksemplar: 'EK-017',
        tanggalBooking: '2026-10-30',
        status: 'Direalisasikan',
        tanggalKeputusan: '2026-10-31',
        diputuskanOleh: 'USR-001',
        alasanPenolakan: null,
        idPeminjaman: 'PJ-007',
    },
    {
        // Disetujui lalu dibatalkan; reservasi eksemplar dilepas (FR-035).
        idBooking: 'BP-005',
        kodeAnggota: 'AG-008',
        kodeEksemplar: 'EK-009',
        tanggalBooking: '2026-11-01',
        status: 'Dibatalkan',
        tanggalKeputusan: '2026-11-02',
        diputuskanOleh: 'USR-001',
        alasanPenolakan: null,
        idPeminjaman: null,
    },
    {
        idBooking: 'BP-006',
        kodeAnggota: 'AG-003',
        kodeEksemplar: 'EK-013',
        tanggalBooking: '2026-11-05',
        status: 'Disetujui',
        tanggalKeputusan: '2026-11-06',
        diputuskanOleh: 'USR-001',
        alasanPenolakan: null,
        idPeminjaman: null,
    },
    {
        idBooking: 'BP-007',
        kodeAnggota: 'AG-001',
        kodeEksemplar: 'EK-005',
        tanggalBooking: '2026-11-06',
        status: 'Diajukan',
        tanggalKeputusan: null,
        diputuskanOleh: null,
        alasanPenolakan: null,
        idPeminjaman: null,
    },
    {
        idBooking: 'BP-008',
        kodeAnggota: 'AG-010',
        kodeEksemplar: 'EK-018',
        tanggalBooking: '2026-11-07',
        status: 'Diajukan',
        tanggalKeputusan: null,
        diputuskanOleh: null,
        alasanPenolakan: null,
        idPeminjaman: null,
    },
]);
