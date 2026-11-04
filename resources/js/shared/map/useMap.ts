import { onBeforeUnmount, onMounted, shallowRef, type Ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { Map as LeafletMap, LayerGroup } from 'leaflet';
import type { SharedProps } from '@/types';

type MapLib = typeof import('./createMap');

/**
 * Crea un mapa en el elemento indicado y lo destruye al salir de la página.
 * El código del mapa (Leaflet + MapLibre) se descarga solo cuando se usa.
 * `draw` se llama cuando el mapa está listo; vuelve a llamarlo cuando cambien los datos.
 */
export function useMap(el: Ref<HTMLElement | null>, draw: (ctx: { map: LeafletMap; layers: LayerGroup; lib: MapLib }) => void) {
    const page = usePage<SharedProps>();
    const map = shallowRef<LeafletMap | null>(null);
    let layers: LayerGroup | null = null;
    let lib: MapLib | null = null;

    const redraw = () => {
        if (!map.value || !layers || !lib) return;
        layers.clearLayers();
        draw({ map: map.value, layers, lib });
    };

    onMounted(async () => {
        lib = await import('./createMap');
        if (!el.value) return;
        map.value = lib.createMap(el.value, { style: page.props.map.style, maptilerKey: page.props.map.maptilerKey });
        layers = lib.L.layerGroup().addTo(map.value);
        redraw();
    });
    onBeforeUnmount(() => map.value?.remove());

    return { map, redraw };
}
