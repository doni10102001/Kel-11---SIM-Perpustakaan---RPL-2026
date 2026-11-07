/**
 * Kredensial SIMULASI untuk akun Anggota yang dibuat Admin (fungsi murni).
 *
 * BUKAN AUTENTIKASI YANG AMAN. Seluruh proses berjalan di browser:
 * - kata sandi di-hash SHA-256 dengan garam acak lalu hanya hash-nya yang
 *   disimpan di store memori (`pengguna.passwordHash`, Class Diagram Pengguna);
 * - plaintext tidak disimpan di store, localStorage, sessionStorage, log, URL,
 *   maupun repositori Git;
 * - tidak ada server, rate limit, penguncian akun, maupun sesi yang tahan
 *   muat ulang. SHA-256 tunggal juga bukan algoritma penyimpanan kata sandi
 *   yang layak untuk produksi (gunakan bcrypt/argon2 di server).
 *
 * SHA-256 ditulis sinkron (tanpa Web Crypto) agar transaksi store tetap
 * sinkron/atomik dan berjalan juga pada konteks http non-localhost.
 */

const K = new Uint32Array([
    0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
    0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
    0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
    0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
    0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
    0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
    0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
    0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2,
]);

const rotr = (x, n) => (x >>> n) | (x << (32 - n));

/** SHA-256 dari teks (UTF-8) → heksadesimal 64 karakter. */
export function sha256(teks) {
    const pesan = new TextEncoder().encode(String(teks));
    const panjangBit = pesan.length * 8;
    const total = Math.ceil((pesan.length + 9) / 64) * 64;
    const blok = new Uint8Array(total);
    blok.set(pesan);
    blok[pesan.length] = 0x80;
    const tampilan = new DataView(blok.buffer);
    tampilan.setUint32(total - 8, Math.floor(panjangBit / 0x100000000));
    tampilan.setUint32(total - 4, panjangBit >>> 0);

    const h = new Uint32Array([0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19]);
    const w = new Uint32Array(64);

    for (let awal = 0; awal < total; awal += 64) {
        for (let i = 0; i < 16; i++) w[i] = tampilan.getUint32(awal + i * 4);
        for (let i = 16; i < 64; i++) {
            const s0 = rotr(w[i - 15], 7) ^ rotr(w[i - 15], 18) ^ (w[i - 15] >>> 3);
            const s1 = rotr(w[i - 2], 17) ^ rotr(w[i - 2], 19) ^ (w[i - 2] >>> 10);
            w[i] = (w[i - 16] + s0 + w[i - 7] + s1) >>> 0;
        }

        let [a, b, c, d, e, f, g, hh] = h;
        for (let i = 0; i < 64; i++) {
            const t1 = (hh + (rotr(e, 6) ^ rotr(e, 11) ^ rotr(e, 25)) + ((e & f) ^ (~e & g)) + K[i] + w[i]) >>> 0;
            const t2 = ((rotr(a, 2) ^ rotr(a, 13) ^ rotr(a, 22)) + ((a & b) ^ (a & c) ^ (b & c))) >>> 0;
            hh = g;
            g = f;
            f = e;
            e = (d + t1) >>> 0;
            d = c;
            c = b;
            b = a;
            a = (t1 + t2) >>> 0;
        }

        [a, b, c, d, e, f, g, hh].forEach((nilai, i) => {
            h[i] = (h[i] + nilai) >>> 0;
        });
    }

    return [...h].map((x) => x.toString(16).padStart(8, '0')).join('');
}

/** Garam acak 16 byte (heksadesimal). */
export function buatGaram() {
    const byte = new Uint8Array(16);
    globalThis.crypto.getRandomValues(byte);

    return [...byte].map((x) => x.toString(16).padStart(2, '0')).join('');
}

/** Format tersimpan: `sha256$<garam>$<hash>`. Plaintext tidak pernah disimpan. */
export const POLA_HASH = /^sha256\$[0-9a-f]{32}\$[0-9a-f]{64}$/;

export function hashKataSandi(kataSandi, garam = buatGaram()) {
    return `sha256$${garam}$${sha256(`${garam}:${kataSandi}`)}`;
}

/** Bandingkan kata sandi dengan hash tersimpan. */
export function cocokKataSandi(kataSandi, tersimpan) {
    if (typeof tersimpan !== 'string' || !POLA_HASH.test(tersimpan)) return false;
    const [, garam, hash] = tersimpan.split('$');
    const hitung = sha256(`${garam}:${kataSandi}`);

    // Bandingkan seluruh karakter (tanpa berhenti di beda pertama).
    let beda = 0;
    for (let i = 0; i < hash.length; i++) beda |= hash.charCodeAt(i) ^ hitung.charCodeAt(i);

    return beda === 0;
}
