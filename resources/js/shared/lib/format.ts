const TZ = 'America/Bogota';

export const money = (n: number | null | undefined): string =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n ?? 0);

export const longDay = (ymd: string): string =>
    new Intl.DateTimeFormat('es-CO', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(`${ymd}T12:00:00Z`));

export const percent = (v: number | null | undefined): string => (v == null ? '—' : `${v.toFixed(1).replace('.', ',')} %`);

export const initials = (name: string): string =>
    name.trim().split(/\s+/).slice(0, 2).map((w) => w[0] ?? '').join('').toUpperCase() || '?';

export const timeZone = TZ;
