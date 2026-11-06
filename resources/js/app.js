// Aset visual dibundel lokal oleh Vite (tanpa CDN).
import '@fontsource-variable/inter';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';

// Inertia v3: plugin @inertiajs/vite membuat resolver halaman otomatis
// dari folder ./Pages dan me-mount aplikasi Vue secara otomatis.
createInertiaApp({
    title: (title) => (title ? `${title} — SIM Perpustakaan` : 'SIM Perpustakaan'),
    progress: {
        color: '#1d5fbf',
        showSpinner: false,
    },
});
