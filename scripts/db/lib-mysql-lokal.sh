# shellcheck shell=bash
# Phase 7A — helper bersama skrip MySQL lokal. Di-source, bukan dijalankan.
#
# Mendeteksi instance mysqld yang SEDANG berjalan dari MYSQL_BASEDIR (default
# Homebrew mysql@8.4): socket, datadir, dan listener TCP dibaca dari proses
# aktif, tidak di-hardcode. Gagal aman bila tidak tepat satu instance.
#
# Kredensial admin:
#   - MYSQL_ADMIN_DEFAULTS_FILE=<file 600> → --defaults-extra-file, atau
#   - MYSQL_LOGIN_PATH=<nama>  → --login-path (dibuat pemilik dengan mysql_config_editor), atau
#   - prompt interaktif `-p` (wajib ada terminal; tanpa TTY skrip berhenti).
# Kata sandi tidak pernah muncul di argumen perintah maupun output.

gagal() { echo "DIBATALKAN: $*" >&2; exit 1; }

deteksi_mysql() {
    MYSQL_BASEDIR=${MYSQL_BASEDIR:-/opt/homebrew/opt/mysql@8.4}
    MYSQL_BIN=${MYSQL_BIN:-$MYSQL_BASEDIR/bin/mysql}
    [[ -x "$MYSQL_BIN" ]] || gagal "Klien MySQL tidak ditemukan: $MYSQL_BIN"

    local pids jumlah
    pids=$(pgrep -f "^$MYSQL_BASEDIR/bin/mysqld( |\$)" || true)
    jumlah=$(printf '%s\n' "$pids" | grep -c . || true)
    [[ "$jumlah" -eq 1 ]] || gagal "Harus tepat satu mysqld dari $MYSQL_BASEDIR yang berjalan (ditemukan: $jumlah)."
    MYSQL_PID=$pids

    MYSQL_DATADIR=$(ps -o command= -p "$MYSQL_PID" | grep -oE -- '--datadir=[^ ]+' | cut -d= -f2- || true)
    [[ -n "$MYSQL_DATADIR" ]] || gagal "Tidak dapat membaca --datadir dari proses mysqld."
    MYSQL_DATADIR="${MYSQL_DATADIR%/}/"

    MYSQL_SOCKET=${MYSQL_SOCKET:-$(lsof -a -p "$MYSQL_PID" -U -Fn 2>/dev/null | sed -n 's/^n//p' | grep -E '/mysql\.sock$' | head -n 1)}
    [[ -S "$MYSQL_SOCKET" ]] || gagal "Socket mysqld (PID $MYSQL_PID) tidak terdeteksi."

    MYSQL_LISTEN=$(lsof -a -p "$MYSQL_PID" -iTCP -sTCP:LISTEN -nP -Fn 2>/dev/null | sed -n 's/^n//p' | sort -u | tr '\n' ' ')
    local alamat
    for alamat in $MYSQL_LISTEN; do
        [[ "$alamat" == 127.0.0.1:* || "$alamat" == \[::1\]:* ]] || gagal "mysqld mendengarkan di alamat non-loopback: $alamat"
    done

    echo "Instance terdeteksi: PID $MYSQL_PID | datadir $MYSQL_DATADIR | socket $MYSQL_SOCKET | TCP ${MYSQL_LISTEN:-(tidak ada)}"
}

# Mengisi array MYSQL_AUTH dengan argumen autentikasi admin yang aman.
siapkan_auth_admin() {
    if [[ -n "${MYSQL_ADMIN_DEFAULTS_FILE:-}" ]]; then
        # File opsi [client] berizin 600 berisi user/password admin; tidak pernah dicetak.
        [[ -f "$MYSQL_ADMIN_DEFAULTS_FILE" ]] || gagal "File opsi admin tidak ditemukan."
        [[ "$(stat -f '%Lp' "$MYSQL_ADMIN_DEFAULTS_FILE")" == "600" ]] || gagal "File opsi admin harus berizin 600."
        MYSQL_AUTH=("--defaults-extra-file=$MYSQL_ADMIN_DEFAULTS_FILE")
        echo "Autentikasi admin: file opsi sementara (izin 600)"
    elif [[ -n "${MYSQL_LOGIN_PATH:-}" ]]; then
        MYSQL_AUTH=("--login-path=$MYSQL_LOGIN_PATH")
        echo "Autentikasi admin: login-path '$MYSQL_LOGIN_PATH'"
    else
        ( : < /dev/tty ) 2>/dev/null || gagal "Tidak ada terminal untuk prompt kata sandi. Jalankan di Terminal, atau set MYSQL_LOGIN_PATH."
        MYSQL_AUTH=("--user=${MYSQL_ADMIN_USER:-root}" "-p")
        echo "Autentikasi admin: user MySQL '${MYSQL_ADMIN_USER:-root}' (kata sandi diminta klien mysql)"
    fi
}

# Jalankan klien mysql sebagai admin lewat socket terdeteksi. Argumen tambahan diteruskan.
mysql_admin() {
    # --login-path harus menjadi opsi pertama.
    "$MYSQL_BIN" "${MYSQL_AUTH[@]}" --protocol=SOCKET --socket="$MYSQL_SOCKET" "$@"
}
