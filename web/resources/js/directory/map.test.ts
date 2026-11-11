// Carte schématique du Bénin de l'annuaire (lot annuaire-membre, P8-12) : projection, contour fermé dans la vue,
// pastilles des seuls départements pourvus et connus (plus le département choisi, même à 0), et pastilles assez écartées pour rester des
// cibles distinctes de 24 px (WCAG 2.5.8) — y compris le Sud, resserré dans le prototype.
import { describe, expect, it } from 'vitest';
import { OUTLINE, PINS, VIEW, mapPins, project } from './map';
import type { MapZone } from './types';

const SLUGS = ['alibori', 'atacora', 'atlantique', 'borgou', 'collines', 'couffo', 'donga', 'littoral', 'mono', 'oueme', 'plateau', 'zou'];

describe('carte schématique du Bénin', () => {
    it('projette dans la vue et ferme le contour', () => {
        expect(project(0.75, 12.45)).toEqual([0, 0]);
        expect(project(2.42, 6.37)).toEqual([83.5, 279.7]);
        expect(OUTLINE.startsWith('M')).toBe(true);
        expect(OUTLINE.endsWith('Z')).toBe(true);
        const numbers = OUTLINE.replace(/[MLZ]/g, ' ').trim().split(/\s+/).map(Number);
        for (let i = 0; i < numbers.length; i += 2) {
            expect(numbers[i]).toBeGreaterThanOrEqual(0);
            expect(numbers[i]).toBeLessThanOrEqual(VIEW.width);
            expect(numbers[i + 1]).toBeGreaterThanOrEqual(0);
            expect(numbers[i + 1]).toBeLessThanOrEqual(VIEW.height);
        }
    });

    it('place les douze départements du référentiel, sans chevauchement', () => {
        expect(Object.keys(PINS).sort()).toEqual(SLUGS);
        const points = Object.values(PINS);
        for (let i = 0; i < points.length; i += 1) {
            const [x, y] = points[i];
            expect(x - 12).toBeGreaterThanOrEqual(0);
            expect(x + 12).toBeLessThanOrEqual(VIEW.width);
            expect(y - 12).toBeGreaterThanOrEqual(0);
            expect(y + 12).toBeLessThanOrEqual(VIEW.height);
            for (let j = i + 1; j < points.length; j += 1) {
                expect(Math.hypot(points[j][0] - x, points[j][1] - y)).toBeGreaterThanOrEqual(26);
            }
        }
    });

    it('ne pose une pastille que sur un département connu et pourvu, et marque le département choisi', () => {
        const zones: MapZone[] = [
            { id: 4, name: 'Zou', slug: 'zou', count: 3 },
            { id: 5, name: 'Borgou', slug: 'borgou', count: 0 },
            { id: 8, name: 'Littoral', slug: 'littoral', count: 12 },
            { id: 99, name: 'Inconnu', slug: 'inconnu', count: 2 },
        ];

        expect(mapPins(zones, 8)).toEqual([
            { id: 4, name: 'Zou', count: 3, left: '47.5%', top: '76%', selected: false },
            { id: 8, name: 'Littoral', count: 12, left: '52.5%', top: '94%', selected: true },
        ]);
        expect(mapPins([], null)).toEqual([]);
    });

    it('garde la pastille du département choisi même sans fiche (filtre sans résultat)', () => {
        const zones: MapZone[] = [
            { id: 4, name: 'Zou', slug: 'zou', count: 2 },
            { id: 5, name: 'Borgou', slug: 'borgou', count: 0 },
            { id: 8, name: 'Littoral', slug: 'littoral', count: 0 },
        ];

        expect(mapPins(zones, 5).map((pin) => [pin.name, pin.count, pin.selected])).toEqual([['Zou', 2, false], ['Borgou', 0, true]]);
    });
});
