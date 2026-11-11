// @vitest-environment jsdom
// XmTabs, XmAccordion, XmStepper, XmBreadcrumb (P6-04) : motifs ARIA et clavier.
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { cleanup, fireEvent, screen } from '@testing-library/vue';
import { h, nextTick } from 'vue';
import { renderXm } from '@/test/helpers';
import XmAccordion from './XmAccordion.vue';
import XmBreadcrumb from './XmBreadcrumb.vue';
import XmStepper from './XmStepper.vue';
import XmTabs from './XmTabs.vue';

afterEach(() => cleanup());

describe('XmTabs', () => {
    beforeEach(() => window.history.replaceState(null, '', '/design-system/vue'));

    const renderTabs = () => renderXm({ render: () => h(XmTabs, { tabs: { infos: 'Infos', notes: 'Notes', docs: 'Documents' } }, { infos: () => 'A', notes: () => 'B', docs: () => 'C' }) });

    it('premier onglet actif, tabindex roulant, clic et adresse ?tab=', async () => {
        renderTabs();
        const [infos, notes] = screen.getAllByRole('tab');
        expect(infos.getAttribute('aria-selected')).toBe('true');
        expect(notes.getAttribute('tabindex')).toBe('-1');
        await fireEvent.click(notes);
        expect(notes.getAttribute('aria-selected')).toBe('true');
        expect(screen.getByRole('tabpanel', { name: 'Notes' }).className).toContain('show active');
        expect(window.location.search).toBe('?tab=notes');
    });

    it('flèches, Début et Fin déplacent sélection et focus', async () => {
        renderTabs();
        const tabs = screen.getAllByRole('tab');
        tabs[0].focus();
        await fireEvent.keyDown(tabs[0], { key: 'ArrowLeft' });
        await nextTick();
        expect(tabs[2].getAttribute('aria-selected')).toBe('true');
        expect(document.activeElement).toBe(tabs[2]);
        await fireEvent.keyDown(tabs[2], { key: 'Home' });
        await nextTick();
        expect(document.activeElement).toBe(tabs[0]);
    });

    it('reprend l’onglet de l’adresse', () => {
        window.history.replaceState(null, '', '/x?tab=docs');
        renderTabs();
        expect(screen.getByRole('tab', { name: 'Documents' }).getAttribute('aria-selected')).toBe('true');
    });
});

describe('XmAccordion', () => {
    it('un seul panneau ouvert, aria-expanded suivi', async () => {
        renderXm({ render: () => h(XmAccordion, { id: 'faq', html: true, items: [{ q: 'Q1', a: '<p>R1</p>' }, { q: 'Q2', a: '<p>R2</p>' }] }) });
        const [q1, q2] = screen.getAllByRole('button');
        expect(q1.getAttribute('aria-expanded')).toBe('true');
        await fireEvent.click(q2);
        expect(q1.getAttribute('aria-expanded')).toBe('false');
        expect(q2.getAttribute('aria-expanded')).toBe('true');
        expect(document.getElementById('faq-p1')?.className).toContain('show');
        await fireEvent.click(q2);
        expect(q2.getAttribute('aria-expanded')).toBe('false');
        expect(document.querySelector('#faq-p0 .accordion-body p')?.textContent).toBe('R1');
    });

    it('réponse en texte brut par défaut : aucun HTML interprété sans la prop html', () => {
        renderXm({ render: () => h(XmAccordion, { id: 'faqText', items: [{ q: 'Q', a: '<img src=x onerror="alert(1)">' }] }) });
        const body = document.querySelector('#faqText-p0 .accordion-body') as HTMLElement;
        expect(body.querySelector('img')).toBeNull();
        expect(body.textContent).toBe('<img src=x onerror="alert(1)">');
    });
});

describe('XmStepper', () => {
    it('étape courante aria-current, états annoncés', () => {
        renderXm({ render: () => h(XmStepper, { steps: ['Un', 'Deux', 'Trois'], current: 2 }) });
        const items = screen.getAllByRole('listitem');
        expect(items[0].className).toContain('is-done');
        expect(items[1].getAttribute('aria-current')).toBe('step');
        expect(items[2].textContent).toContain('à venir');
        expect(screen.getByRole('list', { name: 'Étapes' })).toBeTruthy();
    });
});

describe('XmBreadcrumb', () => {
    it('dernier élément courant, sans lien', () => {
        renderXm({ render: () => h(XmBreadcrumb, { items: [{ label: 'Accueil', url: '/' }, { label: 'Kit', url: '/kit' }] }) });
        expect(screen.getByRole('navigation', { name: 'Fil d’Ariane' })).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Accueil' }).getAttribute('href')).toBe('/');
        expect(screen.queryByRole('link', { name: 'Kit' })).toBeNull();
        expect(screen.getByText('Kit').getAttribute('aria-current')).toBe('page');
    });
});
