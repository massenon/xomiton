// Carte schématique du Bénin par département (lot annuaire-membre, P8-12 ; prototype annuaire.php l.38-41, l.73-78) :
// SVG léger dessiné ici, sans Leaflet ni tuiles côté membre (Q15 b, poids réseau). Contour simplifié projeté en
// coordonnées de la vue (160 × 300 : x = (lon − 0,75) × 50, y = (12,45 − lat) × 46, proche des proportions réelles) ;
// pastilles par département aux positions du prototype ($pos), celles du Sud écartées pour rester distinctes et
// cliquables (cible de 24 px, WCAG 2.5.8 ; la liste « Département » des filtres reste l'équivalent).
import type { MapZone } from './types';

export const VIEW = { width: 160, height: 300 } as const;

/** Projection longitude / latitude → coordonnées de la vue. */
export function project(lon: number, lat: number): [number, number] {
    return [Math.round((lon - 0.75) * 50 * 10) / 10, Math.round((12.45 - lat) * 46 * 10) / 10];
}

/** Frontières simplifiées (longitude, latitude), du littoral ouest dans le sens des aiguilles d'une montre. */
const BORDER: [number, number][] = [
    [1.63, 6.22], [2.72, 6.37], [2.72, 6.95], [2.78, 7.6], [2.73, 8.1], [2.7, 8.55], [2.77, 9.05], [3.1, 9.1],
    [3.15, 9.5], [3.35, 9.8], [3.55, 10.25], [3.7, 10.8], [3.58, 11.1], [3.65, 11.6], [3.4, 11.9], [3.0, 12.25],
    [2.85, 12.4], [2.6, 12.2], [2.4, 11.9], [2.0, 11.4], [1.6, 11.4], [1.4, 11.45], [1.1, 11.2], [0.92, 11.0],
    [0.78, 10.55], [0.85, 10.25], [1.35, 10.0], [1.42, 9.45], [1.6, 9.05], [1.62, 8.4], [1.65, 7.5], [1.6, 7.0],
];

/** Tracé SVG du contour (attribut `d`). */
export const OUTLINE = `M${BORDER.map(([lon, lat]) => project(lon, lat).join(' ')).join(' L')} Z`;

/** Position schématique de chaque département (slug du référentiel `zones`), en coordonnées de la vue. */
export const PINS: Record<string, [number, number]> = {
    alibori: [102.5, 39],
    atacora: [42.5, 90],
    borgou: [102.5, 131],
    donga: [47.5, 126.5],
    collines: [77.5, 195.5],
    zou: [76, 228],
    couffo: [46, 230],
    plateau: [102, 234],
    mono: [36, 256],
    atlantique: [64, 256],
    oueme: [104, 262],
    littoral: [84, 282],
};

export interface Pin {
    id: number;
    name: string;
    count: number;
    left: string;
    top: string;
    selected: boolean;
}

const percent = (value: number, total: number): string => `${Math.round((value / total) * 1000) / 10}%`;

/**
 * Pastilles à afficher : départements connus de la carte ayant au moins une fiche (comme le prototype, qui ne pose
 * une pastille que sur un département pourvu), plus le département choisi même à 0 (la carte garde la sélection après
 * un filtre sans résultat), dans l'ordre du référentiel ; position en pourcentage de la vue.
 */
export function mapPins(zones: MapZone[], selected: number | null): Pin[] {
    return zones
        .filter((zone) => (zone.count > 0 || zone.id === selected) && zone.slug in PINS)
        .map((zone) => {
            const [x, y] = PINS[zone.slug];
            return { id: zone.id, name: zone.name, count: zone.count, left: percent(x, VIEW.width), top: percent(y, VIEW.height), selected: zone.id === selected };
        });
}
