import { readonly, ref } from 'vue';
import { postJson } from './http';

/**
 * GPS del mensajero. Todo el acceso a la ubicación pasa por este archivo.
 *
 * Hoy (PWA): usa el GPS del navegador. Solo funciona con la app abierta y en pantalla;
 * por eso, mientras comparte, se pide al celular que no apague la pantalla (Wake Lock).
 *
 * Capacitor (después): aquí se agrega
 *   if (Capacitor.isNativePlatform()) { BackgroundGeolocation.addWatcher(...) }
 * y el GPS sigue enviando con la pantalla bloqueada. El resto de la app no cambia.
 */
export interface Position {
    latitude: number;
    longitude: number;
    accuracy: number | null;
}

export type SharingState = 'off' | 'starting' | 'on' | 'denied' | 'unavailable';

/** Cada cuánto se envía la ubicación al servidor (el servidor acepta hasta 30 por minuto). */
const SEND_EVERY_MS = 30_000;

const state = ref<SharingState>('off');
const lastSentAt = ref<Date | null>(null);
let watchId: number | null = null;
let lastSent = 0;
let latest: Position | null = null;
let wakeLock: { release: () => Promise<void> } | null = null;

const supported = (): boolean => typeof navigator !== 'undefined' && 'geolocation' in navigator;

const toPosition = (p: GeolocationPosition): Position => ({
    latitude: p.coords.latitude,
    longitude: p.coords.longitude,
    accuracy: Number.isFinite(p.coords.accuracy) ? p.coords.accuracy : null,
});

/** Ubicación de este momento (para adjuntarla a un cambio de estado o a la entrega). Nunca falla: si no hay, devuelve null. */
export function currentPosition(timeoutMs = 6000): Promise<Position | null> {
    if (!supported()) return Promise.resolve(null);
    if (latest && state.value === 'on') return Promise.resolve(latest);

    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (p) => resolve((latest = toPosition(p))),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: timeoutMs, maximumAge: 60_000 },
        );
    });
}

async function send(pos: Position, force = false): Promise<void> {
    const now = Date.now();
    if (!force && now - lastSent < SEND_EVERY_MS) return;
    lastSent = now;
    try {
        const res = await postJson('/mensajero/ubicacion', { latitude: pos.latitude, longitude: pos.longitude });
        if (res.ok) lastSentAt.value = new Date();
    } catch {
        // Sin señal: se intenta de nuevo con la próxima lectura.
    }
}

async function keepScreenOn(): Promise<void> {
    const nav = navigator as Navigator & { wakeLock?: { request: (t: 'screen') => Promise<{ release: () => Promise<void> }> } };
    if (!nav.wakeLock || wakeLock || document.visibilityState !== 'visible') return;
    try {
        wakeLock = await nav.wakeLock.request('screen');
    } catch {
        wakeLock = null;
    }
}

function onVisibility(): void {
    // El navegador suelta el bloqueo de pantalla al cambiar de app; al volver se pide de nuevo.
    if (document.visibilityState === 'visible' && state.value === 'on') {
        wakeLock = null;
        void keepScreenOn();
        if (latest) void send(latest, true);
    }
}

/** Empieza a compartir la ubicación (ruta iniciada o pedido en camino). Llamarlo varias veces no duplica nada. */
export function startSharing(): void {
    if (watchId !== null) return;
    if (!supported()) {
        state.value = 'unavailable';
        return;
    }
    state.value = 'starting';
    watchId = navigator.geolocation.watchPosition(
        (p) => {
            latest = toPosition(p);
            const first = state.value !== 'on';
            state.value = 'on';
            void send(latest, first);
        },
        (err) => {
            state.value = err.code === err.PERMISSION_DENIED ? 'denied' : 'unavailable';
            if (err.code === err.PERMISSION_DENIED) stopSharing(true);
        },
        { enableHighAccuracy: true, maximumAge: 15_000, timeout: 30_000 },
    );
    void keepScreenOn();
    document.addEventListener('visibilitychange', onVisibility);
}

export function stopSharing(keepState = false): void {
    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    watchId = null;
    document.removeEventListener('visibilitychange', onVisibility);
    void wakeLock?.release().catch(() => undefined);
    wakeLock = null;
    if (!keepState) state.value = 'off';
}

export const sharing = { state: readonly(state), lastSentAt: readonly(lastSentAt) };
