<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Booking;
use App\Models\BookReturn;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use App\Services\Transaksi\LayananKedaluwarsaBooking;
use App\Support\PemeriksaInvarianData;
use App\Support\WaktuPerpustakaan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seeder DEVELOPMENT Phase 7A — data demo dari mock UI
 * (database/seeders/data/perpustakaan-demo.json).
 *
 * Keamanan & idempotensi:
 * - Hanya berjalan pada APP_ENV local/testing, driver MySQL, host loopback.
 * - Rekaman dicari berdasarkan kode bisnis/username; yang sudah ada TIDAK
 *   ditimpa (tidak ada update/hapus). Menjalankan ulang tidak menggandakan data.
 * - Semua dalam satu transaksi; invarian lintas tabel diperiksa sebelum commit.
 *   Pelanggaran = rollback penuh.
 * - Kata sandi (D-08): Admin dari SEED_ADMIN_PASSWORD (.env lokal, provisioning
 *   awal). SEMUA akun Anggota diberi hash acak yang tidak diketahui siapa pun
 *   (tidak dapat dipakai masuk sampai Admin mereset kata sandi pada fase
 *   berikutnya). Tidak ada kata sandi bersama, tidak ada yang dicetak atau di-log.
 * - Nama (D-01): users.name hanya untuk Admin; akun Anggota name = NULL karena
 *   nama Anggota bersumber dari members.name. Data sumber wajib konsisten.
 * - Waktu (D-02): tanggal mock tanpa jam disimpan sebagai pukul 12:00 WIB dalam
 *   UTC (konvensi yang sama dengan resources/js/utils/format.js), sehingga
 *   tanggal kalender WIB tidak bergeser. due_date (DATE) disimpan apa adanya.
 */
class DemoPerpustakaanSeeder extends Seeder
{
    private const FILE_DATA = __DIR__.'/data/perpustakaan-demo.json';

    /** @var array<string, array{0:int,1:int}> entitas => [dibuat, sudah ada] */
    private array $ringkasan = [];

    public function run(): void
    {
        $this->pastikanTargetAman();

        $data = json_decode(file_get_contents(self::FILE_DATA), true, flags: JSON_THROW_ON_ERROR);
        $this->pastikanNamaAkunAnggotaKonsisten($data);
        $sandiAdmin = $this->sandiAdmin();

        DB::transaction(function () use ($data, $sandiAdmin) {
            $pengguna = [];
            foreach ($data['pengguna'] as $p) {
                $admin = $p['peran'] === 'admin';
                $pengguna[$p['idPengguna']] = $this->ambilAtauBuat(User::class, 'users', ['username' => $p['username']], fn () => [
                    'name' => $admin ? $p['nama'] : null,
                    'password' => $admin ? $sandiAdmin : Str::random(64),
                    'role' => $p['peran'],
                    'is_active' => true,
                ]);
            }

            $anggota = [];
            foreach ($data['anggota'] as $a) {
                $anggota[$a['kodeAnggota']] = $this->ambilAtauBuat(Member::class, 'members', ['member_code' => $a['kodeAnggota']], fn () => [
                    'user_id' => $a['idPengguna'] ? $pengguna[$a['idPengguna']]->id : null,
                    'name' => $a['nama'],
                    'contact' => $a['kontak'],
                    'status' => $a['status'],
                ]);
            }

            $buku = [];
            foreach ($data['buku'] as $b) {
                $buku[$b['kodeBuku']] = $this->ambilAtauBuat(Book::class, 'books', ['book_code' => $b['kodeBuku']], fn () => [
                    'title' => $b['judul'],
                    'author' => $b['penulis'],
                    'publisher' => $b['penerbit'],
                    'publication_year' => $b['tahunTerbit'],
                    'category' => $b['kategori'],
                ]);
            }

            $eksemplar = [];
            foreach ($data['eksemplar'] as $e) {
                $eksemplar[$e['kodeEksemplar']] = $this->ambilAtauBuat(BookCopy::class, 'book_copies', ['copy_code' => $e['kodeEksemplar']], fn () => [
                    'book_id' => $buku[$e['kodeBuku']]->id,
                    'status' => $e['status'],
                ]);
            }

            $booking = [];
            $bookingAsalPeminjaman = [];
            foreach ($data['booking'] as $k) {
                $booking[$k['idBooking']] = $this->ambilAtauBuat(Booking::class, 'bookings', ['booking_code' => $k['idBooking']], fn () => [
                    'member_id' => $anggota[$k['kodeAnggota']]->id,
                    'copy_id' => $eksemplar[$k['kodeEksemplar']]->id,
                    'status' => $k['status'],
                    'submitted_at' => $this->tanggal($k['tanggalBooking']),
                    'decided_at' => $k['tanggalKeputusan'] ? $this->tanggal($k['tanggalKeputusan']) : null,
                    // Batch 2: booking Disetujui wajib punya Batas Pengambilan = keputusan + 48 jam (CHECK chk_bookings_expiry).
                    'expires_at' => $k['status'] === 'Disetujui' && $k['tanggalKeputusan']
                        ? LayananKedaluwarsaBooking::batasPengambilan($this->tanggal($k['tanggalKeputusan']))
                        : null,
                    'staff_id' => $k['diputuskanOleh'] ? $pengguna[$k['diputuskanOleh']]->id : null,
                    'rejection_reason' => $k['alasanPenolakan'],
                ]);
                if ($k['idPeminjaman']) {
                    $bookingAsalPeminjaman[$k['idPeminjaman']] = $booking[$k['idBooking']];
                }
            }

            $peminjaman = [];
            foreach ($data['peminjaman'] as $p) {
                $peminjaman[$p['idPeminjaman']] = $this->ambilAtauBuat(Loan::class, 'loans', ['loan_code' => $p['idPeminjaman']], fn () => [
                    'member_id' => $anggota[$p['kodeAnggota']]->id,
                    'copy_id' => $eksemplar[$p['kodeEksemplar']]->id,
                    'staff_id' => $pengguna[$p['diprosesOleh']]->id,
                    'booking_id' => ($bookingAsalPeminjaman[$p['idPeminjaman']] ?? null)?->id,
                    'loaned_at' => $this->tanggal($p['tanggalPinjam']),
                    'due_date' => $p['tanggalJatuhTempo'],
                    'status' => $p['status'],
                ]);
            }

            foreach ($data['pengembalian'] as $r) {
                $this->ambilAtauBuat(BookReturn::class, 'returns', ['return_code' => $r['idPengembalian']], fn () => [
                    'loan_id' => $peminjaman[$r['idPeminjaman']]->id,
                    'staff_id' => $pengguna[$r['diprosesOleh']]->id,
                    'returned_at' => $this->tanggal($r['tanggalKembali']),
                ]);
            }

            $pelanggaran = (new PemeriksaInvarianData)->periksa();
            if ($pelanggaran !== []) {
                throw new RuntimeException("Seeder dibatalkan, data tidak konsisten:\n- ".implode("\n- ", $pelanggaran));
            }
        });

        foreach ($this->ringkasan as $tabel => [$dibuat, $ada]) {
            $this->command?->line(sprintf('  %-12s dibuat: %2d, sudah ada (tidak diubah): %2d', $tabel, $dibuat, $ada));
        }
    }

    /**
     * Cari berdasarkan kunci unik; bila belum ada, buat dengan forceFill
     * (kolom sensitif sengaja tidak fillable di model). Rekaman yang sudah ada tidak diubah.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $kelas
     * @param  array<string, mixed>  $kunci
     * @param  callable(): array<string, mixed>  $nilai
     * @return T
     */
    private function ambilAtauBuat(string $kelas, string $tabel, array $kunci, callable $nilai): Model
    {
        $this->ringkasan[$tabel] ??= [0, 0];

        $model = $kelas::query()->where($kunci)->first();
        if ($model !== null) {
            $this->ringkasan[$tabel][1]++;

            return $model;
        }

        $model = new $kelas;
        $model->forceFill($kunci + $nilai())->save();
        $this->ringkasan[$tabel][0]++;

        return $model;
    }

    /** Tanggal mock (tanpa jam, kalender WIB) → instant UTC pukul 12:00 WIB. */
    private function tanggal(string $tanggal): CarbonImmutable
    {
        return WaktuPerpustakaan::instantDariTanggalLokal($tanggal, '12:00');
    }

    /**
     * D-01: nama akun Anggota tidak disimpan di users. Pastikan nama di data
     * sumber sama dengan members.name agar tidak ada nama yang hilang diam-diam.
     *
     * @param  array<string, list<array<string, mixed>>>  $data
     */
    private function pastikanNamaAkunAnggotaKonsisten(array $data): void
    {
        $namaAnggota = [];
        foreach ($data['anggota'] as $a) {
            if ($a['idPengguna']) {
                $namaAnggota[$a['idPengguna']] = $a['nama'];
            }
        }

        foreach ($data['pengguna'] as $p) {
            if ($p['peran'] !== 'anggota') {
                continue;
            }
            if (($namaAnggota[$p['idPengguna']] ?? null) !== $p['nama']) {
                throw new RuntimeException("Data seed tidak konsisten: akun {$p['username']} tidak tertaut ke Anggota bernama sama.");
            }
        }
    }

    private function pastikanTargetAman(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoPerpustakaanSeeder hanya untuk APP_ENV local/testing.');
        }

        $koneksi = DB::connection();
        if ($koneksi->getDriverName() !== 'mysql') {
            throw new RuntimeException('DemoPerpustakaanSeeder hanya untuk MySQL (driver saat ini: '.$koneksi->getDriverName().').');
        }

        $host = (string) $koneksi->getConfig('host');
        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new RuntimeException("DemoPerpustakaanSeeder hanya untuk MySQL lokal (host saat ini: {$host}).");
        }
    }

    /** Provisioning kredensial Admin awal (D-08): wajib dari .env lokal, tanpa nilai bawaan. */
    private function sandiAdmin(): string
    {
        $sandi = config('perpustakaan.seed.admin_password');
        if ($sandi === null || $sandi === '') {
            throw new RuntimeException('SEED_ADMIN_PASSWORD belum diisi di .env lokal. Seeder tidak membuat kata sandi bawaan.');
        }

        $panjang = mb_strlen((string) $sandi);
        if ($panjang < 6 || $panjang > 64) {
            throw new RuntimeException('SEED_ADMIN_PASSWORD harus 6–64 karakter (aturan L.3).');
        }

        return (string) $sandi;
    }
}
