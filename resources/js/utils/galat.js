/**
 * Galat validasi/aksi yang ditampilkan halaman (FormFeedback). `galat` berisi
 * pesan per field; kunci `umum` untuk pesan umum.
 */
export class GalatValidasi extends Error {
    constructor(galat) {
        super(Object.values(galat)[0] ?? 'Data tidak valid.');
        this.name = 'GalatValidasi';
        this.galat = galat;
    }
}
