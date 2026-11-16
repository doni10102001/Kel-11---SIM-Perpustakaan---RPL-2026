/**
 * Batch 2 — frontend transaksi: semua aksi tulis dikirim ke server lewat
 * Inertia (router terikat), kirim ganda dicegah, bukti dibangun dari data
 * server setelah commit, status Kedaluwarsa & Batas Pengambilan tampil (WIB).
 * Jalankan: npm run test:data
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, test } from 'node:test';

import { formatWaktu } from '../../resources/js/utils/format.js';
import { daftarStatus, tampilanStatus } from '../../resources/js/utils/status.js';

const baca = (relatif) => readFileSync(new URL(`../../resources/js/${relatif}`, import.meta.url), 'utf8');

const HALAMAN = {
    'Pages/Admin/Peminjaman.vue': "rute('peminjaman.store')",
    'Pages/Admin/Pengembalian.vue': "rute('pengembalian.store')",
    'Pages/Admin/Booking/Verifikasi.vue': "rute('booking.setujui', { kodeBooking: idDipilih.value })",
    'Pages/Admin/Booking/Realisasi.vue': "rute('booking.realisasikan', { kodeBooking: idBooking })",
    'Pages/Anggota/Booking/Ajukan.vue': "rute('booking.store')",
    'Pages/Anggota/Booking/Status.vue': "rute('booking.batalkan', { kodeBooking: idDipilih.value })",
};

describe('Transaksi ke server (Batch 2)', () => {
    const composable = baca('composables/useTransaksiServer.js');

    test('composable memanggil router.post sebagai method, mencegah kirim ganda, dan membaca flash setelah commit', () => {
        assert.match(composable, /router\.post\(url, payload, \{/);
        assert.doesNotMatch(composable, /=\s*router\.post\b/, 'method router tidak dilepas dari objeknya');
        assert.match(composable, /if \(memproses\.value\) return false;/);
        assert.match(composable, /onSuccess: \(page\) => onSukses\?\.\(page\.props\.flash\?\.transaksi \?\? null/);
        assert.match(composable, /onFinish: \(\) => \{\s*memproses\.value = false;/);
    });

    for (const [berkas, ruteServer] of Object.entries(HALAMAN)) {
        test(`${berkas}: kirim ke ${ruteServer}, tanpa simulasi, konfirmasi memuat`, () => {
            const isi = baca(berkas);
            assert.ok(isi.includes(`kirim(${ruteServer}`), 'mengirim ke endpoint server');
            assert.match(isi, /useTransaksiServer\(\)/);
            assert.match(isi, /:loading="memproses"/, 'tombol konfirmasi menampilkan status memuat (cegah klik ganda)');
            assert.doesNotMatch(isi, /mode pratinjau|GalatValidasi/);
            assert.doesNotMatch(isi, /\b(catatPeminjaman|prosesPengembalian|setujuiBooking|tolakBooking|realisasikanBooking|ajukanBooking|batalkanBooking)\(/);
        });
    }

    test('penolakan booking juga ke server dan pemilik booking tidak pernah dikirim dari browser', () => {
        assert.ok(baca('Pages/Admin/Booking/Verifikasi.vue').includes("kirim(rute('booking.tolak', { kodeBooking: idDipilih.value }), { alasanPenolakan: alasan.value }"));
        assert.ok(baca('Pages/Anggota/Booking/Ajukan.vue').includes("kirim(rute('booking.store'), { kodeBuku: form.kodeBuku, kodeEksemplar: form.kodeEksemplar }"));
        assert.doesNotMatch(baca('Pages/Anggota/Booking/Ajukan.vue'), /kodeAnggota[^\n]*kirim\(|kirim\([^\n]*kodeAnggota/);
    });

    test('bukti/pesan sukses dibangun dari data server berdasarkan kode transaksi', () => {
        assert.match(baca('Pages/Admin/Peminjaman.vue'), /data\.peminjaman\.find\(\(x\) => x\.idPeminjaman === kode\)/);
        assert.match(baca('Pages/Admin/Pengembalian.vue'), /data\.pengembalian\.find\(\(x\) => x\.idPengembalian === kode\)/);
        assert.match(baca('Pages/Anggota/Booking/Ajukan.vue'), /data\.booking\.find\(\(x\) => x\.idBooking === kode\)/);
    });
});

describe('Status Kedaluwarsa & Batas Pengambilan (Batch 2)', () => {
    test('Kedaluwarsa masuk kosakata status booking dengan ikon (tab & badge mengikutinya)', () => {
        assert.deepEqual(daftarStatus('booking'), ['Diajukan', 'Disetujui', 'Ditolak', 'Dibatalkan', 'Direalisasikan', 'Kedaluwarsa']);
        assert.deepEqual(tampilanStatus('booking', 'Kedaluwarsa'), { nada: 'neutral', ikon: 'bi-hourglass-bottom' });
    });

    test('Batas Pengambilan ditampilkan dalam WIB dari instant UTC', () => {
        assert.equal(formatWaktu('2026-11-18T07:00:00Z'), '18 November 2026, 14.00 WIB');
        assert.equal(formatWaktu('2026-11-18T17:30:00Z'), '19 November 2026, 00.30 WIB', 'pergantian hari mengikuti WIB');
        assert.equal(formatWaktu(null), '—');
    });

    test('Batas Pengambilan tampil di Status Booking, Verifikasi, dan Realisasi', () => {
        const status = baca('Pages/Anggota/Booking/Status.vue');
        assert.match(status, /<dt>Batas Pengambilan<\/dt>\s*<dd>\{\{ formatWaktu\(dipilih\.batasPengambilan\) \}\}<\/dd>/);
        assert.match(status, /case 'Kedaluwarsa':/);
        assert.match(baca('Pages/Admin/Booking/Verifikasi.vue'), /<dt>Batas Pengambilan<\/dt>/);
        assert.match(baca('Pages/Admin/Booking/Realisasi.vue'), /label="Batas Pengambilan"/);
    });

    test('penolakan realisasi (mis. Kedaluwarsa) tetap terlihat walau booking keluar dari daftar Disetujui', () => {
        const realisasi = baca('Pages/Admin/Booking/Realisasi.vue');
        assert.match(realisasi, /galatHalaman\.value = e\.umum \? \{ idBooking, pesan: e\.umum \} : null;/);
        assert.match(realisasi, /v-if="galatHalaman && !dipilih"/);
    });
});
