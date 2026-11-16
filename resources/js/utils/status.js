/**
 * Kosakata status resmi (SRS v1.1 §6.3) beserta tampilan badge.
 * Setiap status punya ikon + teks, tidak bergantung pada warna saja (SRS §3.1).
 *
 * nada: success | warning | danger | info | neutral | indigo
 */
export const STATUS = {
    anggota: {
        Aktif: { nada: 'success', ikon: 'bi-check-circle-fill' },
        Nonaktif: { nada: 'neutral', ikon: 'bi-slash-circle' },
    },
    eksemplar: {
        Tersedia: { nada: 'success', ikon: 'bi-check-circle-fill' },
        Dipesan: { nada: 'warning', ikon: 'bi-bookmark-fill' },
        Dipinjam: { nada: 'info', ikon: 'bi-arrow-up-right-circle-fill' },
    },
    peminjaman: {
        Aktif: { nada: 'info', ikon: 'bi-hourglass-split' },
        Selesai: { nada: 'success', ikon: 'bi-check2-circle' },
    },
    booking: {
        Diajukan: { nada: 'warning', ikon: 'bi-send-fill' },
        Disetujui: { nada: 'success', ikon: 'bi-check-circle-fill' },
        Ditolak: { nada: 'danger', ikon: 'bi-x-circle-fill' },
        Dibatalkan: { nada: 'neutral', ikon: 'bi-slash-circle' },
        Direalisasikan: { nada: 'indigo', ikon: 'bi-bookmark-check-fill' },
        // Batch 2: Disetujui yang tidak direalisasikan dalam 48 jam (reservasi dilepas).
        Kedaluwarsa: { nada: 'neutral', ikon: 'bi-hourglass-bottom' },
    },
};

export const JENIS_STATUS = Object.keys(STATUS);

/** Daftar status untuk satu jenis, mis. untuk opsi filter. */
export function daftarStatus(jenis) {
    return Object.keys(STATUS[jenis] ?? {});
}

export function tampilanStatus(jenis, status) {
    return STATUS[jenis]?.[status] ?? null;
}
