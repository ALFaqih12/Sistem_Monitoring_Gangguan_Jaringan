// Bootstrap aplikasi Inertia + Vue 3 (Composition API).
// Entry-point JS utama yang diproses Vite.
//
// createInertiaApp:
//   - resolve(): peta nama halaman (mis. "Dashboard/Index") ke file Vue di
//     resources/js/Pages/. Default layout = AppLayout.vue (mirip @extends('layouts.app') Blade).
//   - setup(): daftarkan plugin Inertia + global component Link & Head, lalu mount ke #app.
//   - progress: progress-bar saat navigasi SPA.

import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp, Link, Head } from '@inertiajs/vue3';

// Layout bersama: sidebar navigasi + slot konten. Diterapkan otomatis ke halaman
// yang belum mendefinisikan layout-nya. (Migrasi dari Blade layouts/app.blade.php)
import AppLayout from './Pages/Layouts/AppLayout.vue';

createInertiaApp({
    // Auto-resolve: "Dashboard/Index" -> resources/js/Pages/Dashboard/Index.vue
    resolve: async (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const page = await pages[`./Pages/${name}.vue`]();

        // Jika komponen halaman belum menentukan layout, pakai AppLayout default.
        // Ini meniru perilaku @extends('layouts.app') pada Blade sebelumnya.
        if (page.default.layout === undefined) {
            page.default.layout = AppLayout;
        }

        return page;
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        // Plugin Inertia + register global component Link & Head
        app.use(plugin);
        app.component('Link', Link);
        app.component('Head', Head);
        return app.mount(el);
    },
    progress: {
        // Warna progress bar navigasi Inertia, konsisten dengan tema aplikasi.
        color: '#2ecc71',
        showProgressBar: true,
    },
});