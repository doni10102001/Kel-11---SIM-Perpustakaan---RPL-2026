/**
 * Utilitas kecil untuk data mock. Data awal dibekukan agar tidak berubah tanpa
 * sengaja; simulasi transaksi (Phase 4–5) harus bekerja pada salinan data.
 */
export function bekukan(nilai) {
    if (nilai && typeof nilai === 'object' && !Object.isFrozen(nilai)) {
        Object.values(nilai).forEach(bekukan);
        Object.freeze(nilai);
    }

    return nilai;
}

/** Salinan dalam yang bisa diubah (mis. untuk store reaktif di phase berikutnya). */
export function salin(nilai) {
    return JSON.parse(JSON.stringify(nilai));
}
