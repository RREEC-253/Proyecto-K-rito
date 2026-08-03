import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0', // Escucha en todas las interfaces dentro del contenedor
        port: 5173,
        strictPort: true,
        cors: true, // Habilita las cabeceras CORS
        hmr: {
            host: 'localhost', // Fuerza el uso de IPv4 (localhost / 127.0.0.1) evitando [::1]
        },
        watch: {
            usePolling: true, // Necesario en Windows / Docker Desktop para detectar cambios en archivos
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
