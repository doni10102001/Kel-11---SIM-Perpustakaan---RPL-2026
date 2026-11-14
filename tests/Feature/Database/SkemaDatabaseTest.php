<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Phase 7A — konektivitas, hasil migration, dan struktur skema pada MySQL 8.4.
 */
class SkemaDatabaseTest extends MySqlTestCase
{
    private const TABEL_DOMAIN = ['users', 'members', 'books', 'book_copies', 'bookings', 'loans', 'returns', 'audit_logs'];

    private function skema(): string
    {
        return DB::connection()->getDatabaseName();
    }

    public function test_terhubung_ke_mysql_84_pada_database_uji_dengan_strict_mode(): void
    {
        $info = DB::selectOne('SELECT VERSION() AS versi, DATABASE() AS db, @@SESSION.sql_mode AS mode');

        $this->assertStringStartsWith('8.4.', $info->versi, 'Server harus MySQL 8.4 LTS.');
        $this->assertStringEndsWith('_test', $info->db);
        $this->assertStringContainsString('STRICT_TRANS_TABLES', $info->mode);
    }

    public function test_zona_waktu_sesi_dan_aplikasi_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('+00:00', DB::selectOne('SELECT @@SESSION.time_zone AS tz')->tz);
        $this->assertSame(
            0,
            (int) DB::selectOne('SELECT TIMESTAMPDIFF(SECOND, NOW(), UTC_TIMESTAMP()) AS selisih')->selisih,
            'NOW() di sesi MySQL harus sama dengan UTC.',
        );
    }

    public function test_semua_migration_tercatat_sudah_dijalankan(): void
    {
        $berkas = collect(File::files(database_path('migrations')))
            ->map(fn ($f) => $f->getFilenameWithoutExtension())
            ->sort()->values()->all();
        $tercatat = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();

        $this->assertSame($berkas, $tercatat);
    }

    public function test_delapan_tabel_domain_innodb_utf8mb4(): void
    {
        $tabel = collect(DB::select(
            'SELECT TABLE_NAME AS nama, ENGINE AS engine, TABLE_COLLATION AS kolasi FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$this->skema()],
        ))->keyBy('nama');

        foreach (self::TABEL_DOMAIN as $nama) {
            $this->assertTrue($tabel->has($nama), "Tabel {$nama} tidak ada.");
            $this->assertSame('InnoDB', $tabel[$nama]->engine, "Engine {$nama}");
            $this->assertSame('utf8mb4_0900_ai_ci', $tabel[$nama]->kolasi, "Collation {$nama}");
        }
    }

    public function test_tipe_kolom_penting_sesuai_desain(): void
    {
        $kolom = fn (string $t, string $k) => DB::selectOne(
            'SELECT COLUMN_TYPE AS tipe, IS_NULLABLE AS boleh_null, EXTRA AS extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->skema(), $t, $k],
        );

        $this->assertSame('date', $kolom('loans', 'due_date')->tipe);
        $this->assertSame('NO', $kolom('loans', 'due_date')->boleh_null);
        $this->assertSame('datetime(6)', $kolom('loans', 'loaned_at')->tipe);
        $this->assertSame('datetime(6)', $kolom('bookings', 'submitted_at')->tipe);
        $this->assertSame('YES', $kolom('bookings', 'decided_at')->boleh_null);
        $this->assertSame('YES', $kolom('members', 'user_id')->boleh_null);
        $this->assertSame('YES', $kolom('loans', 'booking_id')->boleh_null);
        $this->assertSame('NO', $kolom('loans', 'staff_id')->boleh_null);
        $this->assertSame('varchar(200)', $kolom('bookings', 'rejection_reason')->tipe);
        $this->assertSame('STORED GENERATED', $kolom('bookings', 'reserved_copy_id')->extra);
        $this->assertSame('STORED GENERATED', $kolom('loans', 'active_copy_id')->extra);
        $this->assertSame('YES', $kolom('users', 'name')->boleh_null, 'D-01: name hanya untuk Admin.');
        $this->assertSame('YES', $kolom('users', 'remember_token')->boleh_null, 'D-05.');
    }

    public function test_indeks_sesuai_keputusan_d07(): void
    {
        $indeks = collect(DB::select(
            'SELECT DISTINCT TABLE_NAME AS t, INDEX_NAME AS i FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ?',
            [$this->skema()],
        ))->map(fn ($r) => "{$r->t}.{$r->i}")->all();

        $this->assertNotContains('members.idx_members_status', $indeks);
        $this->assertContains('loans.idx_loans_staff', $indeks);
        $this->assertContains('returns.idx_returns_staff', $indeks);
        $this->assertNotContains('password_reset_tokens', DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $this->skema())->pluck('TABLE_NAME')->all(), 'D-04.');
    }

    public function test_kolom_kosakata_biner_sedangkan_username_dan_kode_case_insensitive(): void
    {
        $kolasi = fn (string $t, string $k) => DB::selectOne(
            'SELECT COLLATION_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->skema(), $t, $k],
        )->c;

        foreach ([['users', 'role'], ['members', 'status'], ['book_copies', 'status'], ['bookings', 'status'], ['loans', 'status']] as [$t, $k]) {
            $this->assertSame('utf8mb4_bin', $kolasi($t, $k), "{$t}.{$k}");
        }
        foreach ([['users', 'username'], ['members', 'member_code'], ['books', 'book_code'], ['book_copies', 'copy_code']] as [$t, $k]) {
            $this->assertSame('utf8mb4_0900_ai_ci', $kolasi($t, $k), "{$t}.{$k}");
        }
    }

    public function test_tautan_booking_peminjaman_hanya_satu_sumber_dan_belum_ada_kolom_kedaluwarsa(): void
    {
        $kolomBooking = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $this->skema())->where('TABLE_NAME', 'bookings')
            ->pluck('COLUMN_NAME')->all();

        $this->assertNotContains('loan_id', $kolomBooking, 'Tautan booking→loan hanya lewat loans.booking_id.');
        foreach ($kolomBooking as $nama) {
            $this->assertDoesNotMatchRegularExpression('/expir|kedaluwarsa|batas_waktu/i', $nama, 'Kolom kedaluwarsa booking belum disetujui.');
        }
        $this->assertNotContains('email', DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $this->skema())->where('TABLE_NAME', 'users')->pluck('COLUMN_NAME')->all());
    }

    public function test_indeks_unik_yang_disetujui_tersedia(): void
    {
        $unik = collect(DB::select(
            'SELECT DISTINCT TABLE_NAME AS t, INDEX_NAME AS i FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND NON_UNIQUE = 0',
            [$this->skema()],
        ))->map(fn ($r) => "{$r->t}.{$r->i}")->all();

        foreach ([
            'users.uq_users_username',
            'members.uq_members_member_code',
            'members.uq_members_user_id',
            'books.uq_books_book_code',
            'book_copies.uq_book_copies_copy_code',
            'bookings.uq_bookings_booking_code',
            'bookings.uq_bookings_reserved_copy',
            'loans.uq_loans_loan_code',
            'loans.uq_loans_booking_id',
            'loans.uq_loans_active_copy',
            'returns.uq_returns_return_code',
            'returns.uq_returns_loan_id',
        ] as $indeks) {
            $this->assertContains($indeks, $unik);
        }
    }

    public function test_check_constraint_status_tersedia(): void
    {
        $check = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $this->skema())->where('CONSTRAINT_TYPE', 'CHECK')
            ->pluck('CONSTRAINT_NAME')->all();

        foreach ([
            'chk_users_role',
            'chk_users_anggota_tanpa_status_akses',
            'chk_users_nama_sesuai_peran',
            'chk_members_status',
            'chk_book_copies_status',
            'chk_bookings_status',
            'chk_loans_status',
        ] as $nama) {
            $this->assertContains($nama, $check);
        }
    }

    public function test_semua_foreign_key_domain_restrict_tanpa_cascade(): void
    {
        $fk = DB::select(
            'SELECT CONSTRAINT_NAME AS nama, TABLE_NAME AS t, DELETE_RULE AS hapus, UPDATE_RULE AS ubah FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ?',
            [$this->skema()],
        );

        $nama = array_column($fk, 'nama');
        foreach ([
            'fk_members_user', 'fk_book_copies_book',
            'fk_bookings_member', 'fk_bookings_copy', 'fk_bookings_staff',
            'fk_loans_member', 'fk_loans_copy', 'fk_loans_staff', 'fk_loans_booking',
            'fk_returns_loan', 'fk_returns_staff', 'fk_audit_logs_actor',
        ] as $harapan) {
            $this->assertContains($harapan, $nama);
        }

        foreach ($fk as $baris) {
            $this->assertContains($baris->hapus, ['RESTRICT', 'NO ACTION'], "{$baris->nama} ON DELETE {$baris->hapus}");
            $this->assertContains($baris->ubah, ['RESTRICT', 'NO ACTION'], "{$baris->nama} ON UPDATE {$baris->ubah}");
        }
    }
}
