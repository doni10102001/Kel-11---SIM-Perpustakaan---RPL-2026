<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const pesan = ref('');
</script>

<template>
    <Head title="Hello Echo" />

    <main class="container min-vh-100 d-flex align-items-center justify-content-center py-5">
        <div class="card echo-card shadow-sm w-100">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 fw-bold mb-1">Hello Echo</h1>
                <p class="text-secondary mb-4">Laravel + Inertia + Vue 3 + Bootstrap 5.3</p>

                <div class="mb-4">
                    <label for="pesan" class="form-label fw-semibold">Pesan</label>
                    <input
                        id="pesan"
                        v-model="pesan"
                        type="text"
                        class="form-control"
                        placeholder="Ketik pesan di sini"
                        autocomplete="off"
                    />
                </div>

                <div class="echo-output rounded p-3" aria-live="polite" data-testid="echo-output">
                    <span class="fw-semibold">Echo:</span> {{ pesan }}
                </div>

                <p class="small text-secondary mt-4 mb-0">
                    <span class="badge text-bg-light border">Static UI — tanpa database</span>
                </p>
            </div>
        </div>
    </main>
</template>
