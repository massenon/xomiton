// @vitest-environment jsdom
// Parité Blade / Vue (P6-04, D9) : chaque Xm* rendu ici doit donner le même arbre HTML que son x-* rendu par Laravel
// (référence resources/js/components/__parity__/<cas>.html, écrite et vérifiée par
// tests/Feature/Components/ComponentParityTest.php). Normalisation : resources/js/test/helpers.ts (normalizeHtml).
// Écarts admis, propres à la technologie et listés par cas : attributs data-bs-* que Bootstrap lit côté Blade alors que
// Vue tient l'état (onglets, accordéon, alerte), textes écrits par Alpine (x-text) et style de x-show / v-show (SOS).
import { afterEach, describe, expect, it } from 'vitest';
import { cleanup } from '@testing-library/vue';
import { h, type Component } from 'vue';
import { normalizeHtml, renderXm, type NormalizeOptions } from '@/test/helpers';
import { useUiStore } from '@/stores/ui';
import XmAccordion from './XmAccordion.vue';
import XmAlert from './XmAlert.vue';
import XmAvatar from './XmAvatar.vue';
import XmBadge from './XmBadge.vue';
import XmBell from './XmBell.vue';
import XmBreadcrumb from './XmBreadcrumb.vue';
import XmButton from './XmButton.vue';
import XmCard from './XmCard.vue';
import XmEmptyState from './XmEmptyState.vue';
import XmErrorSummary from './XmErrorSummary.vue';
import XmField from './XmField.vue';
import XmImg from './XmImg.vue';
import XmLogo from './XmLogo.vue';
import XmModal from './XmModal.vue';
import XmSkeleton from './XmSkeleton.vue';
import XmSosButton from './XmSosButton.vue';
import XmStat from './XmStat.vue';
import XmStatus from './XmStatus.vue';
import XmStepper from './XmStepper.vue';
import XmTabs from './XmTabs.vue';
import XmTimeline from './XmTimeline.vue';
import XmToast from './XmToast.vue';
import XmUrgency from './XmUrgency.vue';

interface ParityCase {
    component: Component;
    props?: Record<string, unknown>;
    slots?: Record<string, () => unknown>;
    options?: NormalizeOptions;
    before?: () => void;
}

const text = (value: string) => () => value;

const CASES: Record<string, ParityCase> = {
    'button-primary': { component: XmButton, props: { icon: 'send' }, slots: { default: text('Envoyer') } },
    'button-soft': { component: XmButton, props: { variant: 'soft', icon: 'plus-lg' }, slots: { default: text('Doux') } },
    'button-icon': { component: XmButton, props: { variant: 'icon', icon: 'gear', label: 'Réglages' } },
    'button-link-disabled': { component: XmButton, props: { href: '/fil', variant: 'outline', size: 'sm', disabled: true }, slots: { default: text('Fil') } },
    'button-loading': { component: XmButton, props: { type: 'submit', loading: true }, slots: { default: text('Envoyer') } },
    'badge-soft': { component: XmBadge, props: { color: 'success', soft: true, icon: 'check-circle', pill: true }, slots: { default: text('Actif') } },
    'badge-violet': { component: XmBadge, props: { color: 'violet' }, slots: { default: text('Pair') } },
    'badge-status': { component: XmBadge, props: { scope: 'alert_type', status: 'denonciation' } },
    'badge-unknown-status': { component: XmBadge, props: { scope: 'case', status: 'archived' } },
    status: { component: XmStatus, props: { status: 'assigned' } },
    urgency: { component: XmUrgency, props: { level: 'critical' } },
    'alert-dismissible': { component: XmAlert, props: { type: 'warning', title: 'Attention', dismissible: true }, slots: { default: text('Vérifie ton adresse.') }, options: { ignoreAttributes: ['data-bs-dismiss'] } },
    'alert-no-icon': { component: XmAlert, props: { type: 'info', icon: false }, slots: { default: text('Information.') } },
    'stat-trend': { component: XmStat, props: { label: 'Publications', value: '128', icon: 'chat-dots', trend: '+12 %', direction: 'up', sub: 'Ce mois-ci' } },
    'stat-link': { component: XmStat, props: { label: 'Alertes', value: '3', variant: 'danger', href: '/espace-pair' } },
    'card-link': { component: XmCard, props: { title: 'Comprendre le consentement', badge: 'Fiche', meta: '5 min de lecture', href: '/thematiques/consentement' }, slots: { default: text('Une fiche courte.') } },
    'card-header': { component: XmCard, props: { header: true, title: 'Mes groupes' }, slots: { default: text('Corps'), actions: () => h('button', { type: 'button', class: 'btn btn-sm btn-light' }, 'Exporter') } },
    'card-image': { component: XmCard, props: { variant: 'event', title: 'Atelier', image: '/assets/images/xomiton/covers/og-default.jpg', imageAlt: 'Atelier au centre' }, slots: { default: text('Samedi.') } },
    'avatar-initials': { component: XmAvatar, props: { name: 'Awa Dossou', size: 'lg' } },
    'avatar-image': { component: XmAvatar, props: { name: 'Awa', src: '/assets/images/xomiton/avatar1.png', size: 'sm', rounded: '2' } },
    'empty-state': { component: XmEmptyState, props: { title: 'Rien pour l’instant', icon: 'people' }, slots: { default: text('Les publications apparaîtront ici.'), action: () => h('a', { href: '/groupes', class: 'btn btn-primary' }, 'Voir les groupes') } },
    'empty-state-compact': { component: XmEmptyState, props: { title: 'Aucun résultat', compact: true } },
    'skeleton-lines': { component: XmSkeleton, props: { lines: 4 } },
    'skeleton-card': { component: XmSkeleton, props: { type: 'card' } },
    'skeleton-avatar': { component: XmSkeleton, props: { type: 'avatar', label: 'Chargement des membres' } },
    'skeleton-kpi': { component: XmSkeleton, props: { type: 'kpi' } },
    'skeleton-table': { component: XmSkeleton, props: { type: 'table', rows: 2 } },
    stepper: { component: XmStepper, props: { steps: ['Identité', 'Contact', 'Confirmation'], current: 2 } },
    tabs: { component: XmTabs, props: { id: 'dsTabs', tabs: { infos: 'Informations', notes: 'Notes' }, param: null }, slots: { infos: text('Premier panneau'), notes: text('Second panneau') }, options: { ignoreAttributes: ['data-bs-toggle', 'data-bs-target'] } },
    accordion: { component: XmAccordion, props: { id: 'faq', level: 'h2', html: true, items: [{ q: 'Mes messages sont-ils privés ?', a: '<p>Oui.</p>' }, { q: 'Puis-je partir ?', a: '<p>À tout moment.</p>' }] }, options: { ignoreAttributes: ['data-bs-toggle', 'data-bs-target', 'data-bs-parent'] } },
    breadcrumb: { component: XmBreadcrumb, props: { items: [{ label: 'Accueil', url: '/' }, { label: 'Ressources', url: '/ressources' }, { label: 'Kit' }] } },
    'error-summary': { component: XmErrorSummary, props: { messages: { pseudo: 'Choisis un pseudo.', 'contacts.0.phone': ['Numéro invalide.'] }, autofocus: false } },
    logo: { component: XmLogo, props: { width: 120 } },
    'logo-adaptive': { component: XmLogo, props: { adaptive: true, link: false, imgClass: 'navbar-brand-item' } },
    'logo-icone': { component: XmLogo, props: { variant: 'icone', width: 48 } },
    'img-picture': { component: XmImg, props: { src: '/media/a.jpg', alt: 'Atelier', width: 800, height: 450, webp: '/media/a.webp 800w' } },
    'img-decorative': { component: XmImg, props: { src: '/media/d.svg', alt: '' } },
    'img-priority': { component: XmImg, props: { src: '/media/hero.jpg', alt: 'Accueil', priority: true, srcset: '/media/hero-800.jpg 800w, /media/hero.jpg 1600w' } },
    'modal-delete': { component: XmModal, props: { id: 'mDel', title: 'Supprimer la publication ?', type: 'delete', confirmWord: 'SUPPRIMER', teleport: false }, slots: { default: text('Cette action est définitive.') } },
    'modal-static': { component: XmModal, props: { id: 'mEdit', title: 'Modifier', size: 'lg', action: 'Enregistrer', static: true, teleport: false }, slots: { default: text('Corps') } },
    'field-text': { component: XmField, props: { name: 'pseudo', label: 'Pseudo', hint: 'Visible par les membres.', error: 'Trop court.', autocomplete: 'nickname', optional: true } },
    'field-select': { component: XmField, props: { name: 'zone', label: 'Département', type: 'select', options: { 1: 'Littoral', 2: 'Atlantique' } } },
    'field-switch': { component: XmField, props: { name: 'gps', label: 'Partager ma position', type: 'switch' } },
    'field-radio': { component: XmField, props: { name: 'age', label: 'Âge', type: 'radio-group', options: { a: '15-17 ans', b: '18-24 ans' } } },
    'field-password': { component: XmField, props: { name: 'password', label: 'Mot de passe', type: 'password', autocomplete: 'current-password' } },
    'field-textarea': { component: XmField, props: { name: 'bio', label: 'Présentation', type: 'textarea', maxlength: 200, rows: 3 } },
    toast: { component: XmToast, before: () => { useUiStore().pushToast('Ta publication est en ligne.', 'success'); } },
    bell: { component: XmBell, props: { variant: 'member', items: [{ id: 'n1', text: 'Nouvelle réponse', time: 'il y a 5 min', href: '/fil', read: false }] } },
    'sos-button': { component: XmSosButton, props: { fixed: false, id: 'dsSos', managed: false }, options: { text: false, style: false } },
    timeline: { component: XmTimeline, props: { label: 'Suivi du dossier', items: [
        { title: 'Entretien d’accueil', date: '12/03/2026', datetime: '2026-03-12', by: 'Pair A.', kind: 'shared', badge: { label: 'Réalisé', color: 'success' }, text: 'Premier échange.' },
        { title: 'Relance', kind: 'internal', next: '20/03/2026', late: true },
        { title: 'Clôture', date: '25/03/2026', color: 'warning' },
    ] } },
    'timeline-empty': { component: XmTimeline, props: { items: [] } },
};

const FIXTURES = import.meta.glob<string>('./__parity__/*.html', { query: '?raw', import: 'default', eager: true });
const fixture = (name: string): string => {
    const html = FIXTURES[`./__parity__/${name}.html`];
    if (html === undefined) throw new Error(`Référence de parité absente : ${name}`);
    return html;
};

describe('parité Blade / Vue des composants Xm*', () => {
    afterEach(() => cleanup());

    it('couvre chaque référence écrite par ComponentParityTest', () => {
        const files = readdirNames();
        expect(Object.keys(CASES).sort()).toEqual(files);
    });

    it.each(Object.keys(CASES))('%s', (name) => {
        const parityCase = CASES[name];
        const { container } = renderXm({
            render: () => h(parityCase.component, parityCase.props ?? {}, parityCase.slots ?? {}),
            setup() {
                parityCase.before?.();
            },
        });
        expect(normalizeHtml(container.innerHTML, parityCase.options)).toBe(normalizeHtml(fixture(name), parityCase.options));
    });
});

function readdirNames(): string[] {
    return Object.keys(FIXTURES).map((path) => path.replace(/^\.\/__parity__\//, '').replace(/\.html$/, '')).sort();
}
