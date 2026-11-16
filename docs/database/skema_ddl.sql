-- Phase 7A — PRATINJAU DDL MySQL 8.4, dihasilkan dari database/migrations/ dalam mode pretend
-- (koneksi palsu tanpa PDO, tanpa koneksi server). JANGAN dijalankan manual; gunakan `php artisan migrate`.
-- Tabel infrastruktur cache/jobs bawaan Laravel tidak ditampilkan (tetap dibuat oleh migration).
-- Revisi 14 Nov 2026 setelah keputusan D-01..D-08 disetujui: users.name nullable + chk_users_nama_sesuai_peran (D-01),
-- tanpa idx_members_status (D-07). Zona waktu (D-02) tidak mengubah DDL.

-- 0001_01_01_000000_create_users_table
create table `users` (`id` bigint unsigned not null auto_increment primary key, `username` varchar(100) not null, `name` varchar(100) null, `password` varchar(255) not null, `role` varchar(16) collate 'utf8mb4_bin' not null, `is_active` tinyint(1) not null default '1', `remember_token` varchar(100) null, `created_at` datetime(6) not null default CURRENT_TIMESTAMP(6), `updated_at` datetime(6) not null default CURRENT_TIMESTAMP(6) on update CURRENT_TIMESTAMP(6)) default character set utf8mb4 collate 'utf8mb4_0900_ai_ci' engine = InnoDB;
alter table `users` add unique `uq_users_username`(`username`);
ALTER TABLE users
    ADD CONSTRAINT chk_users_role CHECK (role IN ('admin', 'anggota')),
    ADD CONSTRAINT chk_users_anggota_tanpa_status_akses CHECK (role = 'admin' OR is_active = 1),
    ADD CONSTRAINT chk_users_nama_sesuai_peran CHECK (
        (role = 'admin' AND name IS NOT NULL AND TRIM(name) <> '')
        OR (role = 'anggota' AND name IS NULL)
    );
create table `sessions` (`id` varchar(255) not null, `user_id` bigint unsigned null, `ip_address` varchar(45) null, `user_agent` text null, `payload` longtext not null, `last_activity` int not null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_0900_ai_ci' engine = InnoDB;
alter table `sessions` add index `sessions_user_id_index`(`user_id`);
alter table `sessions` add index `sessions_last_activity_index`(`last_activity`);

-- 2026_11_14_000001_create_members_table
create table `members` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned null, `member_code` varchar(30) not null, `name` varchar(100) not null, `contact` varchar(50) not null, `status` varchar(16) collate 'utf8mb4_bin' not null, `created_at` datetime(6) not null default CURRENT_TIMESTAMP(6), `updated_at` datetime(6) not null default CURRENT_TIMESTAMP(6) on update CURRENT_TIMESTAMP(6)) default character set utf8mb4 collate 'utf8mb4_0900_ai_ci' engine = InnoDB;
alter table `members` add unique `uq_members_member_code`(`member_code`);
alter table `members` add unique `uq_members_user_id`(`user_id`);
alter table `members` add constraint `fk_members_user` foreign key (`user_id`) references `users` (`id`) on delete restrict on update restrict;
ALTER TABLE members ADD CONSTRAINT chk_members_status CHECK (status IN ('Aktif', 'Nonaktif'));

-- 2026_11_14_000002_create_books_table
create table `books` (`id` bigint unsigned not null auto_increment primary key, `book_code` varchar(30) not null, `title` varchar(200) not null, `author` varchar(100) not null, `publisher` varchar(100) not null, `publication_year` smallint unsigned not null, `category` varchar(50) not null, `created_at` datetime(6) not null default CURRENT_TIMESTAMP(6), `updated_at` datetime(6) not null default CURRENT_TIMESTAMP(6) on update CURRENT_TIMESTAMP(6)) default character set utf8mb4 collate 'utf8mb4_0900_ai_ci' engine = InnoDB;
alter table `books` add unique `uq_books_book_code`(`book_code`);
alter table `books` add index `idx_books_title`(`title`);
alter table `books` add index `idx_books_category`(`category`);

-- 2026_11_14_000003_create_book_copies_table
create table `book_copies` (`id` bigint unsigned not null auto_increment primary key, `book_id` bigint unsigned not null, `copy_code` varchar(30) not null, `status` varchar(16) collate 'utf8mb4_bin' not null default 'Tersedia', `created_at` datetime(6) not null default CURRENT_TIMESTAMP(6), `updated_at` datetime(6) not null default CURRENT_TIMESTAMP(6) on update CURRENT_TIMESTAMP(6)) default character set utf8mb4 collate 'utf8mb4_0900_ai_ci' engine = InnoDB;
alter table `book_copies` add unique `uq_book_copies_copy_code`(`copy_code`);
alter table `book_copies` add index `idx_copies_book_status`(`book_id`, `status`);
alter table `book_copies` add constraint `fk_book_copies_book` foreign key (`book_id`) references `books` (`id`) on delete restrict on update restrict;
ALTER TABLE book_copies ADD CONSTRAINT chk_book_copies_status CHECK (status IN ('Tersedia', 'Dipesan', 'Dipinjam'));

-- 2026_11_14_000004_create_bookings_table
create table `bookings` (`id` bigint unsigned not null auto_increment primary key, `booking_code` varchar(30) not null, `member_id` bigint unsigned not null, `copy_id` bigint unsigned not null, `status` varchar(20) collate 'utf8mb4_bin' not null default 'Diajukan', `submitted_at` datetime(6) not null, `decided_at` datetime(6) null, `staff_id` bigint unsigned null, `rejection_reason` varchar(200) null, `reserved_copy_id` bigint unsigned as (CASE WHEN `status` = 'Disetujui' THEN `copy_id` ELSE NULL END) stored, `created_at` datetime(6) not null default CURRENT_TIMESTAMP(6), `updated_at` datetime(6) not null default CURRENT_TIMESTAMP(6) on update CURRENT_TIMESTAMP(6)) default character set utf8mb4 collate 'utf8mb4_0900_ai_ci' engine = InnoDB;
alter table `bookings` add unique `uq_bookings_booking_code`(`booking_code`);
alter table `bookings` add unique `uq_bookings_reserved_copy`(`reserved_copy_id`);
alter table `bookings` add index `idx_booking_member_status`(`member_id`, `status`);
alter table `bookings` add index `idx_booking_copy_status`(`copy_id`, `status`);
alter table `bookings` add index `idx_booking_status_submitted`(`status`, `submitted_at`);
alter table `bookings` add index `idx_booking_staff`(`staff_id`);
alter table `bookings` add constraint `fk_bookings_member` foreign key (`member_id`) references `members` (`id`) on delete restrict on update restrict;
alter table `bookings` add constraint `fk_bookings_copy` foreign key (`copy_id`) references `book_copies` (`id`) on delete restrict on update restrict;
alter table `bookings` add constraint `fk_bookings_staff` foreign key (`staff_id`) references `users` (`id`) on delete restrict on update restrict;
ALTER TABLE bookings ADD CONSTRAINT chk_bookings_status CHECK (status IN ('Diajukan', 'Disetujui', 'Ditolak', 'Dibatalkan', 'Direalisasikan'));

-- 2026_11_14_000005_create_loans_table
create table `loans` (`id` bigint unsigned not null auto_increment primary key, `loan_code` varchar(30) not null, `member_id` bigint unsigned not null, `copy_id` bigint unsigned not null, `staff_id` bigint unsigned not null, `booking_id` bigint unsigned null, `loaned_at` datetime(6) not null, `due_date` date not null, `status` varchar(16) collate 'utf8mb4_bin' not null default 'Aktif', `active_copy_id` bigint unsigned as (CASE WHEN `status` = 'Aktif' THEN `copy_id` ELSE NULL END) stored, `created_at` datetime(6) not null default CURRENT_TIMESTAMP(6), `updated_at` datetime(6) not null default CURRENT_TIMESTAMP(6) on update CURRENT_TIMESTAMP(6)) default character set utf8mb4 collate 'utf8mb4_0900_ai_ci' engine = InnoDB;
alter table `loans` add unique `uq_loans_loan_code`(`loan_code`);
alter table `loans` add unique `uq_loans_booking_id`(`booking_id`);
alter table `loans` add unique `uq_loans_active_copy`(`active_copy_id`);
alter table `loans` add index `idx_loans_member_status`(`member_id`, `status`);
alter table `loans` add index `idx_loans_copy_status`(`copy_id`, `status`);
alter table `loans` add index `idx_loans_staff`(`staff_id`);
alter table `loans` add index `idx_loans_loaned_at`(`loaned_at`);
alter table `loans` add index `idx_loans_due_date`(`due_date`);
alter table `loans` add constraint `fk_loans_member` foreign key (`member_id`) references `members` (`id`) on delete restrict on update restrict;
alter table `loans` add constraint `fk_loans_copy` foreign key (`copy_id`) references `book_copies` (`id`) on delete restrict on update restrict;
alter table `loans` add constraint `fk_loans_staff` foreign key (`staff_id`) references `users` (`id`) on delete restrict on update restrict;
alter table `loans` add constraint `fk_loans_booking` foreign key (`booking_id`) references `bookings` (`id`) on delete restrict on update restrict;
ALTER TABLE loans ADD CONSTRAINT chk_loans_status CHECK (status IN ('Aktif', 'Selesai'));

-- 2026_11_14_000006_create_returns_table
create table `returns` (`id` bigint unsigned not null auto_increment primary key, `return_code` varchar(30) not null, `loan_id` bigint unsigned not null, `staff_id` bigint unsigned not null, `returned_at` datetime(6) not null, `created_at` datetime(6) not null default CURRENT_TIMESTAMP(6), `updated_at` datetime(6) not null default CURRENT_TIMESTAMP(6) on update CURRENT_TIMESTAMP(6)) default character set utf8mb4 collate 'utf8mb4_0900_ai_ci' engine = InnoDB;
alter table `returns` add unique `uq_returns_return_code`(`return_code`);
alter table `returns` add unique `uq_returns_loan_id`(`loan_id`);
alter table `returns` add index `idx_returns_staff`(`staff_id`);
alter table `returns` add index `idx_returns_returned_at`(`returned_at`);
alter table `returns` add constraint `fk_returns_loan` foreign key (`loan_id`) references `loans` (`id`) on delete restrict on update restrict;
alter table `returns` add constraint `fk_returns_staff` foreign key (`staff_id`) references `users` (`id`) on delete restrict on update restrict;

-- 2026_11_14_000007_create_audit_logs_table
create table `audit_logs` (`id` bigint unsigned not null auto_increment primary key, `actor_id` bigint unsigned null, `action` varchar(80) not null, `subject_type` varchar(80) not null, `subject_id` bigint unsigned not null, `metadata` json null, `created_at` datetime(6) not null default CURRENT_TIMESTAMP(6)) default character set utf8mb4 collate 'utf8mb4_0900_ai_ci' engine = InnoDB;
alter table `audit_logs` add index `idx_audit_actor_time`(`actor_id`, `created_at`);
alter table `audit_logs` add index `idx_audit_subject`(`subject_type`, `subject_id`, `created_at`);
alter table `audit_logs` add index `idx_audit_action_time`(`action`, `created_at`);
alter table `audit_logs` add constraint `fk_audit_logs_actor` foreign key (`actor_id`) references `users` (`id`) on delete restrict on update restrict;
