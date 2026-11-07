/*
 * Service worker de la app del mensajero (alcance: /mensajero).
 * - Nunca guarda páginas ni datos de pedidos (son privados): siempre van a la red.
 * - Guarda los archivos del build (tienen nombre con hash, no cambian) para abrir rápido.
 * - Sin señal, al abrir la app muestra una página de "sin conexión" en vez del error del navegador.
 */
const VERSION = 'isa-mensajero-v1';
const SHELL = ['/mensajero-app/sin-conexion.html', '/mensajero-app/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Abrir la app (navegación completa, no las visitas internas de Inertia).
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match('/mensajero-app/sin-conexion.html')));
        return;
    }

    // Archivos del build: primero la copia guardada.
    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(VERSION).then((c) => c.put(req, copy));
                }
                return res;
            })),
        );
    }
});
