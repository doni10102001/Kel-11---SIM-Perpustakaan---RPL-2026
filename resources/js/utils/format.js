/**
 * Format tanggal dalam Bahasa Indonesia, zona waktu Asia/Jakarta (WIB).
 * Menerima string ISO tanggal (YYYY-MM-DD) atau tanggal-waktu.
 */
const ZONA_WAKTU = 'Asia/Jakarta';

function keDate(nilai) {
    if (nilai instanceof Date) {
        return nilai;
    }

    const teks = String(nilai);

    // Tanggal tanpa jam dianggap tengah hari WIB agar tidak bergeser hari.
    return /^\d{4}-\d{2}-\d{2}$/.test(teks) ? new Date(`${teks}T12:00:00+07:00`) : new Date(teks);
}

const FORMAT = {
    pendek: { day: '2-digit', month: 'short', year: 'numeric' },
    panjang: { day: 'numeric', month: 'long', year: 'numeric' },
    lengkap: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
};

/**
 * @param {string|Date|null|undefined} nilai
 * @param {'pendek'|'panjang'|'lengkap'} gaya
 * @returns {string} '—' bila nilai kosong
 */
export function formatTanggal(nilai, gaya = 'panjang') {
    if (nilai === null || nilai === undefined || nilai === '') {
        return '—';
    }

    const tanggal = keDate(nilai);

    if (Number.isNaN(tanggal.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', { ...FORMAT[gaya], timeZone: ZONA_WAKTU }).format(tanggal);
}

/**
 * Batch 2 — tanggal & jam WIB dari instant ISO UTC, mis. Batas Pengambilan:
 * "18 November 2026, 14.00 WIB".
 * @param {string|Date|null|undefined} nilai
 */
export function formatWaktu(nilai) {
    if (nilai === null || nilai === undefined || nilai === '') {
        return '—';
    }

    const waktu = keDate(nilai);
    if (Number.isNaN(waktu.getTime())) {
        return '—';
    }

    const tanggal = new Intl.DateTimeFormat('id-ID', { ...FORMAT.panjang, timeZone: ZONA_WAKTU }).format(waktu);
    const jam = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: ZONA_WAKTU }).format(waktu);

    return `${tanggal}, ${jam} WIB`;
}

/** Inisial nama untuk avatar, mis. "Rina Wulandari" → "RW". */
export function inisial(nama) {
    return String(nama ?? '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((kata) => kata[0].toUpperCase())
        .join('');
}
