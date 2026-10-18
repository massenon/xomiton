// @vitest-environment jsdom
// vue-i18n de l'espace Vue (P6-05) : clés à plat de resources/js/lang/fr.json, placeholders, pluriels alignés sur
// Laravel, chargement de la locale avec repli.
import { describe, expect, it } from 'vitest';
import { frenchPlural, i18n, loadLocale, t } from './i18n';

describe('i18n', () => {
    it('charge le catalogue de la locale (repli fr pour une locale sans fichier)', async () => {
        expect(await loadLocale('yo', 'fr')).toBe('fr');
        expect(i18n.global.locale.value).toBe('fr');
        expect(t('common.close')).toBe('Fermer');
    });

    it('clés à plat (points et espaces dans la clé), placeholders {nom}', async () => {
        await loadLocale('fr');
        expect(t('The given data was invalid.')).toBe('Les données envoyées sont invalides.');
        expect(t('components.avatar.alt', { name: 'Awa' })).toBe('Avatar de Awa');
        expect(t('clé.inexistante')).toBe('clé.inexistante');
    });

    it('pluriels : deux formes (0 et 1 au singulier), trois formes', async () => {
        await loadLocale('fr');
        expect(t('components.error_summary.title', { count: 0 }, 0)).toBe('0 erreur à corriger');
        expect(t('components.error_summary.title', { count: 1 }, 1)).toBe('1 erreur à corriger');
        expect(t('components.error_summary.title', { count: 3 }, 3)).toBe('3 erreurs à corriger');
        expect(t('common.count_results', { count: 0 }, 0)).toBe('Aucun résultat');
        expect(t('common.count_results', { count: 5 }, 5)).toBe('5 résultats');
        expect(frenchPlural(1, 2)).toBe(0);
        expect(frenchPlural(2, 2)).toBe(1);
        expect(frenchPlural(7, 3)).toBe(2);
    });
});
