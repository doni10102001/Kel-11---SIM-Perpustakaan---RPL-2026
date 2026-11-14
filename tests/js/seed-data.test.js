/**
 * Phase 7A — data seed MySQL (database/seeders/data/perpustakaan-demo.json)
 * harus identik dengan data mock yang dipakai UI. Jalankan: npm run test:data
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, test } from 'node:test';

import { DATA_MOCK } from './fixtures/mock/index.js';

const seed = JSON.parse(readFileSync(new URL('../../database/seeders/data/perpustakaan-demo.json', import.meta.url), 'utf8'));
const mock = JSON.parse(JSON.stringify(DATA_MOCK));

describe('Data seed MySQL (Phase 7A)', () => {
    test('memuat entitas yang sama dengan mock', () => {
        const kunciSeed = Object.keys(seed).filter((k) => !k.startsWith('_')).sort();
        assert.deepEqual(kunciSeed, Object.keys(mock).sort());
    });

    test('pengguna sama dengan mock tanpa passwordHash/statusAkun', () => {
        const harapan = mock.pengguna.map(({ passwordHash, statusAkun, ...sisa }) => sisa);
        assert.deepEqual(seed.pengguna, harapan);
    });

    test('tidak ada kata sandi maupun hash di data seed', () => {
        const teks = JSON.stringify(seed).toLowerCase();
        assert.ok(!teks.includes('password'), 'data seed tidak boleh memuat field password');
        assert.ok(!teks.includes('katasandi'), 'data seed tidak boleh memuat kata sandi');
    });

    for (const entitas of ['anggota', 'buku', 'eksemplar', 'peminjaman', 'pengembalian', 'booking']) {
        test(`${entitas} identik dengan mock`, () => {
            assert.deepEqual(seed[entitas], mock[entitas]);
        });
    }
});
