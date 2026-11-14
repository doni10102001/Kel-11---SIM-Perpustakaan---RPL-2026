#!/usr/bin/env bash
# Phase 7A — Siapkan database MySQL LOKAL untuk SIM Perpustakaan.
#
# Dijalankan oleh PEMILIK PROYEK dengan akun administrator MySQL lokal.
#
# Tahap 1 (read-only): verifikasi instance target (versi 8.4.x, datadir
#   Homebrew, bind loopback) dan pastikan database/user belum ada.
# Tahap 2 (setelah konfirmasi "YA"): buat
#   - database development  DB_DATABASE       (mis. sim_perpustakaan_db)
#   - database pengujian    DB_TEST_DATABASE  (wajib berakhiran _test)
#   - user development      DB_USERNAME       → hanya DB development
#   - user pengujian        DB_TEST_USERNAME  → hanya DB pengujian
#   masing-masing @localhost dan @127.0.0.1, dengan hak eksplisit (least privilege).
#
# Keamanan:
#   - Nilai dibaca dari .env tanpa mengeksekusi isinya; kata sandi TIDAK dicetak
#     dan dikirim lewat stdin (tidak terlihat di daftar proses).
#   - CREATE DATABASE / CREATE USER tanpa "IF NOT EXISTS" dan tanpa DROP: bila
#     sesuatu sudah ada, klien mysql berhenti pada error pertama.
#   - Tidak menyentuh database lain, konfigurasi server, atau akun lain.
#
# Pemakaian:
#   bash scripts/db/siapkan-mysql-lokal.sh
#   MYSQL_ADMIN_USER=nama_admin bash scripts/db/siapkan-mysql-lokal.sh
#   MYSQL_LOGIN_PATH=nama bash scripts/db/siapkan-mysql-lokal.sh   # tanpa prompt kata sandi
# Socket/datadir dideteksi dari proses mysqld aktif (scripts/db/lib-mysql-lokal.sh).

set -euo pipefail

cd "$(dirname "$0")/../.."
# shellcheck source=lib-mysql-lokal.sh
source scripts/db/lib-mysql-lokal.sh

[[ -f .env ]] || { echo "File .env tidak ditemukan." >&2; exit 1; }

baca_env() {
    local nilai
    nilai=$(grep -E "^$1=" .env | tail -n 1 | cut -d '=' -f 2- || true)
    nilai="${nilai%\"}"; nilai="${nilai#\"}"
    printf '%s' "$nilai"
}

DB_HOST=$(baca_env DB_HOST)
DB_DATABASE=$(baca_env DB_DATABASE)
DB_USERNAME=$(baca_env DB_USERNAME)
DB_PASSWORD=$(baca_env DB_PASSWORD)
DB_TEST_DATABASE=$(baca_env DB_TEST_DATABASE)
DB_TEST_USERNAME=$(baca_env DB_TEST_USERNAME)
DB_TEST_PASSWORD=$(baca_env DB_TEST_PASSWORD)

case "$DB_HOST" in 127.0.0.1|localhost) ;; *) gagal "DB_HOST harus 127.0.0.1/localhost (sekarang '$DB_HOST')." ;; esac

for nama in "$DB_DATABASE" "$DB_TEST_DATABASE" "$DB_USERNAME" "$DB_TEST_USERNAME"; do
    [[ "$nama" =~ ^[A-Za-z0-9_]{1,32}$ ]] || gagal "Nama tidak valid: '$nama' (huruf, angka, _; maks. 32)."
done
[[ "$DB_TEST_DATABASE" == *_test ]] || gagal "DB_TEST_DATABASE wajib berakhiran _test."
[[ "$DB_TEST_DATABASE" != "$DB_DATABASE" ]] || gagal "DB_TEST_DATABASE tidak boleh sama dengan DB_DATABASE."
[[ "$DB_TEST_USERNAME" != "$DB_USERNAME" ]] || gagal "DB_TEST_USERNAME harus berbeda dari DB_USERNAME (least privilege)."
for sandi in "$DB_PASSWORD" "$DB_TEST_PASSWORD"; do
    [[ ${#sandi} -ge 16 ]] || gagal "DB_PASSWORD/DB_TEST_PASSWORD kosong atau < 16 karakter."
    [[ "$sandi" != *"'"* && "$sandi" != *"\\"* ]] || gagal "Kata sandi tidak boleh memuat kutip tunggal atau backslash."
done

deteksi_mysql
siapkan_auth_admin

echo "== Tahap 1: verifikasi target (read-only) =="
echo "Bila muncul ERROR 1524, akun admin ini memakai mysql_native_password; lihat docs/database/phase7/PHASE_7A_DATABASE_SETUP.md §2.2–2.3."

INFO=$(mysql_admin --skip-column-names --batch <<SQL
SELECT CONCAT('versi=', @@version);
SELECT CONCAT('datadir=', @@datadir);
SELECT CONCAT('socket=', @@socket);
SELECT CONCAT('bind=', @@bind_address);
SELECT CONCAT('port=', @@port);
SELECT CONCAT('schema_ada=', SCHEMA_NAME) FROM information_schema.SCHEMATA
    WHERE SCHEMA_NAME IN ('$DB_DATABASE', '$DB_TEST_DATABASE');
SELECT CONCAT('user_ada=', user, '@', host) FROM mysql.user
    WHERE user IN ('$DB_USERNAME', '$DB_TEST_USERNAME');
SQL
)

echo "$INFO" | grep -vE '^(schema_ada|user_ada)=' || true

grep -q '^versi=8\.4\.' <<<"$INFO" || gagal "Server bukan MySQL 8.4.x."
grep -qx "datadir=$MYSQL_DATADIR" <<<"$INFO" || gagal "Datadir server tidak sama dengan proses terdeteksi ($MYSQL_DATADIR)."
grep -qx "socket=$MYSQL_SOCKET" <<<"$INFO" || gagal "Socket server tidak sama dengan socket terdeteksi ($MYSQL_SOCKET)."
grep -qx 'bind=127.0.0.1' <<<"$INFO" || gagal "bind_address bukan 127.0.0.1."
if grep -qE '^(schema_ada|user_ada)=' <<<"$INFO"; then
    echo "$INFO" | grep -E '^(schema_ada|user_ada)='
    gagal "Database/user target sudah ada. Tidak ada yang diubah."
fi

echo
echo "Akan dibuat:"
echo "  DB  $DB_DATABASE, $DB_TEST_DATABASE (utf8mb4 / utf8mb4_0900_ai_ci)"
echo "  USR $DB_USERNAME@{localhost,127.0.0.1} → hanya $DB_DATABASE"
echo "  USR $DB_TEST_USERNAME@{localhost,127.0.0.1} → hanya $DB_TEST_DATABASE"
echo "  Hak: SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES (tanpa GRANT OPTION, tanpa hak global)"
if [[ -n "${SETUJU_TAHAP_B:-}" ]]; then
    # Konfirmasi non-interaktif: persetujuan tertulis pemilik proyek (sesi tanpa terminal).
    jawab=$SETUJU_TAHAP_B
    echo "Konfirmasi dari SETUJU_TAHAP_B: $jawab"
else
    read -r -p "Ketik YA untuk melanjutkan: " jawab < /dev/tty
fi
[[ "$jawab" == "YA" ]] || gagal "Tidak dikonfirmasi."

HAK="SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES"

echo "== Tahap 2: pembuatan =="
mysql_admin <<SQL
CREATE DATABASE \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE \`$DB_TEST_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER '$DB_USERNAME'@'localhost' IDENTIFIED WITH caching_sha2_password BY '$DB_PASSWORD';
CREATE USER '$DB_USERNAME'@'127.0.0.1' IDENTIFIED WITH caching_sha2_password BY '$DB_PASSWORD';
CREATE USER '$DB_TEST_USERNAME'@'localhost' IDENTIFIED WITH caching_sha2_password BY '$DB_TEST_PASSWORD';
CREATE USER '$DB_TEST_USERNAME'@'127.0.0.1' IDENTIFIED WITH caching_sha2_password BY '$DB_TEST_PASSWORD';
GRANT $HAK ON \`$DB_DATABASE\`.* TO '$DB_USERNAME'@'localhost', '$DB_USERNAME'@'127.0.0.1';
GRANT $HAK ON \`$DB_TEST_DATABASE\`.* TO '$DB_TEST_USERNAME'@'localhost', '$DB_TEST_USERNAME'@'127.0.0.1';
SQL

echo "Selesai. Lanjutkan dengan langkah eksekusi di docs/database/phase7/PHASE_7A_DATABASE_SETUP.md §2.5."
