// @vitest-environment jsdom
// Page /assistant (lot assistant, P8-11 ; prototype assistant.php) : un message de crise affiche aussitôt les numéros
// d'urgence, le SOS et « Parler à un humain » **sans appel au fournisseur** (aucune requête), question ordinaire par
// l'API, `persist` seulement sur demande, hors connexion sans requête, suggestions du repli, historique de l'onglet
// (rien en mode discret), « Effacer la conversation » (DELETE si enregistrée), « Parler à un humain » (modale, clé
// d'idempotence, code de suivi) ; file des requêtes (question posée pendant l'enregistrement de la crise, « Effacer »
// pendant l'envoi), échec d'enregistrement dit, crise envoyable pendant une réponse en cours, demande anonyme sans
// conversation, échec 429 avec numéro d'urgence.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { flushPromises } from '@vue/test-utils';
import { memberProps, mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { useUiStore } from '@/stores/ui';
import Index from './Index.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    sessionStorage.clear();
    document.body.classList.remove('modal-open');
});

const CRISIS_TEXT = 'Je te crois, et tu n’es pas seule. Si tu es en danger maintenant, mets-toi à l’abri si tu le peux et appelle tout de suite :\n• Police secours : 117\n• Ligne d’écoute Xomiton : +229 01 97 38 88 47\n\nBouton SOS : ton alerte part avec ta position.';

const pageProps = (overrides: Record<string, unknown> = {}) => ({
    title: 'Assistant',
    chips: ['Comment porter plainte ?', 'Contraception d’urgence'],
    max_length: 500,
    crisis: {
        keywords: ['me tuer', 'il va me tuer', 'frappe', 'sequestree'],
        text: CRISIS_TEXT,
        links: [
            { label: 'SOS avec position GPS', url: '/sos', variant: 'danger' },
            { label: 'Appeler le 117 (Police secours)', url: 'tel:117', variant: 'primary-soft' },
            { label: 'Appeler le +229 01 97 38 88 47 (Ligne d’écoute Xomiton)', url: 'tel:+2290197388847', variant: 'primary-soft' },
            { label: 'Exprimer un besoin', url: '/signaler?tab=besoin', variant: 'primary-soft' },
        ],
    },
    offline: {
        text: 'Tu es hors connexion : l’assistant a besoin du réseau. En danger ? Appelle le 117 ou ouvre les contacts d’urgence.',
        escalation: 'Tu es hors connexion : ta demande ne peut pas partir.',
        call: { label: 'Appeler le 117 (Police secours)', url: 'tel:117' },
    },
    escalation: { channels: { sms: 'SMS', whatsapp: 'WhatsApp', call: 'Appel', email: 'E-mail' }, message_max: 1000 },
    links: { sos: '/sos', urgences: '/urgences', annuaire: '/annuaire' },
    ...overrides,
});

const answer = (data: Record<string, unknown>) => json({ data: { text: 'Rends-toi au commissariat.', kind: 'kb', emergency: false, suggestions: [], links: [], conversation_id: null, escalation: false, ...data } });

const mount = (overrides: Record<string, unknown> = {}, props = memberProps()) => mountInertia(Index, { url: '/assistant', props, pageProps: pageProps(overrides) });

async function send(text: string) {
    await fireEvent.update(screen.getByLabelText('Ton message'), text);
    await fireEvent.submit(document.querySelector('.card-footer form') as HTMLFormElement);
    await flushPromises();
}

/** Réponse retenue jusqu'à `resolve()` (réseau lent). */
function deferred() {
    let resolve!: (response: Response) => void;
    const promise = new Promise<Response>((done) => { resolve = done; });
    return { promise, resolve };
}

const keepConversation = () => fireEvent.click(screen.getByRole('switch', { name: /Garder cette conversation/ }));

const bubbles = () => [...document.querySelectorAll('[data-xm-chat] [data-who]')].map((row) => [row.getAttribute('data-who'), row.querySelector('.bubble')?.textContent?.trim()]);

describe('Page /assistant', () => {
    it('affiche l’accueil du prototype, les puces et la colonne « En danger ? » · « Parler à un humain »', async () => {
        fakeFetch(() => answer({}));
        await mount();

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Assistant Xomiton');
        expect(bubbles()[0][1]).toContain('Bonjour, je suis l’assistant Xomiton.');
        expect(screen.getByRole('group', { name: 'Questions fréquentes' }).querySelectorAll('button')).toHaveLength(2);
        expect(screen.getByRole('log', { name: 'Conversation avec l’assistant' })).toBeTruthy();
        const aside = screen.getByRole('complementary');
        expect(aside.querySelector('a.btn-danger')?.getAttribute('href')).toBe('/sos');
        expect(aside.querySelector('a[href="/annuaire"]')).not.toBeNull();
        expect(document.querySelector('[data-xm-history]')?.textContent).toContain('La conversation est gardée uniquement dans cet onglet.');
    });

    it('affiche les numéros d’urgence d’un message de crise sans aucun appel au fournisseur', async () => {
        const { calls } = fakeFetch(() => answer({}));
        await mount();

        await send('Il va me TUER, aidez-moi');

        expect(calls).toHaveLength(0);
        const crisis = document.querySelector('[data-kind="crisis"]') as HTMLElement;
        expect(crisis.textContent).toContain('Police secours : 117');
        expect(crisis.textContent).toContain('+229 01 97 38 88 47');
        expect([...crisis.querySelectorAll('a')].map((a) => a.getAttribute('href'))).toEqual(['/sos', 'tel:117', 'tel:+2290197388847', '/signaler?tab=besoin']);
        expect(crisis.querySelector('a[href="/sos"]')?.classList.contains('btn-danger')).toBe(true);
        expect([...crisis.querySelectorAll('button')].map((b) => b.textContent?.trim())).toContain('Parler à un humain');
    });

    it('répond aussi à une crise hors connexion, sans requête', async () => {
        const { calls } = fakeFetch(() => answer({}));
        await mount();
        useUiStore().setOnline(false);

        await send('il me frappe');
        expect(document.querySelector('[data-kind="crisis"]')?.textContent).toContain('117');

        await send('Comment porter plainte ?');
        expect(calls).toHaveLength(0);
        expect(bubbles().at(-1)?.[1]).toContain('Tu es hors connexion');
        expect(document.querySelector('[data-xm-chat] a[href="tel:117"]')).not.toBeNull();
    });

    it('pose une question ordinaire à l’API sans demander d’enregistrement, puis affiche la réponse', async () => {
        const { calls } = fakeFetch(() => answer({}));
        await mount();

        await send('Comment porter plainte ?');

        expect(calls).toHaveLength(1);
        expect(calls[0]).toMatchObject({ url: '/api/v1/assistant/question', method: 'POST', body: { question: 'Comment porter plainte ?' } });
        expect(bubbles().slice(-2)).toEqual([['me', 'Toi : Comment porter plainte ?'], ['bot', 'Assistant : Rends-toi au commissariat.']]);
        expect(document.activeElement).toBe(screen.getByLabelText('Ton message'));
    });

    it('propose les suggestions du repli et les pose d’un clic', async () => {
        const { calls } = fakeFetch(({ body }) => ((body as { question: string }).question === 'blabla'
            ? answer({ kind: 'fallback', text: 'Je n’ai pas trouvé.', suggestions: ['Contraception d’urgence'] })
            : answer({})));
        await mount();

        await send('blabla');
        const suggestion = [...document.querySelectorAll('[data-xm-chat] button')].find((b) => b.textContent?.trim() === 'Contraception d’urgence') as HTMLElement;
        await fireEvent.click(suggestion);
        await flushPromises();

        expect(calls.map((call) => (call.body as { question: string }).question)).toEqual(['blabla', 'Contraception d’urgence']);
    });

    it('enregistre sur demande : persist puis conversation_id, crise envoyée en arrière-plan pour relecture', async () => {
        const { calls } = fakeFetch(({ body }) => answer({ conversation_id: 41, kind: (body as { question: string }).question.includes('frappe') ? 'crisis' : 'kb' }));
        await mount();

        await fireEvent.click(screen.getByRole('switch', { name: /Garder cette conversation/ }));
        await send('Comment porter plainte ?');
        await send('il me frappe');

        expect(calls.map((call) => call.body)).toEqual([
            { question: 'Comment porter plainte ?', persist: true },
            { question: 'il me frappe', persist: true, conversation_id: 41 },
        ]);
        // La réponse de crise reste celle de la page (numéros), affichée sans attendre le serveur.
        expect(document.querySelectorAll('[data-kind="crisis"]')).toHaveLength(1);
        expect(document.querySelector('[data-xm-history]')?.textContent).toContain('Conversation enregistrée et chiffrée');
    });

    it('garde l’historique dans l’onglet et le retrouve', async () => {
        fakeFetch(() => answer({}));
        await mount();
        await send('Comment porter plainte ?');

        const stored = JSON.parse(sessionStorage.getItem('xm_assistant_v1:7') || '{}');
        expect(stored.messages.map((m: { text: string }) => m.text)).toEqual(['Comment porter plainte ?', 'Rends-toi au commissariat.']);

        unmountInertia();
        await mount();
        expect(bubbles().map(([, text]) => text)).toEqual(['Toi : Comment porter plainte ?', 'Assistant : Rends-toi au commissariat.']);
    });

    it('ne garde rien dans le navigateur en mode discret, même une conversation laissée avant', async () => {
        sessionStorage.setItem('xm_assistant_v1:7', JSON.stringify({ messages: [{ id: 1, who: 'me', text: 'ancien' }], conversationId: null, persist: false }));
        fakeFetch(() => answer({}));
        await mount({}, memberProps({ auth: { user: { profile: { discreet_mode: true } } } }));

        expect(sessionStorage.getItem('xm_assistant_v1:7')).toBeNull();
        await send('Comment porter plainte ?');

        expect(sessionStorage.length).toBe(0);
        expect(bubbles()).toHaveLength(2);
        expect(document.querySelector('[data-xm-history]')?.textContent).toContain('Mode discret');
    });

    it('efface la conversation, aussi côté serveur quand elle est enregistrée', async () => {
        const { calls } = fakeFetch(({ method }) => (method === 'DELETE' ? new Response(null, { status: 204 }) : answer({ conversation_id: 41 })));
        await mount();
        await fireEvent.click(screen.getByRole('switch', { name: /Garder cette conversation/ }));
        await send('Comment porter plainte ?');

        await fireEvent.click(document.querySelector('[data-xm-clear]') as HTMLElement);
        await flushPromises();

        expect(calls.at(-1)).toMatchObject({ url: '/api/v1/assistant/conversations/41', method: 'DELETE' });
        expect(bubbles()).toHaveLength(1); // accueil seul
        expect(sessionStorage.getItem('xm_assistant_v1:7')).toBeNull();
    });

    it('envoie « Parler à un humain » avec une clé d’idempotence et annonce le code de suivi', async () => {
        const { calls } = fakeFetch(() => json({ data: { id: '01J', tracking_code: 'BES-2026-0042', tracking_code_display: 'BES-2026-0042', routed: { role: 'Point focal' } } }, 201));
        await mount();
        await send('il va me tuer');

        const human = [...document.querySelectorAll('[data-kind="crisis"] button')].find((b) => b.textContent?.trim() === 'Parler à un humain') as HTMLElement;
        await fireEvent.click(human);
        await waitFor(() => expect(document.querySelector('#modalEscalation')).not.toBeNull());

        await fireEvent.submit(document.querySelector('#modalEscalation form') as HTMLFormElement);
        expect(document.querySelector('#modalEscalation')?.textContent).toContain('Indique un téléphone ou un e-mail');
        expect(calls).toHaveLength(0);

        await fireEvent.update(document.querySelector('#f-contact') as HTMLInputElement, '+229 01 97 38 88 47');
        await fireEvent.submit(document.querySelector('#modalEscalation form') as HTMLFormElement);
        await flushPromises();

        expect(calls[0]).toMatchObject({
            url: '/api/v1/assistant/escalate',
            method: 'POST',
            body: { contact: '+229 01 97 38 88 47', preferred_channel: null, message: null, anonymous: true, conversation_id: null },
        });
        expect(calls[0].headers['Idempotency-Key']).toMatch(/^[0-9a-f-]{36}$/);
        await waitFor(() => expect(bubbles().at(-1)?.[1]).toContain('BES-2026-0042'));
    });

    it('attend l’enregistrement de la crise avant la question suivante : une seule conversation enregistrée', async () => {
        const crisis = deferred();
        const { calls } = fakeFetch(({ body }) => ((body as { question: string }).question === 'il me frappe' ? crisis.promise : answer({ conversation_id: 41 })));
        await mount();
        await keepConversation();

        await send('il me frappe');
        await send('Comment porter plainte ?');
        // La question attend la réponse de l'enregistrement de la crise.
        expect(calls).toHaveLength(1);

        crisis.resolve(answer({ kind: 'crisis', conversation_id: 41 }) as Response);
        await flushPromises();
        await flushPromises();

        expect(calls.map((call) => call.body)).toEqual([
            { question: 'il me frappe', persist: true },
            { question: 'Comment porter plainte ?', persist: true, conversation_id: 41 },
        ]);
        expect(bubbles().at(-1)?.[1]).toContain('Rends-toi au commissariat.');
    });

    it('efface l’écran tout de suite pendant l’envoi, puis la conversation créée par la réponse tardive', async () => {
        const crisis = deferred();
        const { calls } = fakeFetch(({ method }) => (method === 'DELETE' ? new Response(null, { status: 204 }) : crisis.promise));
        await mount();
        await keepConversation();
        await send('il me frappe');

        await fireEvent.click(document.querySelector('[data-xm-clear]') as HTMLElement);
        await flushPromises();
        expect(bubbles()).toHaveLength(1); // accueil seul, sans attendre le serveur
        expect(calls).toHaveLength(1);

        crisis.resolve(answer({ kind: 'crisis', conversation_id: 41 }) as Response);
        await flushPromises();
        await flushPromises();

        expect(calls.at(-1)).toMatchObject({ url: '/api/v1/assistant/conversations/41', method: 'DELETE' });
        expect(bubbles()).toHaveLength(1);
        expect(sessionStorage.getItem('xm_assistant_v1:7')).toBeNull();
        expect(document.querySelector('[data-xm-history]')?.textContent).toContain('La conversation est gardée uniquement dans cet onglet.');

        // La suite repart d'une nouvelle conversation.
        await send('Comment porter plainte ?');
        expect(calls.at(-1)?.body).toEqual({ question: 'Comment porter plainte ?', persist: true });
    });

    it('dit quand l’échange de crise n’a pas pu être enregistré', async () => {
        fakeFetch(() => json({ message: 'Erreur' }, 500));
        await mount();
        await keepConversation();

        await send('il va me tuer');
        await flushPromises();

        expect(document.querySelector('[data-kind="crisis"]')).not.toBeNull();
        expect(bubbles().at(-1)?.[1]).toContain('Ce message n’a pas pu être enregistré');
    });

    it('affiche la réponse de crise même pendant une réponse en cours', async () => {
        const slow = deferred();
        fakeFetch(() => slow.promise);
        await mount();

        await send('Comment porter plainte ?');
        expect(document.querySelector('[data-xm-typing]')).not.toBeNull();
        await fireEvent.update(screen.getByLabelText('Ton message'), 'il me frappe');
        expect((document.querySelector('.card-footer button[type="submit"]') as HTMLButtonElement).disabled).toBe(false);
        await send('il me frappe');

        expect(document.querySelector('[data-kind="crisis"]')?.textContent).toContain('117');
        slow.resolve(answer({}) as Response);
        await flushPromises();
    });

    it('ne joint la conversation enregistrée qu’à une demande qui transmet le pseudonyme, et annonce l’envoi une fois', async () => {
        const { calls } = fakeFetch(({ url }) => (url.endsWith('/escalate')
            ? json({ data: { id: '01J', tracking_code: 'BES-2026-0042', tracking_code_display: 'BES-2026-0042' } }, 201)
            : answer({ conversation_id: 41 })));
        await mount();
        await keepConversation();
        await send('Comment porter plainte ?');

        await fireEvent.click(screen.getByRole('button', { name: 'Demander à être recontacté·e' }));
        await waitFor(() => expect(document.querySelector('#modalEscalation')).not.toBeNull());
        expect(document.querySelector('[data-xm-conversation-note]')?.textContent).toContain('transmets ton pseudonyme');
        expect(document.querySelector('#f-contact')?.hasAttribute('autocomplete')).toBe(false);

        await fireEvent.update(document.querySelector('#f-contact') as HTMLInputElement, 'moi@example.org');
        await fireEvent.submit(document.querySelector('#modalEscalation form') as HTMLFormElement);
        await flushPromises();
        expect(calls.at(-1)?.body).toMatchObject({ anonymous: true, conversation_id: null });
        await waitFor(() => expect(bubbles().at(-1)?.[1]).toContain('BES-2026-0042'));
        expect(useUiStore().toasts.some((toast) => toast.message.includes('BES-2026-0042'))).toBe(false);
    });

    it('envoie la conversation avec le pseudonyme transmis', async () => {
        const { calls } = fakeFetch(({ url }) => (url.endsWith('/escalate')
            ? json({ data: { id: '01J', tracking_code: 'BES-2026-0043' } }, 201)
            : answer({ conversation_id: 41 })));
        await mount();
        await keepConversation();
        await send('Comment porter plainte ?');

        await fireEvent.click(screen.getByRole('button', { name: 'Demander à être recontacté·e' }));
        await waitFor(() => expect(document.querySelector('#modalEscalation')).not.toBeNull());
        await fireEvent.click(document.querySelector('#f-share_pseudo') as HTMLInputElement);
        expect(document.querySelector('[data-xm-conversation-note]')?.textContent).toContain('pourra être relue');
        await fireEvent.update(document.querySelector('#f-contact') as HTMLInputElement, '+229 01 97 38 88 47');
        await fireEvent.submit(document.querySelector('#modalEscalation form') as HTMLFormElement);
        await flushPromises();

        expect(calls.at(-1)?.body).toMatchObject({ anonymous: false, conversation_id: 41 });
    });

    it('propose le numéro d’urgence et l’annuaire quand la demande échoue (quota 429)', async () => {
        fakeFetch(() => json({ message: 'Trop de demandes. Réessaie plus tard.', code: 'too_many_requests' }, 429));
        await mount();

        await fireEvent.click(screen.getByRole('button', { name: 'Demander à être recontacté·e' }));
        await waitFor(() => expect(document.querySelector('#modalEscalation')).not.toBeNull());
        await fireEvent.update(document.querySelector('#f-contact') as HTMLInputElement, '+229 01 97 38 88 47');
        await fireEvent.submit(document.querySelector('#modalEscalation form') as HTMLFormElement);
        await flushPromises();

        const failure = document.querySelector('[data-xm-escalation-failure]') as HTMLElement;
        expect(failure.textContent).toContain('En danger');
        expect(failure.querySelector('a[href="tel:117"]')?.textContent).toContain('Appeler le 117');
        expect(failure.querySelector('a[href="/annuaire"]')).not.toBeNull();
    });
});
