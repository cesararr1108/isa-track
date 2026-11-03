import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

// Todo el código y las fuentes se empaquetan en el propio servidor:
// no se cargan scripts de CDNs externos (la CSP solo permite 'self').
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts', 'resources/js/tracking.ts'],
            refresh: true,
        }),
        vue({
            template: { transformAssetUrls: { base: null, includeAbsolute: false } },
        }),
    ],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    build: {
        sourcemap: false,
        chunkSizeWarningLimit: 1200,
        rollupOptions: {
            output: {
                // El mapa (MapLibre) es pesado: va en su propio archivo y solo se descarga en las pantallas con mapa.
                manualChunks: (id) => (id.includes('maplibre-gl') ? 'maplibre' : id.includes('leaflet') ? 'leaflet' : undefined),
            },
        },
    },
    server: {
        watch: { ignored: ['**/storage/framework/views/**'] },
    },
});
