import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

// Vite configuration untuk aplikasi Laravel 12 + Vue 3 + Inertia.js.
// - laravel-vite-plugin: mem-build asset Laravel (manifest + hot) dan menyuntikkan ke Blade.
// - @vitejs/plugin-vue:   mengompilasi komponen Vue SFC (.vue) + <script setup>.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
