import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

/**
 * For hiding UI only — the server checks every route itself.
 */
export function usePermissions() {
    const page = usePage<SharedData>();

    const can = (permission?: string): boolean => permission === undefined || page.props.auth.permissions.includes(permission);

    return { can };
}
