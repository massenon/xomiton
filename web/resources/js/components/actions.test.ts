// @vitest-environment jsdom
// XmButton, XmBadge, XmStatus, XmUrgency, XmAlert, XmStat (P6-04) : variantes, accessibilité, évènements.
import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, fireEvent, screen } from '@testing-library/vue';
import { h } from 'vue';
import { renderXm, useFrench } from '@/test/helpers';
import XmAlert from './XmAlert.vue';
import XmBadge from './XmBadge.vue';
import XmButton from './XmButton.vue';
import XmStat from './XmStat.vue';
import XmStatus from './XmStatus.vue';
import XmUrgency from './XmUrgency.vue';

afterEach(() => cleanup());

describe('XmButton', () => {
    it('rend un bouton primaire et relaie le clic', async () => {
        const clicks: Event[] = [];
        renderXm({ render: () => h(XmButton, { onClick: (e: Event) => clicks.push(e) }, () => 'Publier') });
        const button = screen.getByRole('button', { name: 'Publier' });
        expect(button.className).toBe('btn btn-primary');
        expect(button.getAttribute('type')).toBe('button');
        await fireEvent.click(button);
        expect(clicks).toHaveLength(1);
    });

    it('variante soft = btn-primary-soft, tailles sm et lg', () => {
        renderXm({ render: () => [h(XmButton, { variant: 'soft', size: 'sm' }, () => 'A'), h(XmButton, { variant: 'danger-soft', size: 'lg' }, () => 'B')] });
        expect(screen.getByRole('button', { name: 'A' }).className).toBe('btn btn-primary-soft btn-sm');
        expect(screen.getByRole('button', { name: 'B' }).className).toBe('btn btn-danger-soft btn-lg');
    });

    it('chargement : spinner, « Envoi… », désactivé et aria-busy', () => {
        renderXm({ render: () => h(XmButton, { loading: true, type: 'submit' }, () => 'Envoyer') });
        const button = screen.getByRole('button');
        expect(button.textContent?.trim()).toBe('Envoi…');
        expect(button.hasAttribute('disabled')).toBe(true);
        expect(button.getAttribute('aria-busy')).toBe('true');
        expect(button.querySelector('.spinner-border')).not.toBeNull();
    });

    it('lien désactivé : aria-disabled, tabindex -1, classe disabled', () => {
        renderXm({ render: () => h(XmButton, { href: '/fil', disabled: true }, () => 'Fil') });
        const link = screen.getByText('Fil').closest('a') as HTMLAnchorElement;
        expect(link.getAttribute('href')).toBe('/fil');
        expect(link.getAttribute('aria-disabled')).toBe('true');
        expect(link.getAttribute('tabindex')).toBe('-1');
        expect(link.classList.contains('disabled')).toBe(true);
    });

    it('bouton icône : nom accessible obligatoire', () => {
        renderXm({ render: () => h(XmButton, { variant: 'icon', icon: 'gear', label: 'Réglages' }) });
        const button = screen.getByRole('button', { name: 'Réglages' });
        expect(button.getAttribute('title')).toBe('Réglages');
        expect(button.classList.contains('xm-btn-icon')).toBe(true);
        expect(() => renderXm({ render: () => h(XmButton, { variant: 'icon', icon: 'gear' }) })).toThrow(/label/);
    });
});

describe('XmBadge et XmStatus', () => {
    it('statut prêt à l’emploi depuis lang/fr (libellé, couleur, icône)', () => {
        renderXm({ render: () => h(XmStatus, { status: 'resolved' }) });
        const badge = screen.getByText('Résolu');
        expect(badge.className).toContain('bg-success');
        expect(badge.querySelector('.bi-check-circle')).not.toBeNull();
    });

    it('signalement en variante douce, statut inconnu en neutre', () => {
        renderXm({ render: () => [h(XmBadge, { scope: 'alert_type', status: 'besoin' }), h(XmBadge, { scope: 'case', status: 'zzz' })] });
        expect(screen.getByText('Besoin').className).toContain('bg-info bg-opacity-10 text-info');
        expect(screen.getByText('zzz').className).toContain('bg-light text-body border');
    });

    it('alerte : texte foncé sur fond jaune', () => {
        renderXm({ render: () => h(XmBadge, { color: 'warning' }, () => 'À revoir') });
        expect(screen.getByText('À revoir').className).toBe('badge bg-warning text-dark');
    });
});

describe('XmUrgency', () => {
    it('icône + mot, niveau inconnu ramené à faible', () => {
        useFrench();
        renderXm({ render: () => [h(XmUrgency, { level: 'high' }), h(XmUrgency, { level: 'zzz' })] });
        expect(screen.getByText(/Élevée/).closest('.xm-urgency')?.className).toBe('xm-urgency xm-urgency--high');
        expect(screen.getByText(/Faible/).closest('.xm-urgency')?.className).toBe('xm-urgency xm-urgency--low');
        expect(screen.getAllByText('Urgence :')).toHaveLength(2);
    });
});

describe('XmAlert', () => {
    it('role alert pour danger, status pour info ; fermeture', async () => {
        const dismissed: number[] = [];
        renderXm({ render: () => [
            h(XmAlert, { type: 'danger', title: 'Erreur' }, () => 'Réessaie.'),
            h(XmAlert, { type: 'info', dismissible: true, onDismiss: () => dismissed.push(1) }, () => 'Info'),
        ] });
        expect(screen.getByRole('alert').textContent).toContain('Erreur');
        const status = screen.getByRole('status');
        await fireEvent.click(screen.getByRole('button', { name: 'Fermer' }));
        expect(dismissed).toEqual([1]);
        expect(status.isConnected).toBe(false);
    });
});

describe('XmStat', () => {
    it('tendance avec mot caché, lien vers la liste filtrée', () => {
        renderXm({ render: () => h(XmStat, { label: 'Cas', value: 12, trend: '-3 %', direction: 'down', href: '/espace-pair', variant: 'warning' }) });
        expect(screen.getByText('(en baisse)', { exact: false }).className).toBe('visually-hidden');
        const link = screen.getByRole('link');
        expect(link.getAttribute('href')).toBe('/espace-pair');
        expect(link.querySelector('.card.xm-stat.warning.xm-card-hover')).not.toBeNull();
        expect(screen.getByText('Voir le détail')).toBeTruthy();
    });
});
