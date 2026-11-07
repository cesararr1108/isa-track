/**
 * Peticiones JSON de la app del mensajero que no cambian de página (p. ej. el GPS).
 * Usa la misma sesión que el panel: cookie httpOnly + token CSRF (cookie XSRF-TOKEN).
 *
 * Capacitor: si la app empaquetada carga el sitio con `server.url`, esto funciona igual
 * (mismo dominio, misma cookie). Si algún día se empaquetan los archivos dentro del APK,
 * aquí se cambia a token de Sanctum guardado en el almacenamiento seguro del celular.
 */
function xsrfToken(): string {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return m?.[1] ? decodeURIComponent(m[1]) : '';
}

export async function postJson(url: string, body: unknown): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });
}
