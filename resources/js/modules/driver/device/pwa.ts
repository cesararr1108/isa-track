import { readonly, ref } from 'vue';

/**
 * Instalación de la app en el celular (PWA): registra el service worker y guarda
 * el aviso de "instalar" del navegador para mostrar nuestro propio botón.
 * El service worker necesita HTTPS (o localhost); sin eso la app igual funciona en el navegador.
 */
interface InstallPrompt extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const canInstall = ref(false);
let deferred: InstallPrompt | null = null;
let started = false;

export function setupPwa(): void {
    if (started || typeof window === 'undefined') return;
    started = true;

    if ('serviceWorker' in navigator && window.isSecureContext) {
        navigator.serviceWorker.register('/sw-mensajero.js', { scope: '/mensajero' }).catch(() => undefined);
    }
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferred = e as InstallPrompt;
        canInstall.value = true;
    });
    window.addEventListener('appinstalled', () => {
        deferred = null;
        canInstall.value = false;
    });
}

export async function install(): Promise<void> {
    if (!deferred) return;
    await deferred.prompt();
    await deferred.userChoice.catch(() => undefined);
    deferred = null;
    canInstall.value = false;
}

/** iPhone no muestra el aviso automático: hay que explicar "Compartir → Agregar a inicio". */
export const isIos = (): boolean => /iphone|ipad|ipod/i.test(navigator.userAgent);
export const isStandalone = (): boolean =>
    window.matchMedia('(display-mode: standalone)').matches || (navigator as Navigator & { standalone?: boolean }).standalone === true;

export const pwa = { canInstall: readonly(canInstall) };
