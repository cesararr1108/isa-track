import { createMap, L } from '@/shared/map/createMap';
import type { LatLng } from '@/types';

/*
 * Mapa de la página pública de rastreo (/rastreo/{codigo}).
 * Lee los datos de un bloque JSON que deja el servidor; no hay scripts en línea.
 */
interface TrackingMapData {
    here: LatLng | null;
    dest: LatLng | null;
    style: string;
    maptilerKey: string | null;
}

const dataEl = document.getElementById('tracking-map-data');
const el = document.getElementById('map');

if (dataEl && el) {
    const data = JSON.parse(dataEl.textContent ?? '{}') as TrackingMapData;
    const map = createMap(el, { style: data.style, maptilerKey: data.maptilerKey });
    map.removeControl(map.zoomControl);
    const points: LatLng[] = [];
    if (data.dest) {
        L.circle(data.dest, { radius: 600, color: '#0B1F3A', weight: 2, fillOpacity: 0.08 }).addTo(map).bindTooltip('Tu barrio');
        points.push(data.dest);
    }
    if (data.here) {
        L.circleMarker(data.here, { radius: 9, color: '#fff', weight: 3, fillColor: '#00C896', fillOpacity: 1 })
            .addTo(map)
            .bindTooltip('Mensajero (aprox.)', { permanent: true, direction: 'top' });
        points.push(data.here);
    }
    if (points.length === 1 && points[0]) map.setView(points[0], 15);
    else if (points.length > 1) map.fitBounds(points, { padding: [40, 40] });
    // Mientras el pedido va en camino, la página se actualiza cada minuto.
    setTimeout(() => location.reload(), 60_000);
}
