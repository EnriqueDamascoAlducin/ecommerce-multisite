import { usePage } from '@inertiajs/react';

export type UsePermissionsReturn = {
    permissions: string[];
    roles: string[];
    can: (permission: string) => boolean;
    canAny: (permissions: string[]) => boolean;
    hasRole: (role: string) => boolean;
};

/**
 * Lee los roles y permisos del usuario autenticado desde las props compartidas
 * por Inertia (ver HandleInertiaRequests::share). Úsalo para mostrar/ocultar UI;
 * la autorización real se aplica en el backend con el middleware de permisos.
 */
export function usePermissions(): UsePermissionsReturn {
    const { auth } = usePage().props;
    const permissions = auth?.permissions ?? [];
    const roles = auth?.roles ?? [];

    const can = (permission: string): boolean => permissions.includes(permission);

    return {
        permissions,
        roles,
        can,
        canAny: (required: string[]): boolean => required.some(can),
        hasRole: (role: string): boolean => roles.includes(role),
    };
}
