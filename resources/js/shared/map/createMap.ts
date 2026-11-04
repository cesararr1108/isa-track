import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import * as maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import '@maplibre/maplibre-gl-leaflet';
import type { LatLng } from '@/types';

/*
 * Mapa base del panel (Leaflet).
 * Calles: estilo vectorial (OpenFreeMap "Liberty" por defecto, o MapTiler con llave).
 * Si no carga en 8 s se pasa a OpenStreetMap y luego a Esri, todos sin llave.
 * Las librerías van empaquetadas en el servidor (sin CDNs externos).
 */
export const MEDELLIN: LatLng = [6.222, -75.578];
const OSM = '© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';

export interface MapOptions {
    center?: LatLng;
    zoom?: number;
    style?: string;
    maptilerKey?: string | null;
}

function styleUrl(style: string, key?: string | null): string | null {
    if (style === 'maptiler') return key ? `https://api.maptiler.com/maps/streets-v2/style.json?key=${encodeURIComponent(key)}` : null;
    const ofm: Record<string, string> = { urbano: 'liberty', claro: 'positron', brillante: 'bright' };
    return ofm[style] ? `https://tiles.openfreemap.org/styles/${ofm[style]}` : null;
}

const RASTERS = [
    { url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', attribution: OSM, maxZoom: 19 },
    { url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', attribution: 'Mapa © Esri, ' + OSM, maxZoom: 19 },
];

function addRaster(map: L.Map, i = 0): void {
    const src = RASTERS[i];
    if (!src) {
        const note = document.createElement('div');
        note.className = 'map-off';
        note.textContent = 'No se pudieron cargar las calles. Las zonas y marcadores sí se muestran.';
        map.getContainer().appendChild(note);
        return;
    }
    let loaded = 0;
    let failed = 0;
    const layer = L.tileLayer(src.url, { maxZoom: src.maxZoom, attribution: src.attribution }).addTo(map);
    layer.on('tileload', () => loaded++);
    layer.on('tileerror', () => {
        if (++failed >= 4 && loaded === 0) {
            map.removeLayer(layer);
            addRaster(map, i + 1);
        }
    });
}

function addStreets(map: L.Map, style: string, key?: string | null): void {
    const url = styleUrl(style, key);
    if (!url) return addRaster(map);
    let settled = false;
    const options = { style: url, attribution: '<a href="https://openfreemap.org" target="_blank" rel="noopener">OpenFreeMap</a> ' + OSM } as unknown as L.LeafletMaplibreGLOptions;
    const gl = L.maplibreGL(options).addTo(map);
    const fallback = () => {
        if (settled || !map.getContainer().isConnected) return;
        settled = true;
        map.removeLayer(gl);
        addRaster(map);
    };
    const lib = gl.getMaplibreMap();
    lib.once('load', () => (settled = true));
    lib.on('error', () => { if (!lib.loaded()) fallback(); });
    setTimeout(fallback, 8000);
}

export function createMap(el: HTMLElement, opts: MapOptions = {}): L.Map {
    void maplibregl; // la librería debe quedar cargada para el plugin.
    const map = L.map(el, { scrollWheelZoom: false }).setView(opts.center ?? MEDELLIN, opts.zoom ?? 12);
    addStreets(map, opts.style ?? 'urbano', opts.maptilerKey);
    return map;
}

export const dotIcon = (color: string, size = 18): L.DivIcon =>
    L.divIcon({ className: 'pin-icon', html: `<span class="mk" style="--c:${color}"></span>`, iconSize: [size, size], iconAnchor: [size / 2, size / 2] });

export { L };

/** Marcador numerado (paradas de una ruta). */
export const numberIcon = (n: number, color: string, dark = false): L.DivIcon =>
    L.divIcon({
        className: 'pin-icon',
        html: `<span class="mk num-mk" style="--c:${color};color:${dark ? '#0B1F3A' : '#fff'}">${n}</span>`,
        iconSize: [26, 26],
        iconAnchor: [13, 13],
    });

/** Mensajero en el mapa (iniciales sobre azul con borde menta). */
export const riderIcon = (initials: string): L.DivIcon =>
    L.divIcon({ className: 'pin-icon', html: `<span class="mk rider-mk">${initials}</span>`, iconSize: [30, 30], iconAnchor: [15, 15] });

/** Bodega / punto de partida. */
export const depotIcon = (): L.DivIcon =>
    L.divIcon({ className: 'pin-icon', html: '<span class="mk depot-mk">B</span>', iconSize: [24, 24], iconAnchor: [12, 12] });

/** Escapa texto antes de ponerlo en un popup o tooltip (evita XSS con nombres o direcciones). */
export const escapeHtml = (s: string | null | undefined): string =>
    String(s ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch] ?? ch);
