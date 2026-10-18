// @vitest-environment jsdom
// XmPoll (lot fil-ecrans, P7-05) : port de x_render_poll — formulaire (radio ou cases), vote sans rechargement par
// `POST posts/{ulid}/poll/vote` (choices[]), résultats rendus depuis la réponse (barres %, réponse en tête, coche
// « ton choix », participant·es · votes), retrait par `DELETE`, sondage terminé, erreurs annoncées.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, screen, waitFor } from '@testing-library/vue';
import { renderXm } from '@/test/helpers';
import { fakeFetch, json, makePoll } from '@/test/feed';
import type { Poll } from '@/feed/types';
import XmPoll from './XmPoll.vue';

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

const results = (overrides: Partial<Poll> = {}): Poll => makePoll({
    has_voted: true,
    my_choices: [12],
    results_visible: true,
    voters_count: 5,
    votes_count: 5,
    options: [
        { id: 11, label: 'Santé', position: 1, votes_count: 1, percentage: 20, leading: false },
        { id: 12, label: 'Droits', position: 2, votes_count: 3, percentage: 60, leading: true },
        { id: 13, label: 'Bien-être', position: 3, votes_count: 1, percentage: 20, leading: false },
    ],
    ...overrides,
});

function mount(poll: Poll) {
    const view = renderXm(XmPoll, { props: { postId: '01JCPOLL000000000000000001', poll } });
    return view;
}

describe('XmPoll', () => {
    it('propose une réponse unique tant que la personne n’a pas voté, sans résultats', () => {
        const { container } = mount(makePoll());
        expect(screen.getByRole('heading', { level: 3, name: 'Quel thème pour la causerie ?' })).toBeTruthy();
        expect(container.textContent).toContain('Une seule réponse');
        expect(container.textContent).toContain('Se termine dans 2 j');
        expect(container.querySelectorAll('input[type="radio"]')).toHaveLength(3);
        expect(container.querySelector('.xm-poll-bar')).toBeNull();
        expect(container.textContent).toContain('4 participant·es');
    });

    it('vote sans rechargement : POST choices[], puis résultats de la réponse avec la coche et la réponse en tête', async () => {
        const { calls } = fakeFetch(() => json({ data: results() }));
        const view = mount(makePoll());
        await fireEvent.click(screen.getByLabelText('Droits'));
        await fireEvent.click(screen.getByRole('button', { name: /Voter/ }));

        await waitFor(() => expect(view.emitted('update:poll')).toBeTruthy());
        expect(calls[0]).toMatchObject({ url: '/api/v1/posts/01JCPOLL000000000000000001/poll/vote', method: 'POST', body: { choices: [12] } });
        const updated = (view.emitted('update:poll') as unknown[][])[0][0] as Poll;
        await view.rerender({ poll: updated });

        const lead = view.container.querySelector('li.is-lead') as HTMLElement;
        expect(lead.textContent).toContain('Droits');
        expect(lead.textContent).toContain('(ton choix)');
        expect(lead.textContent).toMatch(/60\s%/);
        expect((lead.querySelector('.xm-poll-bar') as HTMLElement).style.width).toBe('60%');
        expect(view.container.textContent).toContain('5 participant·es · 5 votes');
        expect(screen.getByRole('status').textContent).toContain('Merci, ton vote est enregistré.');
    });

    it('demande au moins une réponse avant d’envoyer', async () => {
        const { calls } = fakeFetch(() => json({ data: results() }));
        mount(makePoll());
        await fireEvent.click(screen.getByRole('button', { name: /Voter/ }));
        expect(screen.getByText('Choisis au moins une réponse.')).toBeTruthy();
        expect(calls).toHaveLength(0);
    });

    it('accepte plusieurs réponses quand le sondage le permet', async () => {
        const { calls } = fakeFetch(() => json({ data: results({ multiple: true, my_choices: [11, 13] }) }));
        const { container } = mount(makePoll({ multiple: true }));
        expect(container.querySelectorAll('input[type="checkbox"]')).toHaveLength(3);
        await fireEvent.click(screen.getByLabelText('Santé'));
        await fireEvent.click(screen.getByLabelText('Bien-être'));
        await fireEvent.click(screen.getByRole('button', { name: /Voter/ }));
        await waitFor(() => expect(calls).toHaveLength(1));
        expect(calls[0].body).toEqual({ choices: [11, 13] });
    });

    it('retire le vote par DELETE et revient au formulaire avec le sondage renvoyé', async () => {
        const { calls } = fakeFetch(() => json({ data: makePoll() }));
        const view = mount(results());
        await fireEvent.click(screen.getByRole('button', { name: 'Retirer mon vote' }));
        await waitFor(() => expect(view.emitted('update:poll')).toBeTruthy());
        expect(calls[0]).toMatchObject({ method: 'DELETE', url: '/api/v1/posts/01JCPOLL000000000000000001/poll/vote' });
    });

    it('affiche les résultats sans retrait pour un sondage terminé', () => {
        const { container } = mount(results({ closed: true, can_vote: false }));
        expect(container.textContent).toContain('Sondage terminé');
        expect(screen.queryByRole('button', { name: 'Retirer mon vote' })).toBeNull();
        expect(container.querySelector('form')).toBeNull();
    });

    it('montre les barres avant le vote si l’auteur·rice l’a permis', () => {
        const { container } = mount(makePoll({ results_before_vote: true, results_visible: true, options: results().options }));
        expect(container.querySelector('form')).not.toBeNull();
        expect(container.querySelectorAll('.xm-poll-bar')).toHaveLength(3);
    });

    it('annonce un sondage clos entre-temps (422 poll_closed)', async () => {
        fakeFetch(() => json({ message: 'Ce sondage est terminé.', code: 'poll_closed' }, 422));
        mount(makePoll());
        await fireEvent.click(screen.getByLabelText('Santé'));
        await fireEvent.click(screen.getByRole('button', { name: /Voter/ }));
        expect(await screen.findByText('Ce sondage est terminé : les votes sont clos.')).toBeTruthy();
    });
});
