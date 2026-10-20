// Mise en forme des écrans de l'espace pair (lot espace-pair) : date `Y-m-d` de l'API en `jj/mm/aaaa` (prototype
// admin/suivi.php : date('d/m/Y')), sans fuseau (la date reste celle du jour calendaire du Bénin, RG-07).
export function dateFr(value: string | null | undefined): string {
    if (!value) return '';
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
    return match ? `${match[3]}/${match[2]}/${match[1]}` : value;
}
