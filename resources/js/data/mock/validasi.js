/**
 * Pemeriksa konsistensi data mock (khusus pengembang).
 *
 * Mengembalikan daftar pelanggaran (array string); kosong = konsisten.
 * Dipakai oleh tes otomatis dan dapat dipakai lagi untuk memeriksa hasil
 * simulasi transaksi di Phase 4–5. Aturan mengacu ke SRS v1.1:
 * §6.1–6.3, BR-01..BR-08, FR-020..FR-035.
 */
import { statusEksemplarDariTransaksi } from './relasi.js';
import { STATUS } from '../../utils/status.js';

const ISO_TANGGAL = /^\d{4}-\d{2}-\d{2}$/;
const POLA_KODE = {
    idPengguna: /^USR-\d{3}$/,
    kodeAnggota: /^AG-\d{3}$/,
    kodeBuku: /^BK-\d{3}$/,
    kodeEksemplar: /^EK-\d{3}$/,
    idPeminjaman: /^PJ-\d{3}$/,
    idPengembalian: /^KB-\d{3}$/,
    idBooking: /^BP-\d{3}$/,
};

function tanggalValid(nilai) {
    if (typeof nilai !== 'string' || !ISO_TANGGAL.test(nilai)) {
        return false;
    }

    const [t, b, h] = nilai.split('-').map(Number);
    const d = new Date(Date.UTC(t, b - 1, h));

    return d.getUTCFullYear() === t && d.getUTCMonth() === b - 1 && d.getUTCDate() === h;
}

/**
 * @param {object} data      { pengguna, anggota, buku, eksemplar, peminjaman, pengembalian, booking }
 * @param {object} opsi      { tanggalAcuan: 'YYYY-MM-DD' } — "hari ini" untuk pratinjau
 * @returns {string[]}       daftar pelanggaran
 */
export function periksaKonsistensi(data, { tanggalAcuan } = {}) {
    const galat = [];
    const salah = (pesan) => galat.push(pesan);

    // ---------------------------------------------------------------- 1. Kode unik & format
    const cekUnik = (daftar, kunci, label) => {
        const terlihat = new Set();
        daftar.forEach((r) => {
            if (!POLA_KODE[kunci]?.test(r[kunci])) salah(`${label}: format ${kunci} "${r[kunci]}" tidak valid`);
            if (terlihat.has(r[kunci])) salah(`${label}: ${kunci} "${r[kunci]}" duplikat`);
            terlihat.add(r[kunci]);
        });
    };
    cekUnik(data.pengguna, 'idPengguna', 'Pengguna');
    cekUnik(data.anggota, 'kodeAnggota', 'Anggota');
    cekUnik(data.buku, 'kodeBuku', 'Buku');
    cekUnik(data.eksemplar, 'kodeEksemplar', 'Eksemplar');
    cekUnik(data.peminjaman, 'idPeminjaman', 'Peminjaman');
    cekUnik(data.pengembalian, 'idPengembalian', 'Pengembalian');
    cekUnik(data.booking, 'idBooking', 'Booking');

    const username = new Set();
    data.pengguna.forEach((p) => {
        if (username.has(p.username)) salah(`Pengguna: username "${p.username}" duplikat`);
        username.add(p.username);
    });

    // ---------------------------------------------------------------- 2. Kosakata status
    const cekStatus = (daftar, jenis, kunci, label) =>
        daftar.forEach((r) => {
            if (!(r.status in STATUS[jenis])) salah(`${label} ${r[kunci]}: status "${r.status}" bukan status ${jenis} yang valid`);
        });
    cekStatus(data.anggota, 'anggota', 'kodeAnggota', 'Anggota');
    cekStatus(data.eksemplar, 'eksemplar', 'kodeEksemplar', 'Eksemplar');
    cekStatus(data.peminjaman, 'peminjaman', 'idPeminjaman', 'Peminjaman');
    cekStatus(data.booking, 'booking', 'idBooking', 'Booking');
    data.pengguna.forEach((p) => {
        if (!['admin', 'anggota'].includes(p.peran)) salah(`Pengguna ${p.idPengguna}: peran "${p.peran}" tidak dikenal`);
    });

    // ---------------------------------------------------------------- 3. Relasi (foreign key)
    const pengguna = new Map(data.pengguna.map((p) => [p.idPengguna, p]));
    const anggota = new Map(data.anggota.map((a) => [a.kodeAnggota, a]));
    const buku = new Set(data.buku.map((b) => b.kodeBuku));
    const eksemplar = new Map(data.eksemplar.map((e) => [e.kodeEksemplar, e]));
    const peminjaman = new Map(data.peminjaman.map((p) => [p.idPeminjaman, p]));
    const adalahAdmin = (id) => pengguna.get(id)?.peran === 'admin';

    data.anggota.forEach((a) => {
        // members.user_id opsional (SRS 6.1): anggota baru boleh belum punya akun.
        if (a.idPengguna === null) return;
        const akun = pengguna.get(a.idPengguna);
        if (!akun) salah(`Anggota ${a.kodeAnggota}: idPengguna ${a.idPengguna} tidak ditemukan`);
        else if (akun.peran !== 'anggota') salah(`Anggota ${a.kodeAnggota}: akun ${a.idPengguna} bukan peran anggota`);
        else if (akun.nama !== a.nama) salah(`Anggota ${a.kodeAnggota}: nama berbeda dengan akun ${a.idPengguna}`);
    });

    data.eksemplar.forEach((e) => {
        if (!buku.has(e.kodeBuku)) salah(`Eksemplar ${e.kodeEksemplar}: kodeBuku ${e.kodeBuku} tidak ditemukan`);
    });
    // Buku 1 → 1..* Eksemplar (SRS 6.2)
    data.buku.forEach((b) => {
        if (!data.eksemplar.some((e) => e.kodeBuku === b.kodeBuku)) salah(`Buku ${b.kodeBuku}: tidak memiliki eksemplar`);
    });

    data.peminjaman.forEach((p) => {
        if (!anggota.has(p.kodeAnggota)) salah(`Peminjaman ${p.idPeminjaman}: anggota ${p.kodeAnggota} tidak ditemukan`);
        if (!eksemplar.has(p.kodeEksemplar)) salah(`Peminjaman ${p.idPeminjaman}: eksemplar ${p.kodeEksemplar} tidak ditemukan`);
        if (!adalahAdmin(p.diprosesOleh)) salah(`Peminjaman ${p.idPeminjaman}: pemroses ${p.diprosesOleh} bukan Admin`);
    });

    const pengembalianPer = new Map();
    data.pengembalian.forEach((k) => {
        if (!peminjaman.has(k.idPeminjaman)) salah(`Pengembalian ${k.idPengembalian}: peminjaman ${k.idPeminjaman} tidak ditemukan`);
        if (!adalahAdmin(k.diprosesOleh)) salah(`Pengembalian ${k.idPengembalian}: pemroses ${k.diprosesOleh} bukan Admin`);
        // Peminjaman 1 → 0..1 Pengembalian
        if (pengembalianPer.has(k.idPeminjaman)) salah(`Peminjaman ${k.idPeminjaman}: memiliki lebih dari satu pengembalian`);
        pengembalianPer.set(k.idPeminjaman, k);
    });

    data.booking.forEach((b) => {
        if (!anggota.has(b.kodeAnggota)) salah(`Booking ${b.idBooking}: anggota ${b.kodeAnggota} tidak ditemukan`);
        if (!eksemplar.has(b.kodeEksemplar)) salah(`Booking ${b.idBooking}: eksemplar ${b.kodeEksemplar} tidak ditemukan`);
        if (b.diputuskanOleh && !adalahAdmin(b.diputuskanOleh)) salah(`Booking ${b.idBooking}: pemutus ${b.diputuskanOleh} bukan Admin`);
        if (b.idPeminjaman && !peminjaman.has(b.idPeminjaman)) salah(`Booking ${b.idBooking}: peminjaman ${b.idPeminjaman} tidak ditemukan`);
    });

    // ---------------------------------------------------------------- 4. Peminjaman ↔ pengembalian ↔ eksemplar
    data.peminjaman.forEach((p) => {
        const kembali = pengembalianPer.get(p.idPeminjaman);
        if (p.status === 'Aktif' && kembali) salah(`Peminjaman ${p.idPeminjaman}: Aktif tetapi punya pengembalian ${kembali.idPengembalian}`);
        if (p.status === 'Selesai' && !kembali) salah(`Peminjaman ${p.idPeminjaman}: Selesai tanpa data pengembalian`);
    });

    const aktifPerEksemplar = new Map();
    data.peminjaman
        .filter((p) => p.status === 'Aktif')
        .forEach((p) => aktifPerEksemplar.set(p.kodeEksemplar, [...(aktifPerEksemplar.get(p.kodeEksemplar) ?? []), p.idPeminjaman]));
    aktifPerEksemplar.forEach((ids, kode) => {
        // BR-05 / FR-025: maksimal satu peminjaman Aktif per eksemplar
        if (ids.length > 1) salah(`Eksemplar ${kode}: lebih dari satu peminjaman Aktif (${ids.join(', ')})`);
    });

    const disetujuiPerEksemplar = new Map();
    data.booking
        .filter((b) => b.status === 'Disetujui')
        .forEach((b) => disetujuiPerEksemplar.set(b.kodeEksemplar, [...(disetujuiPerEksemplar.get(b.kodeEksemplar) ?? []), b.idBooking]));
    disetujuiPerEksemplar.forEach((ids, kode) => {
        if (ids.length > 1) salah(`Eksemplar ${kode}: lebih dari satu booking Disetujui (${ids.join(', ')})`);
        // BR-07: eksemplar Dipesan tidak boleh sekaligus dipinjam
        if (aktifPerEksemplar.has(kode)) salah(`Eksemplar ${kode}: dipesan (${ids.join(', ')}) sekaligus dipinjam`);
    });

    data.eksemplar.forEach((e) => {
        const seharusnya = statusEksemplarDariTransaksi(data, e.kodeEksemplar);
        if (e.status !== seharusnya) salah(`Eksemplar ${e.kodeEksemplar}: status "${e.status}", seharusnya "${seharusnya}" menurut transaksi`);
    });

    // ---------------------------------------------------------------- 5. Booking & realisasi
    data.booking.forEach((b) => {
        const diputuskan = ['Disetujui', 'Ditolak', 'Direalisasikan'].includes(b.status);
        if (diputuskan && (!b.tanggalKeputusan || !b.diputuskanOleh)) salah(`Booking ${b.idBooking}: ${b.status} tanpa waktu/pelaku keputusan`);
        if (b.status === 'Diajukan' && (b.tanggalKeputusan || b.diputuskanOleh)) salah(`Booking ${b.idBooking}: Diajukan tetapi sudah punya keputusan`);
        if (b.status === 'Ditolak' && !b.alasanPenolakan) salah(`Booking ${b.idBooking}: Ditolak tanpa alasan penolakan`);
        if (b.status !== 'Ditolak' && b.alasanPenolakan) salah(`Booking ${b.idBooking}: alasan penolakan hanya untuk status Ditolak`);
        if ((b.status === 'Direalisasikan') !== Boolean(b.idPeminjaman)) salah(`Booking ${b.idBooking}: idPeminjaman hanya (dan wajib) untuk status Direalisasikan`);

        if (b.status === 'Direalisasikan' && peminjaman.has(b.idPeminjaman)) {
            const p = peminjaman.get(b.idPeminjaman);
            if (p.kodeAnggota !== b.kodeAnggota || p.kodeEksemplar !== b.kodeEksemplar) {
                salah(`Booking ${b.idBooking}: anggota/eksemplar berbeda dengan peminjaman ${p.idPeminjaman}`);
            }
            if (b.tanggalKeputusan && p.tanggalPinjam < b.tanggalKeputusan) {
                salah(`Booking ${b.idBooking}: direalisasikan (${p.tanggalPinjam}) sebelum disetujui (${b.tanggalKeputusan})`);
            }
        }
    });
    const realisasiPer = new Map();
    data.booking
        .filter((b) => b.idPeminjaman)
        .forEach((b) => {
            if (realisasiPer.has(b.idPeminjaman)) salah(`Peminjaman ${b.idPeminjaman}: berasal dari lebih dari satu booking`);
            realisasiPer.set(b.idPeminjaman, b.idBooking);
        });

    // Catatan: syarat "anggota Aktif" (BR-01, FR-026, FR-031) dan "eksemplar Tersedia
    // saat booking diajukan" (FR-031) berlaku SAAT transaksi dibuat, bukan invariant
    // sepanjang waktu — anggota boleh dinonaktifkan kemudian (FR-022) dan eksemplar
    // booking Diajukan boleh dipinjam langsung (BR-07 hanya melindungi Dipesan).
    // Aturan tersebut diperiksa di utils/validasiTransaksi.js.

    // ---------------------------------------------------------------- 6. Tanggal
    const cekTanggal = (nilai, label, bolehMasaDepan = false) => {
        if (!tanggalValid(nilai)) {
            salah(`${label}: tanggal "${nilai}" tidak valid`);
        } else if (!bolehMasaDepan && tanggalAcuan && nilai > tanggalAcuan) {
            salah(`${label}: tanggal ${nilai} melewati tanggal acuan ${tanggalAcuan}`);
        }
    };

    data.peminjaman.forEach((p) => {
        cekTanggal(p.tanggalPinjam, `Peminjaman ${p.idPeminjaman} tanggalPinjam`);
        cekTanggal(p.tanggalJatuhTempo, `Peminjaman ${p.idPeminjaman} tanggalJatuhTempo`, true);
        if (p.tanggalJatuhTempo <= p.tanggalPinjam) salah(`Peminjaman ${p.idPeminjaman}: jatuh tempo tidak setelah tanggal pinjam`);
        const kembali = pengembalianPer.get(p.idPeminjaman);
        if (kembali && kembali.tanggalKembali < p.tanggalPinjam) salah(`Peminjaman ${p.idPeminjaman}: dikembalikan sebelum dipinjam`);
    });
    data.pengembalian.forEach((k) => cekTanggal(k.tanggalKembali, `Pengembalian ${k.idPengembalian} tanggalKembali`));
    data.booking.forEach((b) => {
        cekTanggal(b.tanggalBooking, `Booking ${b.idBooking} tanggalBooking`);
        if (b.tanggalKeputusan) {
            cekTanggal(b.tanggalKeputusan, `Booking ${b.idBooking} tanggalKeputusan`);
            if (b.tanggalKeputusan < b.tanggalBooking) salah(`Booking ${b.idBooking}: diputuskan sebelum diajukan`);
        }
    });

    // Periode peminjaman pada eksemplar yang sama tidak boleh tumpang tindih.
    const periodePer = new Map();
    data.peminjaman.forEach((p) => {
        const akhir = pengembalianPer.get(p.idPeminjaman)?.tanggalKembali ?? '9999-12-31';
        periodePer.set(p.kodeEksemplar, [...(periodePer.get(p.kodeEksemplar) ?? []), { id: p.idPeminjaman, awal: p.tanggalPinjam, akhir }]);
    });
    periodePer.forEach((daftar, kode) => {
        const urut = [...daftar].sort((a, b) => a.awal.localeCompare(b.awal));
        for (let i = 1; i < urut.length; i++) {
            if (urut[i].awal < urut[i - 1].akhir) salah(`Eksemplar ${kode}: periode ${urut[i - 1].id} dan ${urut[i].id} tumpang tindih`);
        }
    });

    return galat;
}
