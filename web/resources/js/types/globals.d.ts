// Types globaux de l'espace Vue (P6-02, P6-05).
import type { SharedProps } from '@/app/types';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedProps;
        errorValueType: string;
    }
}

/** Fonction route() écrite dans la page par la directive @routes('member') de Ziggy (app.blade.php). */
export type ZiggyRoute = (name: string, params?: Record<string, unknown> | string | number, absolute?: boolean) => string;

declare global {
    interface Window {
        route?: ZiggyRoute;
        XM?: {
            toast?: (message: string, type?: string) => unknown;
            [key: string]: unknown;
        };
    }
}
