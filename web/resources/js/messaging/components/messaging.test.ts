// @vitest-environment jsdom
// Écran de messagerie (P8-01, prototype messaging.php) : liste (aperçu masqué en mode discret, non lus, « Chiffrés »
// sans promesse « de bout en bout », Q32 b), conversation (bulles, « Vu », horloge grisée d'un message en attente,
// « non envoyé » avec Réessayer, blocage), zone d'écriture (Entrée envoie, Maj+Entrée non).
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent } from '@testing-library/vue';
import { renderXm } from '@/test/helpers';
import ChatPane from './ChatPane.vue';
import ConversationList from './ConversationList.vue';
import type { ChatMessage, Conversation } from '../types';

const conversation = (extra: Partial<Conversation> = {}): Conversation => ({
    id: '01JABCDEFGHJKMNPQRSTVWXYZ0', kind: 'private', name: 'Chantal', title: null, avatar_url: null, other: null, participants: [],
    participants_count: 2, last_message: { id: 3, preview: 'Rendez-vous samedi', preview_hidden: false, mine: false, author: null, deleted: false, attachment: false, at: '2026-10-18T09:00:00Z' },
    last_message_at: '2026-10-18T09:00:00Z', unread_count: 2, muted: false, muted_until: null, role: 'member', can_send: true,
    can_manage: false, blocked_by_me: false, others_count: 1, created_at: '2026-10-01T10:00:00Z', ...extra,
});

const message = (id: number | null, extra: Partial<ChatMessage> = {}): ChatMessage => ({
    id, client_uuid: `u-${id}`, body: `Message ${id}`, deleted: false, mine: false, author: { id: 2, pseudo: 'Chantal', avatar_url: null },
    attachment: null, status: null, created_at: new Date().toISOString(), ...extra,
});

afterEach(() => cleanup());

describe('ConversationList', () => {
    it('affiche nom, aperçu, non lus et la mention « Chiffrés » sans « bout en bout »', () => {
        const { getByText, container } = renderXm(ConversationList, { props: { conversations: [conversation()] } });

        expect(getByText('Chantal')).toBeTruthy();
        expect(getByText('Rendez-vous samedi')).toBeTruthy();
        expect(container.querySelector('.badge')?.textContent).toContain('2');
        expect(container.textContent).toContain('Chiffrés');
        expect(container.textContent?.toLowerCase()).not.toContain('bout en bout');
    });

    it('masque l’aperçu en mode discret et marque la conversation ouverte', () => {
        const hidden = conversation({ last_message: { ...conversation().last_message!, preview: null, preview_hidden: true } });
        const { getByText, container } = renderXm(ConversationList, { props: { conversations: [hidden], activeId: hidden.id } });

        expect(getByText('Aperçu masqué')).toBeTruthy();
        expect(container.textContent).not.toContain('Rendez-vous');
        expect(container.querySelector('[aria-current="true"]')).not.toBeNull();
    });

    it('préfixe l’aperçu par « Toi » ou l’autrice avec la clé lang preview_from (espace insécable)', () => {
        const last = conversation().last_message!;
        const { getByText } = renderXm(ConversationList, {
            props: { conversations: [conversation({ last_message: { ...last, mine: true, preview: 'Merci' } }), conversation({ id: '01JABCDEFGHJKMNPQRSTVWXYZ1', kind: 'group', last_message: { ...last, author: 'Awa', preview: 'Salut' } })] },
        });
        // getByText normalise les espaces : le texte brut garde l'espace insécable de lang/fr.
        expect(getByText('Toi : Merci').textContent).toBe('Toi\u00a0: Merci');
        expect(getByText('Awa : Salut').textContent).toBe('Awa\u00a0: Salut');
    });

    it('émet la sélection et la création', async () => {
        const { getByText, getByLabelText, emitted } = renderXm(ConversationList, { props: { conversations: [conversation()] } });

        await fireEvent.click(getByText('Chantal'));
        await fireEvent.click(getByLabelText('Nouvelle conversation'));

        expect(emitted().select[0]).toEqual(['01JABCDEFGHJKMNPQRSTVWXYZ0']);
        expect(emitted().create).toHaveLength(1);
    });
});

describe('ChatPane', () => {
    it('affiche les bulles, « Vu », l’horloge grisée d’un message en attente et « Réessayer » d’un message refusé', async () => {
        const { getByText, getAllByText, container, emitted } = renderXm(ChatPane, {
            props: {
                conversation: conversation(),
                messages: [
                    message(1),
                    message(2, { mine: true, status: 'seen', body: 'Merci' }),
                    message(null, { client_uuid: 'p-1', mine: true, state: 'pending', body: 'En route' }),
                    message(null, { client_uuid: 'p-2', mine: true, state: 'failed', error: 'Refusé', body: 'Raté' }),
                ],
            },
        });

        // Le fil n'est pas une région live (l'historique n'est jamais relu au chargement) : région dédiée, vide.
        expect(container.querySelector('[data-xm-messages]')?.hasAttribute('aria-live')).toBe(false);
        expect(container.querySelector('[data-xm-messages]')?.getAttribute('tabindex')).toBe('0');
        expect(container.querySelector('[data-xm-incoming]')?.textContent).toBe('');
        expect(getByText('Message 1').closest('.xm-bubble')?.classList.contains('them')).toBe(true);
        expect(getByText('Merci').closest('.xm-bubble')?.classList.contains('me')).toBe(true);
        expect(getByText('Vu')).toBeTruthy();
        expect(getByText('En route').closest('.xm-bubble')?.classList.contains('is-pending')).toBe(true);
        expect(container.querySelector('.is-pending .bi-clock')).not.toBeNull();
        expect(getAllByText('En attente d’envoi')).toHaveLength(1);
        expect(getByText('Refusé')).toBeTruthy();

        await fireEvent.click(getByText('Réessayer'));
        expect(emitted().retry[0]).toEqual(['p-2']);
    });

    it('envoie à Entrée, garde Maj+Entrée pour une nouvelle ligne, vide la zone après envoi', async () => {
        const { getByLabelText, emitted } = renderXm(ChatPane, { props: { conversation: conversation(), messages: [] } });
        const input = getByLabelText('Message') as HTMLTextAreaElement;

        await fireEvent.update(input, 'Bonjour');
        await fireEvent.keyDown(input, { key: 'Enter', shiftKey: true });
        expect(emitted().send).toBeUndefined();
        await fireEvent.keyDown(input, { key: 'Enter' });

        expect(emitted().send[0]).toEqual(['Bonjour', null]);
        expect(input.value).toBe('');
    });

    it('remplace la zone d’écriture par un avis neutre quand on ne peut plus écrire', () => {
        const blockedByOther = renderXm(ChatPane, { props: { conversation: conversation({ can_send: false }), messages: [] } });
        expect(blockedByOther.getByText('Cette conversation n’est plus disponible.')).toBeTruthy();
        expect(blockedByOther.queryByLabelText('Message')).toBeNull();
        blockedByOther.unmount();

        const blockedByMe = renderXm(ChatPane, { props: { conversation: conversation({ can_send: false, blocked_by_me: true }), messages: [] } });
        expect(blockedByMe.getByText(/Tu as bloqué cette personne/)).toBeTruthy();
    });

    it('annonce un message entrant dans la région live dédiée, jamais ses propres envois', async () => {
        const { container, rerender } = renderXm(ChatPane, { props: { conversation: conversation(), messages: [message(1)] } });
        const live = () => container.querySelector('[data-xm-incoming]')?.textContent;

        await rerender({ conversation: conversation(), messages: [message(1), message(2, { mine: true })] });
        expect(live()).toBe('');
        await rerender({ conversation: conversation(), messages: [message(1), message(2, { mine: true }), message(3)] });
        expect(live()).toBe('Nouveau message de Chantal');
    });

    it('ouvre le choix de la pièce jointe au clavier : vrai bouton focusable (WCAG 2.1.1)', async () => {
        const click = vi.spyOn(HTMLInputElement.prototype, 'click').mockImplementation(() => undefined);
        const { container } = renderXm(ChatPane, { props: { conversation: conversation(), messages: [] } });
        const button = container.querySelector('[data-xm-attach]') as HTMLButtonElement;

        expect(button.tagName).toBe('BUTTON');
        expect(button.getAttribute('type')).toBe('button');
        expect(button.textContent).toContain('Joindre un fichier');
        button.focus();
        expect(document.activeElement).toBe(button);
        await fireEvent.keyDown(button, { key: 'Enter' });
        await fireEvent.click(button); // Entrée / Espace sur un <button> déclenchent click
        expect(click).toHaveBeenCalledOnce();
        expect(container.querySelector('#chatFile')?.getAttribute('tabindex')).toBe('-1');
        click.mockRestore();
    });

    it('masque « Réessayer » quand la pièce jointe n’a pas survécu au rechargement', () => {
        const { queryByText, getByText } = renderXm(ChatPane, {
            props: { conversation: conversation(), messages: [message(null, { client_uuid: 'p-3', mine: true, state: 'failed', file_name: 'perdu.pdf', retryable: false })] },
        });
        expect(queryByText('Réessayer')).toBeNull();
        expect(getByText(/Pièce jointe perdue/)).toBeTruthy();
        expect(getByText('Retirer ce message')).toBeTruthy();
    });

    it('propose « Choisis une conversation » sans conversation ouverte', () => {
        const { getByText } = renderXm(ChatPane, { props: { conversation: null, messages: [] } });
        expect(getByText('Choisis une conversation pour l’afficher.')).toBeTruthy();
    });
});
