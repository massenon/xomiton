// @vitest-environment jsdom
// Modale « Signaler cette publication / ce commentaire » (lot fil-ecrans, P7-05) : motifs fournis par la page
// (feed/reportReasons.ts, props `report_reasons` = ContentReportReason::selectable()), « Autre » seul sans eux, motif
// obligatoire, envoi `POST posts/{ulid}/report` ou `comments/{id}/report` (201 : transmis ; 200 : déjà signalé), erreurs
// 422 sous les champs, renvoi vers `/signaler?post={ulid}` pour une personne en danger.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, screen, waitFor } from '@testing-library/vue';
import { renderXm } from '@/test/helpers';
import { fakeFetch, json } from '@/test/feed';
import { REPORT_REASONS } from '@/feed/reportReasons';
import { useUiStore } from '@/stores/ui';
import ReportContentModal from './ReportContentModal.vue';

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.className = '';
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
});

const reasons = [
    { value: 'harcelement', label: 'Harcèlement ou insultes' },
    { value: 'spam', label: 'Spam' },
    { value: 'autre', label: 'Autre' },
];

const POST = '01JC0000000000000000000001';

const mount = (target: { post: string } | { comment: number } = { post: POST }, provided: typeof reasons | null = reasons) => renderXm(ReportContentModal, {
    props: { target },
    global: provided ? { provide: { [REPORT_REASONS as symbol]: provided } } : {},
});

/** Bootstrap Modal : ouverture terminée, focus piégé. */
const shown = () => waitFor(() => expect(document.querySelector('.modal.show')?.contains(document.activeElement)).toBe(true));
const submit = () => fireEvent.submit(document.querySelector('#modalReportContent form') as HTMLFormElement);
const toasts = () => useUiStore().toasts.map((toast) => toast.message);

describe('ReportContentModal', () => {
    it('propose les motifs fournis par la page et renvoie vers /signaler?post= pour une personne en danger', async () => {
        mount();
        await shown();
        expect(document.getElementById('modalReportContentTitle')?.textContent).toBe('Signaler cette publication');
        expect([...document.querySelectorAll<HTMLInputElement>('#modalReportContent input[type="radio"]')].map((input) => input.value)).toEqual(['harcelement', 'spam', 'autre']);
        expect(screen.getByLabelText('Harcèlement ou insultes')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Fais un signalement anonyme' }).getAttribute('href')).toBe(`/signaler?post=${POST}`);
    });

    it('exige un motif avant tout envoi', async () => {
        const { calls } = fakeFetch(() => json({ data: { reported: true } }, 201));
        mount();
        await shown();
        await submit();
        expect(await screen.findAllByText('Choisis un motif.')).not.toHaveLength(0);
        expect(calls).toHaveLength(0);
    });

    it('transmet le signalement (201), l’annonce et ferme la modale', async () => {
        const { calls } = fakeFetch(() => json({ data: { reported: true } }, 201));
        const view = mount();
        await shown();
        await fireEvent.click(screen.getByLabelText('Spam'));
        await fireEvent.update(screen.getByLabelText(/Précisions/), '  Liens publicitaires répétés  ');
        await submit();
        await waitFor(() => expect(view.emitted('reported')).toBeTruthy());
        expect(calls[0]).toMatchObject({ method: 'POST', url: `/api/v1/posts/${POST}/report`, body: { reason: 'spam', details: 'Liens publicitaires répétés' } });
        expect(toasts()).toContain('Signalement transmis à la modération. Merci.');
        await waitFor(() => expect(view.emitted('hidden')).toBeTruthy());
    });

    it('remercie sans doublon quand le contenu est déjà signalé (200)', async () => {
        fakeFetch(() => json({ data: { reported: true } }, 200));
        const view = mount();
        await shown();
        await fireEvent.click(screen.getByLabelText('Harcèlement ou insultes'));
        await submit();
        await waitFor(() => expect(view.emitted('reported')).toBeTruthy());
        expect(toasts()).toContain('Tu as déjà signalé ce contenu. Merci.');
        await waitFor(() => expect(view.emitted('hidden')).toBeTruthy());
    });

    it('affiche les erreurs 422 sous les champs et garde la modale ouverte', async () => {
        fakeFetch(() => json({ message: 'Données invalides.', code: 'validation_failed', errors: { details: ['Les précisions ne peuvent pas dépasser 500 caractères.'] } }, 422));
        const view = mount();
        await shown();
        await fireEvent.click(screen.getByLabelText('Autre'));
        await submit();
        expect(await screen.findAllByText('Les précisions ne peuvent pas dépasser 500 caractères.')).not.toHaveLength(0);
        expect(view.emitted('reported')).toBeUndefined();
        expect(document.querySelector('.modal.show')).not.toBeNull();
    });

    it('signale un commentaire, avec le seul motif « Autre » quand la page ne fournit pas les motifs', async () => {
        const { calls } = fakeFetch(() => json({ data: { reported: true } }, 201));
        const view = mount({ comment: 42 }, null);
        await shown();
        expect(document.getElementById('modalReportContentTitle')?.textContent).toBe('Signaler ce commentaire');
        expect([...document.querySelectorAll<HTMLInputElement>('#modalReportContent input[type="radio"]')].map((input) => input.value)).toEqual(['autre']);
        expect(screen.getByRole('link', { name: 'Fais un signalement anonyme' }).getAttribute('href')).toBe('/signaler');
        await fireEvent.click(screen.getByLabelText('Autre'));
        await submit();
        await waitFor(() => expect(view.emitted('reported')).toBeTruthy());
        expect(calls[0]).toMatchObject({ method: 'POST', url: '/api/v1/comments/42/report', body: { reason: 'autre', details: null } });
        await waitFor(() => expect(view.emitted('hidden')).toBeTruthy());
    });
});
