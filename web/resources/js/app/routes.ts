// Routes nommées Laravel dans l'espace Vue (P6-02) : la fonction route() vient de la directive @routes('member') de Ziggy
// (app.blade.php, groupe « member » de config/ziggy.php), écrite dans la page : rien de plus dans le paquet app.ts.
// Repli `fallback` (chemin) quand Ziggy est absent (tests) ou que le nom n'est pas dans le groupe.

export function route(name: string, params?: Record<string, unknown> | string | number, fallback?: string): string {
    const ziggy = typeof window !== 'undefined' ? window.route : undefined;
    if (typeof ziggy === 'function') {
        try {
            return ziggy(name, params, false);
        } catch {
            // Nom absent du groupe « member » : repli ci-dessous.
        }
    }
    if (fallback !== undefined) return fallback;
    throw new Error(`Route inconnue : ${name}`);
}
