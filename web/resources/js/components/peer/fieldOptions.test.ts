// @vitest-environment jsdom
// <XmField> : options en tableau [{ value, label }] (lot espace-pair, P9-04) — un objet JS trie ses clés entières avant
// les autres, donc « Choisir… » (clé vide) passait après les identifiants ; le tableau garde l'ordre donné.
import { describe, expect, it } from 'vitest';
import { screen } from '@testing-library/vue';
import { renderXm } from '@/test/helpers';
import XmField from '@/components/XmField.vue';

describe('XmField options', () => {
    it('garde l’ordre d’un tableau d’options, identifiants entiers compris', () => {
        renderXm(XmField, { props: { name: 'zone', label: 'Département', type: 'select', modelValue: '8', options: [{ value: '', label: 'Choisir…' }, { value: 41, label: 'Abomey' }, { value: 8, label: 'Cotonou' }] } });

        const select = screen.getByLabelText('Département') as HTMLSelectElement;
        expect([...select.options].map((o) => [o.value, o.text])).toEqual([['', 'Choisir…'], ['41', 'Abomey'], ['8', 'Cotonou']]);
        expect(select.value).toBe('8');
    });

    it('accepte toujours un objet { valeur: libellé }', () => {
        renderXm(XmField, { props: { name: 'sexe', label: 'Sexe', type: 'select', modelValue: '', options: { '': 'Choisir…', femme: 'Femme' } } });

        expect([...(screen.getByLabelText('Sexe') as HTMLSelectElement).options].map((o) => o.value)).toEqual(['', 'femme']);
    });

    it('un groupe de cases rend ses options en tableau', () => {
        renderXm(XmField, { props: { name: 'services', label: 'Services', type: 'checkbox-group', modelValue: ['b'], options: [{ value: 'b', label: 'B' }, { value: 'a', label: 'A' }] } });

        expect(screen.getAllByRole('checkbox').map((c) => (c as HTMLInputElement).value)).toEqual(['b', 'a']);
        expect((screen.getByLabelText('B') as HTMLInputElement).checked).toBe(true);
    });
});
