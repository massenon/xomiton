// @vitest-environment jsdom
// XmVerifiedBadges (P18-09, Q97) : badges vérifiés par l'administration à côté du pseudo — un libellé lisible par
// badge, icône décorative, rien sans badge.
import { afterEach, describe, expect, it } from 'vitest';
import { cleanup } from '@testing-library/vue';
import { renderXm } from '@/test/helpers';
import XmVerifiedBadges from './XmVerifiedBadges.vue';

afterEach(cleanup);

describe('XmVerifiedBadges', () => {
    it('affiche chaque badge avec son libellé et une icône décorative', () => {
        const { container } = renderXm(XmVerifiedBadges, { props: { badges: [
            { key: 'pair_animateur', label: 'Pair animateur·rice', icon: 'award-fill' },
        ] } });
        const badge = container.querySelector('[data-badge="pair_animateur"]');
        expect(badge?.textContent).toBe('Pair animateur·rice');
        expect(badge?.querySelector('i.bi-award-fill')?.getAttribute('aria-hidden')).toBe('true');
    });

    it.each([[[]], [null], [undefined]])('ne rend rien sans badge (%j)', (badges) => {
        const { container } = renderXm(XmVerifiedBadges, { props: { badges: badges as never } });
        expect(container.querySelector('.xm-verified-badge')).toBeNull();
    });
});
