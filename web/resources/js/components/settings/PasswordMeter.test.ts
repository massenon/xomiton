// @vitest-environment jsdom
// Jauge de solidité Vue (P8-09) : même HTML et mêmes règles que le partiel Blade et password-meter.js
// (App\Domain\Auth\PasswordStrength) — niveau écrit (jamais la seule couleur), segments allumés, règles cochées.
import { describe, expect, it } from 'vitest';
import { renderXm } from '@/test/helpers';
import PasswordMeter from './PasswordMeter.vue';

const meter = (password: string) => {
    const { container } = renderXm(PasswordMeter, { props: { id: 'pwMeter', password } });
    return container.querySelector('#pwMeter') as HTMLElement;
};

describe('<PasswordMeter>', () => {
    it('reste neutre tant que le champ est vide, règles listées et liées par {id}-rules', () => {
        const el = meter('');
        expect(el.dataset.level).toBe('empty');
        expect(el.querySelectorAll('.xm-pw-meter__segment.is-on')).toHaveLength(0);
        expect(el.querySelector('#pwMeter-rules')?.querySelectorAll('li')).toHaveLength(5);
        expect(el.querySelector('[aria-live="polite"]')?.textContent).toContain('Solidité du mot de passe');
    });

    it('écrit le niveau et coche les règles comme le serveur', () => {
        const weak = meter('abcdefghij');
        expect(weak.dataset.level).toBe('weak');
        expect(weak.querySelectorAll('.xm-pw-meter__segment.is-on.is-danger')).toHaveLength(1);

        const excellent = meter('Nouveau-Mot2passe');
        expect(excellent.dataset.level).toBe('excellent');
        expect(excellent.querySelectorAll('.xm-pw-meter__segment.is-on.is-success')).toHaveLength(4);
        expect(excellent.querySelector('[data-rule="symbol"]')?.getAttribute('data-checked')).toBe('true');
        expect(excellent.querySelector('[data-password-label]')?.textContent).toBe('Excellent');
    });
});
