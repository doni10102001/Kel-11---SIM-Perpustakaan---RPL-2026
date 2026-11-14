<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ReadonlyField from '@/Components/ReadonlyField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { kodeBerikutnya } from '@/utils/pencarian.js';
import { daftarStatus } from '@/utils/status.js';
import {
    akunAnggota,
    relasiAkunValid,
    transaksiBerjalanAnggota,
    validasiAksesLogin,
    validasiAnggota,
} from '@/utils/validasiMaster.js';

const { data } = useDataPerpustakaan();
const { rute, parameter, halaman } = useDemo();

const kodeAsal = parameter.value.kodeAnggota ?? null;
const mode = kodeAsal ? 'ubah' : 'tambah';
const anggota = mode === 'ubah' ? data.anggota.find((a) => a.kodeAnggota === kodeAsal) : null;
const tidakDitemukan = mode === 'ubah' && !anggota;

const form = reactive({
    kodeAnggota: anggota?.kodeAnggota ?? '',
    nama: anggota?.nama ?? '',
    kontak: anggota?.kontak ?? '',
    status: anggota?.status ?? 'Aktif',
});

// ------------------------------------------------ Akses login (opsional)
const akunLama = anggota ? akunAnggota(data, anggota) : null;
const relasiRusak = anggota ? !relasiAkunValid(data, anggota) : false;
const page = usePage();

const akses = reactive({
    buatAkses: false,
    username: '',
    kataSandi: '',
    konfirmasiKataSandi: '',
    resetKataSandi: false,
});
const tampilkanSandi = ref(false);


// ------------------------------------------------ Phase 8C: kredensial ke server
// Buat akses login & reset kata sandi adalah aksi EKSPLISIT yang disimpan ke
// MySQL (Laravel: Form Request + transaksi). Simpan data Anggota (nama, kontak,
// status) belum tersedia dan tidak membawa isian kata sandi.
const galatAkses = ref({});
const memprosesAkses = ref(false);
const pesanAkses = computed(() => page.props.flash?.status ?? null);

function kirimAkses(jenis) {
    const buat = jenis === 'buat';
    const isian = buat
        ? { buatAkses: true, username: akses.username, kataSandi: akses.kataSandi, konfirmasiKataSandi: akses.konfirmasiKataSandi }
        : { resetKataSandi: true, kataSandi: akses.kataSandi, konfirmasiKataSandi: akses.konfirmasiKataSandi };

    // Validasi awal di browser (umpan balik cepat); server tetap memvalidasi ulang.
    galatAkses.value = validasiAksesLogin(isian, data, { anggota });
    if (Object.keys(galatAkses.value).length > 0) return;

    const payload = { password: akses.kataSandi, password_confirmation: akses.konfirmasiKataSandi };
    if (buat) payload.username = akses.username.trim();

    memprosesAkses.value = true;
    router[buat ? 'post' : 'put'](rute(buat ? 'anggota.akses-login' : 'anggota.kata-sandi.reset', { kodeAnggota: anggota.kodeAnggota }), payload, {
        preserveScroll: true,
        // Berhasil → halaman dimuat ulang dari server (akun terbaru, isian kosong); gagal → isian lain dipertahankan.
        preserveState: 'errors',
        onError: (e) => {
            galatAkses.value = Object.fromEntries(
                Object.entries({ username: e.username, kataSandi: e.password, konfirmasiKataSandi: e.password_confirmation, akses: e.akses }).filter(([, v]) => v),
            );
        },
        onFinish: () => {
            memprosesAkses.value = false;
            kosongkanKataSandi();
        },
    });
}

// Kata sandi tidak disimpan lebih lama dari yang dibutuhkan.
function kosongkanKataSandi() {
    akses.kataSandi = '';
    akses.konfirmasiKataSandi = '';
    tampilkanSandi.value = false;
}

function aturBuatAkses(aktif) {
    galatAkses.value = {};
    akses.buatAkses = aktif;
    if (!aktif) {
        akses.username = '';
        kosongkanKataSandi();
    }
    perbaruiGalat();
}

function aturResetKataSandi(aktif) {
    galatAkses.value = {};
    akses.resetKataSandi = aktif;
    if (!aktif) kosongkanKataSandi();
    perbaruiGalat();
}

/** Status login tidak diatur terpisah: selalu mengikuti Status Anggota yang dipilih. */
const infoStatusLogin = computed(() =>
    form.status === 'Aktif'
        ? 'Akses login tersedia karena status anggota Aktif.'
        : 'Akses login dinonaktifkan otomatis karena status anggota Nonaktif.',
);

const galat = ref({});
const sudahDikirim = ref(false);
const konfirmasiNonaktif = ref(false);

const contohKode = kodeBerikutnya(data.anggota, 'kodeAnggota', 'AG');
const transaksi = computed(() => (anggota ? transaksiBerjalanAnggota(data, anggota.kodeAnggota) : null));
const totalRiwayat = computed(() => (anggota ? data.peminjaman.filter((p) => p.kodeAnggota === anggota.kodeAnggota).length : 0));

// Phase 9A: akses login opsional ikut dikirim saat TAMBAH; pada Ubah tetap lewat aksi eksplisit (Phase 8C).
const isianAksesTambah = computed(() =>
    mode === 'tambah' && akses.buatAkses
        ? { buatAkses: true, username: akses.username, kataSandi: akses.kataSandi, konfirmasiKataSandi: akses.konfirmasiKataSandi }
        : null,
);

function validasi() {
    galat.value = validasiAnggota(form, data, { mode });
    // Validasi awal di browser (umpan balik cepat); server memvalidasi ulang semuanya.
    galatAkses.value = isianAksesTambah.value ? validasiAksesLogin(isianAksesTambah.value, data) : {};

    return Object.keys(galat.value).length === 0 && Object.keys(galatAkses.value).length === 0;
}

// Setelah percobaan simpan pertama, pesan galat diperbarui saat pengguna mengetik.
function perbaruiGalat() {
    if (sudahDikirim.value) validasi();
}

async function simpan() {
    sudahDikirim.value = true;

    if (!validasi()) {
        await nextTick();
        document.querySelector('[aria-invalid="true"]')?.focus();

        return;
    }

    if (mode === 'ubah' && anggota.status === 'Aktif' && form.status === 'Nonaktif') {
        konfirmasiNonaktif.value = true;

        return;
    }

    terapkan();
}

const memprosesSimpan = ref(false);

/**
 * Phase 9A — simpan ke MySQL (POST /admin/anggota atau PUT /admin/anggota/{kode}).
 * Pesan sukses ditampilkan Data Anggota SETELAH server commit (redirect + flash).
 * Gagal → galat per field; isian non-sensitif dipertahankan, kata sandi dikosongkan.
 */
function terapkan() {
    konfirmasiNonaktif.value = false;
    if (memprosesSimpan.value) return; // cegah kirim ganda

    const payload = { nama: form.nama, kontak: form.kontak, status: form.status };
    if (mode === 'tambah') {
        payload.kodeAnggota = form.kodeAnggota;
        payload.buatAkses = Boolean(isianAksesTambah.value);
        if (isianAksesTambah.value) {
            payload.username = akses.username.trim();
            payload.password = akses.kataSandi;
            payload.password_confirmation = akses.konfirmasiKataSandi;
        }
    }

    memprosesSimpan.value = true;
    const kirim = mode === 'tambah' ? router.post : router.put;
    kirim(mode === 'tambah' ? rute('anggota.store') : rute('anggota.update', { kodeAnggota: kodeAsal }), payload, {
        preserveScroll: true,
        preserveState: 'errors',
        onError: (e) => {
            const { username, password, password_confirmation: konfirmasi, akses: galatAksesUmum, ...profil } = e;
            galat.value = profil;
            galatAkses.value = Object.fromEntries(
                Object.entries({ username, kataSandi: password, konfirmasiKataSandi: konfirmasi, akses: galatAksesUmum }).filter(([, v]) => v),
            );
        },
        onFinish: () => {
            memprosesSimpan.value = false;
            kosongkanKataSandi();
        },
    });
}

const judulKartu = mode === 'tambah' ? 'Data Anggota Baru' : 'Data Anggota';

const pesanNonaktif = computed(() => {
    if (!anggota) return '';
    const berjalan = transaksi.value.peminjamanAktif + transaksi.value.bookingBerjalan;
    const login = akunLama ? ' Akses loginnya ikut dinonaktifkan, tetapi akun dan kata sandinya tetap tersimpan.' : '';
    const dasar = `${anggota.nama} (${anggota.kodeAnggota}) tidak akan dapat membuat peminjaman maupun booking baru.${login} Riwayat transaksinya tetap tersimpan.`;

    return berjalan > 0
        ? `${dasar} Peminjaman Aktif dan booking yang sedang berjalan tidak dibatalkan.`
        : dasar;
});
</script>

<template>
    <AdminLayout>
        <Head :title="halaman?.judul" />

        <PageHeader
            :description="mode === 'ubah' && anggota ? `Perbarui data dan status keanggotaan ${anggota.nama}.` : undefined"
        />

        <section v-if="tidakDitemukan" class="sim-card">
            <EmptyState icon="bi-person-x" title="Anggota tidak ditemukan" :description="`Kode Anggota ${kodeAsal} tidak terdaftar.`">
                <template #actions>
                    <BaseButton variant="secondary" icon="bi-arrow-left" :href="rute('anggota.index')">Kembali ke Data Anggota</BaseButton>
                </template>
            </EmptyState>
        </section>

        <div v-else class="sim-form-layout">
            <form class="sim-card" novalidate @submit.prevent="simpan" @input="perbaruiGalat" @change="perbaruiGalat">
                <div class="sim-card-header">
                    <div>
                        <h2 class="sim-card-title">{{ judulKartu }}</h2>
                        <p class="sim-card-subtitle">Field bertanda <span class="sim-required">*</span> wajib diisi.</p>
                    </div>
                </div>

                <div class="sim-card-body">
                    <FormFeedback
                        v-if="sudahDikirim && Object.keys(galat).length"
                        class="mb-4"
                        tone="danger"
                        title="Data anggota belum valid"
                        :message="galat.umum ?? galat.akses ?? 'Periksa kembali isian yang ditandai, lalu simpan ulang.'"
                    />

                    <div class="sim-form-grid">
                        <BaseInput
                            v-model="form.kodeAnggota"
                            label="Kode Anggota"
                            :placeholder="mode === 'tambah' ? `Contoh: ${contohKode}` : null"
                            :readonly="mode === 'ubah'"
                            :hint="mode === 'ubah' ? 'Kode Anggota tidak dapat diubah karena dipakai pada riwayat transaksi.' : 'Harus unik; tidak dapat diubah setelah disimpan.'"
                            required
                            autocomplete="off"
                            :error="galat.kodeAnggota"
                        />
                        <BaseSelect
                            v-model="form.status"
                            label="Status Anggota"
                            :options="daftarStatus('anggota')"
                            required
                            :error="galat.status"
                        />
                        <div class="is-full">
                            <BaseInput
                                v-model="form.nama"
                                label="Nama"
                                placeholder="Nama lengkap anggota"
                                required
                                autocomplete="off"
                                :error="galat.nama"
                            />
                        </div>
                        <div class="is-full">
                            <BaseInput
                                v-model="form.kontak"
                                label="Kontak"
                                placeholder="Contoh: 0812-3456-7890"
                                required
                                autocomplete="off"
                                :error="galat.kontak"
                            />
                        </div>
                    </div>

                    <section class="sim-form-section" aria-labelledby="judul-akses-login">
                        <h3 id="judul-akses-login" class="sim-subheading">Akses Login Anggota</h3>
                        <p class="sim-field-hint mb-3">
                            <template v-if="mode === 'ubah'">
                                Opsional. Status login mengikuti Status Anggota. Akses login dan reset kata sandi disimpan ke
                                server lewat tombol masing-masing; kata sandi tidak pernah ditampilkan kembali.
                            </template>
                            <template v-else>
                                Opsional. Akses login dibuat bersama data anggota saat Simpan Anggota; anggota wajib mengganti
                                kata sandi ini saat pertama masuk. Status login mengikuti Status Anggota.
                            </template>
                        </p>

                        <FormFeedback v-if="pesanAkses && !Object.keys(galatAkses).length" class="mb-3" tone="success" title="Akses login diperbarui" :message="pesanAkses" />
                        <FormFeedback v-if="galatAkses.akses" class="mb-3" tone="danger" title="Akses login tidak diubah" :message="galatAkses.akses" />

                        <FormFeedback v-if="relasiRusak" tone="danger" title="Relasi akun tidak valid" message="Akun login yang terhubung dengan anggota ini tidak ditemukan atau tidak sesuai. Akses login tidak dapat diubah dari formulir ini." />

                        <!-- Anggota sudah memiliki akun login -->
                        <div v-else-if="akunLama" class="sim-form-grid">
                            <BaseInput
                                :model-value="akunLama.username"
                                label="Username"
                                readonly
                                hint="Username tidak dapat diubah."
                                autocomplete="off"
                            />
                            <ReadonlyField label="Status Login" :hint="infoStatusLogin">
                                <StatusBadge type="anggota" :status="form.status" />
                                <span class="sim-cell-sub">mengikuti Status Anggota</span>
                            </ReadonlyField>

                            <div v-if="!akses.resetKataSandi" class="is-full sim-akses-sandi">
                                <div>
                                    <span class="form-label d-block mb-1">Kata Sandi</span>
                                    <span class="sim-field-hint">
                                        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>
                                        Kata sandi tersimpan di server dan tidak dapat ditampilkan.
                                    </span>
                                </div>
                                <BaseButton variant="outline-primary" size="sm" icon="bi-key" @click="aturResetKataSandi(true)">
                                    Reset Kata Sandi
                                </BaseButton>
                            </div>

                            <div v-else id="panel-reset-kata-sandi" class="is-full sim-reset-panel" role="group" aria-labelledby="judul-reset-kata-sandi">
                                <div class="sim-reset-panel__head">
                                    <div>
                                        <h4 id="judul-reset-kata-sandi" class="sim-reset-panel__title">Reset Kata Sandi</h4>
                                        <p class="sim-field-hint mb-0">Kata sandi lama tidak ditampilkan. Kata sandi baru berlaku setelah Simpan Kata Sandi Baru; sesi login anggota sebelumnya diakhiri.</p>
                                    </div>
                                    <BaseButton
                                        variant="secondary"
                                        size="sm"
                                        icon="bi-x-lg"
                                        aria-label="Batalkan reset kata sandi"
                                        @click="aturResetKataSandi(false)"
                                    >
                                        Batal
                                    </BaseButton>
                                </div>
                                <div class="sim-form-grid">
                                    <BaseInput
                                        v-model="akses.kataSandi"
                                        label="Kata Sandi Baru"
                                        :type="tampilkanSandi ? 'text' : 'password'"
                                        placeholder="Minimal 6 karakter"
                                        required
                                        autocomplete="new-password"
                                        :error="galatAkses.kataSandi"
                                    >
                                    <template #action>
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-icon btn-sm"
                                            :aria-label="tampilkanSandi ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                            :aria-pressed="tampilkanSandi ? 'true' : 'false'"
                                            @click="tampilkanSandi = !tampilkanSandi"
                                        >
                                            <i :class="['bi', tampilkanSandi ? 'bi-eye-slash' : 'bi-eye']" aria-hidden="true"></i>
                                        </button>
                                    </template>
                                    </BaseInput>
                                    <BaseInput
                                        v-model="akses.konfirmasiKataSandi"
                                        label="Konfirmasi Kata Sandi Baru"
                                        :type="tampilkanSandi ? 'text' : 'password'"
                                        placeholder="Ulangi kata sandi baru"
                                        required
                                        autocomplete="new-password"
                                        :error="galatAkses.konfirmasiKataSandi"
                                    />
                                    <div class="is-full d-flex justify-content-end">
                                        <BaseButton icon="bi-key" :loading="memprosesAkses" @click="kirimAkses('reset')">Simpan Kata Sandi Baru</BaseButton>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Anggota baru / anggota tanpa akun login -->
                        <template v-else>
                            <div class="form-check form-switch sim-switch">
                                <input
                                    id="buat-akses-login"
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                    :checked="akses.buatAkses"
                                    aria-controls="isian-akses-login"
                                    :aria-expanded="akses.buatAkses ? 'true' : 'false'"
                                    @change="aturBuatAkses($event.target.checked)"
                                />
                                <label class="form-check-label" for="buat-akses-login">Buat akses login untuk anggota ini</label>
                            </div>

                            <div v-if="akses.buatAkses" id="isian-akses-login" class="sim-form-grid mt-3">
                                <BaseInput
                                    v-model="akses.username"
                                    label="Username"
                                    :placeholder="`Contoh: ${form.kodeAnggota.trim() || contohKode}`"
                                    hint="Harus unik; tanpa spasi."
                                    required
                                    autocomplete="off"
                                    autocapitalize="off"
                                    spellcheck="false"
                                    :error="galatAkses.username"
                                />
                                <ReadonlyField label="Status Login" :hint="infoStatusLogin">
                                    <StatusBadge type="anggota" :status="form.status" />
                                    <span class="sim-cell-sub">mengikuti Status Anggota</span>
                                </ReadonlyField>
                                <BaseInput
                                    v-model="akses.kataSandi"
                                    label="Kata Sandi"
                                    :type="tampilkanSandi ? 'text' : 'password'"
                                    placeholder="Minimal 6 karakter"
                                    required
                                    autocomplete="new-password"
                                    :error="galatAkses.kataSandi"
                                >
                                    <template #action>
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-icon btn-sm"
                                            :aria-label="tampilkanSandi ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                            :aria-pressed="tampilkanSandi ? 'true' : 'false'"
                                            @click="tampilkanSandi = !tampilkanSandi"
                                        >
                                            <i :class="['bi', tampilkanSandi ? 'bi-eye-slash' : 'bi-eye']" aria-hidden="true"></i>
                                        </button>
                                    </template>
                                </BaseInput>
                                <BaseInput
                                    v-model="akses.konfirmasiKataSandi"
                                    label="Konfirmasi Kata Sandi"
                                    :type="tampilkanSandi ? 'text' : 'password'"
                                    placeholder="Ulangi kata sandi"
                                    required
                                    autocomplete="new-password"
                                    :error="galatAkses.konfirmasiKataSandi"
                                />
                                <div v-if="mode === 'ubah'" class="is-full d-flex justify-content-end">
                                    <BaseButton icon="bi-person-check" :loading="memprosesAkses" @click="kirimAkses('buat')">Buat Akses Login</BaseButton>
                                </div>
                            </div>
                        </template>
                    </section>
                </div>

                <div class="sim-card-footer">
                    <div class="sim-form-actions w-100">
                        <BaseButton variant="secondary" :href="rute('anggota.index')">Batal</BaseButton>
                        <BaseButton type="submit" icon="bi-check2" :loading="memprosesSimpan">Simpan Anggota</BaseButton>
                    </div>
                </div>
            </form>

            <aside class="sim-card">
                <template v-if="mode === 'ubah' && anggota">
                    <div class="sim-card-header">
                        <h2 class="sim-card-title">Ringkasan Keanggotaan</h2>
                    </div>
                    <div class="sim-card-body">
                        <dl class="sim-detail-list">
                            <dt>Status saat ini</dt>
                            <dd><StatusBadge type="anggota" :status="anggota.status" /></dd>
                            <dt>Peminjaman Aktif</dt>
                            <dd class="tabular-nums">{{ transaksi.peminjamanAktif }}</dd>
                            <dt>Booking berjalan</dt>
                            <dd class="tabular-nums">{{ transaksi.bookingBerjalan }}</dd>
                            <dt>Total peminjaman</dt>
                            <dd class="tabular-nums">{{ totalRiwayat }}</dd>
                            <dt>Akses login</dt>
                            <dd>
                                <span v-if="akunLama">Sudah dibuat</span>
                                <span v-else class="text-secondary">Belum dibuat</span>
                            </dd>
                        </dl>
                        <FormFeedback
                            v-if="transaksi.peminjamanAktif + transaksi.bookingBerjalan > 0"
                            class="mt-4"
                            tone="info"
                            message="Menonaktifkan anggota tidak membatalkan peminjaman atau booking yang sedang berjalan. Anggota Nonaktif tidak dapat membuat peminjaman atau booking baru."
                        />
                    </div>
                </template>
                <template v-else>
                    <div class="sim-card-header">
                        <h2 class="sim-card-title">Ketentuan Pengisian</h2>
                    </div>
                    <div class="sim-card-body">
                        <ul class="sim-aside-list">
                            <li><i class="bi bi-check2-circle" aria-hidden="true"></i>Seluruh field bertanda * wajib diisi.</li>
                            <li><i class="bi bi-check2-circle" aria-hidden="true"></i>Kode Anggota harus unik dan tidak dapat diubah setelah disimpan.</li>
                            <li><i class="bi bi-check2-circle" aria-hidden="true"></i>Hanya anggota berstatus Aktif yang dapat meminjam dan mengajukan booking.</li>
                            <li><i class="bi bi-check2-circle" aria-hidden="true"></i>Akses login bersifat opsional dan dapat dibuat kemudian melalui Ubah Anggota.</li>
                        </ul>
                    </div>
                </template>
            </aside>
        </div>

        <ConfirmationModal
            v-model:show="konfirmasiNonaktif"
            title="Nonaktifkan anggota?"
            :message="pesanNonaktif"
            tone="warning"
            icon="bi-person-slash"
            confirm-label="Nonaktifkan"
            @confirm="terapkan"
        />
    </AdminLayout>
</template>
