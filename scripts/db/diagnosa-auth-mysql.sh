#!/usr/bin/env bash
# Phase 7A — Diagnostik autentikasi MySQL lokal (TAHAP A, READ-ONLY).
#
# Hanya pernyataan SELECT. Tidak mengubah konfigurasi, akun, grant, plugin,
# atau database, dan tidak me-restart MySQL. Kolom authentication_string
# (hash kata sandi) tidak dibaca. Socket/datadir dideteksi dari proses aktif.
#
# Pemakaian (di Terminal):
#   bash scripts/db/diagnosa-auth-mysql.sh                         # admin = user MySQL 'root', kata sandi diminta
#   MYSQL_ADMIN_USER=nama bash scripts/db/diagnosa-auth-mysql.sh
#   MYSQL_LOGIN_PATH=nama bash scripts/db/diagnosa-auth-mysql.sh   # tanpa prompt (mysql_config_editor)

set -euo pipefail
cd "$(dirname "$0")/../.."
# shellcheck source=lib-mysql-lokal.sh
source scripts/db/lib-mysql-lokal.sh

deteksi_mysql
siapkan_auth_admin

mysql_admin --table <<'SQL'
SELECT @@version AS versi, @@version_comment AS distribusi, CURRENT_USER() AS masuk_sebagai;

SELECT @@datadir AS datadir, @@socket AS socket, @@port AS port,
       @@bind_address AS bind_address, @@mysqlx_bind_address AS mysqlx_bind_address,
       @@authentication_policy AS authentication_policy;

SELECT PLUGIN_NAME, PLUGIN_STATUS, LOAD_OPTION
  FROM information_schema.PLUGINS
 WHERE PLUGIN_NAME IN ('mysql_native_password', 'caching_sha2_password', 'sha256_password');

-- Komponen kebijakan kata sandi dapat menolak CREATE USER pada Tahap B.
SELECT IFNULL(GROUP_CONCAT(component_urn), '(tidak ada komponen terpasang)') AS komponen
  FROM mysql.component;

SELECT plugin, COUNT(*) AS jumlah_akun
  FROM mysql.user GROUP BY plugin ORDER BY jumlah_akun DESC;

SELECT IF(user = '', '(ANONIM)', user) AS user, host, plugin, account_locked, password_expired
  FROM mysql.user
 WHERE user IN ('', 'root') OR plugin NOT IN ('caching_sha2_password')
 ORDER BY plugin, user, host;

SELECT 'database_target' AS cek, s.nama,
       IF(sc.SCHEMA_NAME IS NULL, 'belum ada', 'SUDAH ADA') AS status
  FROM (SELECT 'sim_perpustakaan_db' AS nama UNION ALL SELECT 'sim_perpustakaan_test') s
  LEFT JOIN information_schema.SCHEMATA sc ON sc.SCHEMA_NAME = s.nama;

SELECT 'user_target' AS cek, s.nama,
       IF(COUNT(u.user) = 0, 'belum ada', CONCAT('SUDAH ADA (', COUNT(u.user), ' host)')) AS status
  FROM (SELECT 'sim_perpus_app' AS nama UNION ALL SELECT 'sim_perpus_test') s
  LEFT JOIN mysql.user u ON u.user = s.nama
 GROUP BY s.nama;
SQL
