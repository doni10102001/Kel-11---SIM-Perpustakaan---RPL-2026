/**
 * Pencarian dan filter di sisi browser (fungsi murni).
 */

/** Normalisasi teks untuk pencarian: huruf kecil, spasi dirapikan. */
export function normalTeks(nilai) {
    return String(nilai ?? '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

/** true bila kata kunci kosong atau terkandung di salah satu nilai. */
export function cocokKataKunci(nilai, kataKunci) {
    const kunci = normalTeks(kataKunci);

    return kunci === '' || nilai.some((n) => normalTeks(n).includes(kunci));
}

/** Data Anggota: cari berdasarkan kode, nama, atau kontak; filter status. */
export function filterAnggota(daftar, { kataKunci = '', status = '' } = {}) {
    return daftar.filter(
        (a) => (status === '' || a.status === status) && cocokKataKunci([a.kodeAnggota, a.nama, a.kontak], kataKunci),
    );
}

/** Data Buku: cari berdasarkan kode buku, judul, atau kategori (FR-007). */
export function filterBuku(daftar, { kataKunci = '' } = {}) {
    return daftar.filter((b) => cocokKataKunci([b.kodeBuku, b.judul, b.kategori], kataKunci));
}

/** Katalog (Cari Buku): kata kunci pada kode buku, judul, atau kategori; filter kategori opsional. */
export function filterKatalog(daftar, { kataKunci = '', kategori = '' } = {}) {
    return filterBuku(daftar, { kataKunci }).filter((b) => kategori === '' || b.kategori === kategori);
}

/**
 * Usulan kode berikutnya untuk placeholder, mis. AG-010 → "AG-011".
 * Hanya contoh di placeholder; kode tetap diisi pengguna.
 */
export function kodeBerikutnya(daftar, kunci, prefiks) {
    const pola = new RegExp(`^${prefiks}-(\\d+)$`);
    const terbesar = daftar.reduce((maks, r) => {
        const cocok = pola.exec(r[kunci]);

        return cocok ? Math.max(maks, Number(cocok[1])) : maks;
    }, 0);

    return `${prefiks}-${String(terbesar + 1).padStart(3, '0')}`;
}
