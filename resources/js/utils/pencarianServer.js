/**
 * Phase 9B — fungsi murni untuk pencarian buku di server (query string &
 * nomor halaman). Dipakai composables/usePencarianBuku.js dan PaginasiTabel.
 */
export const JEDA_KETIK_MS = 300;

export const META_KOSONG = Object.freeze({ q: '', kategori: '', halaman: 1, perHalaman: 20, halamanTerakhir: 1, total: 0, totalSemua: 0 });

/** Parameter query yang dikirim (tanpa nilai kosong / halaman 1). */
export function parameterPencarian({ q = '', kategori = '', halaman = 1 } = {}) {
    const p = {};
    if (String(q).trim() !== '') p.q = String(q).trim();
    if (kategori) p.kategori = kategori;
    if (Number(halaman) > 1) p.page = Number(halaman);

    return p;
}

/** URL halaman hasil dengan filter yang sama (dipakai tautan pagination). */
export function urlPencarian(dasar, isian) {
    const qs = new URLSearchParams(parameterPencarian(isian)).toString();

    return qs ? `${dasar}?${qs}` : dasar;
}

/** Rentang nomor halaman yang ditampilkan (maks. 5, berpusat pada halaman aktif). */
export function nomorHalaman(halaman, terakhir, lebar = 5) {
    const awal = Math.max(1, Math.min(halaman - Math.floor(lebar / 2), terakhir - lebar + 1));

    return Array.from({ length: Math.min(lebar, terakhir) }, (_, i) => awal + i);
}
