import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

/**
 * Permisos del usuario para mostrar u ocultar botones. Es solo comodidad visual:
 * el servidor vuelve a validar cada permiso en cada petición.
 */
export function usePermissions() {
    const page = usePage<SharedProps>();
    const list = computed(() => page.props.auth.user?.permissions ?? []);

    return { can: (permission: string): boolean => list.value.includes(permission) };
}
