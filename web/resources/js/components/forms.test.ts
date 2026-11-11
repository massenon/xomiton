// @vitest-environment jsdom
// XmField et XmErrorSummary (P6-04, ACCESSIBILITE §4) : liaisons libellé / aide / erreur, v-model, résumé focalisé.
import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, fireEvent, screen } from '@testing-library/vue';
import { h, nextTick, ref } from 'vue';
import { renderXm } from '@/test/helpers';
import XmErrorSummary from './XmErrorSummary.vue';
import XmField from './XmField.vue';

afterEach(() => cleanup());

describe('XmField', () => {
    it('libellé lié, aide et erreur dans aria-describedby, aria-invalid', () => {
        renderXm({ render: () => h(XmField, { name: 'pseudo', label: 'Pseudo', hint: 'Visible.', error: 'Trop court.', 'aria-describedby': 'rules' }) });
        const input = screen.getByLabelText('Pseudo');
        expect(input.id).toBe('f-pseudo');
        expect(input.getAttribute('aria-describedby')).toBe('f-pseudo-hint rules f-pseudo-error');
        expect(input.getAttribute('aria-invalid')).toBe('true');
        expect(screen.getByText('Trop court.').id).toBe('f-pseudo-error');
    });

    it('v-model et sortie du champ (validation au blur)', async () => {
        const value = ref('');
        const blurs: number[] = [];
        renderXm({ render: () => h(XmField, { name: 'email', label: 'E-mail', type: 'email', modelValue: value.value, 'onUpdate:modelValue': (v: unknown) => { value.value = String(v); }, onBlur: () => blurs.push(1) }) });
        const input = screen.getByLabelText('E-mail') as HTMLInputElement;
        await fireEvent.update(input, 'awa@example.org');
        await fireEvent.blur(input);
        expect(value.value).toBe('awa@example.org');
        expect(blurs).toEqual([1]);
    });

    it('mot de passe : bouton œil qui bascule le type, collage autorisé', async () => {
        renderXm({ render: () => h(XmField, { name: 'password', label: 'Mot de passe', type: 'password' }) });
        const input = screen.getByLabelText('Mot de passe') as HTMLInputElement;
        const toggle = screen.getByRole('button', { name: 'Afficher le mot de passe' });
        await fireEvent.click(toggle);
        expect(input.type).toBe('text');
        expect(toggle.getAttribute('aria-pressed')).toBe('true');
        expect(toggle.getAttribute('aria-label')).toBe('Masquer le mot de passe');
    });

    it('textarea : compteur de caractères', async () => {
        const value = ref('Bonjour');
        renderXm({ render: () => h(XmField, { name: 'bio', label: 'Bio', type: 'textarea', maxlength: 100, modelValue: value.value, 'onUpdate:modelValue': (v: unknown) => { value.value = String(v); } }) });
        expect(screen.getByText('7 / 100')).toBeTruthy();
        await fireEvent.update(screen.getByLabelText('Bio'), 'Bonjour à toi');
        await nextTick();
        expect(screen.getByText('13 / 100')).toBeTruthy();
    });

    it('interrupteur, liste, groupes radio et cases', async () => {
        const values: unknown[] = [];
        const onUpdate = (v: unknown) => values.push(v);
        renderXm({ render: () => [
            h(XmField, { name: 'gps', label: 'Position', type: 'switch', modelValue: true, 'onUpdate:modelValue': onUpdate }),
            h(XmField, { name: 'zone', label: 'Zone', type: 'select', options: { 1: 'Littoral', 2: 'Atlantique' }, modelValue: '2' }),
            h(XmField, { name: 'age', label: 'Âge', type: 'radio-group', options: { a: '15-17', b: '18-24' }, modelValue: 'b', optional: true }),
            h(XmField, { name: 'needs', label: 'Besoins', type: 'checkbox-group', options: { s: 'Santé', j: 'Juridique' }, modelValue: ['j'], 'onUpdate:modelValue': onUpdate }),
        ] });
        const toggle = screen.getByRole('switch', { name: 'Position' }) as HTMLInputElement;
        expect(toggle.checked).toBe(true);
        await fireEvent.click(toggle);
        expect((screen.getByLabelText('Zone') as HTMLSelectElement).value).toBe('2');
        expect((screen.getByLabelText('18-24') as HTMLInputElement).checked).toBe(true);
        expect(screen.getByRole('group', { name: /Âge/ }).textContent).toContain('(facultatif)');
        await fireEvent.click(screen.getByLabelText('Santé'));
        expect(values).toEqual([false, ['s', 'j']]);
    });
});

describe('XmErrorSummary', () => {
    it('un lien par champ vers son identifiant, focalisé', async () => {
        renderXm({ render: () => h(XmErrorSummary, { messages: { pseudo: ['Choisis un pseudo.'], 'contacts.0.phone': 'Numéro invalide.' } }) });
        await nextTick();
        const summary = screen.getByRole('alert');
        expect(summary.textContent).toContain('2 erreurs à corriger');
        expect(screen.getByRole('link', { name: 'Numéro invalide.' }).getAttribute('href')).toBe('#f-contacts-0-phone');
        expect(document.activeElement).toBe(summary);
    });

    it('rien sans erreur ; singulier pour une erreur', () => {
        const { container } = renderXm({ render: () => h(XmErrorSummary, { messages: {} }) });
        expect(container.textContent).toBe('');
        cleanup();
        renderXm({ render: () => h(XmErrorSummary, { messages: { a: 'x' }, autofocus: false }) });
        expect(screen.getByRole('alert').textContent).toContain('1 erreur à corriger');
    });
});
