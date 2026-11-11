// Store Pinia `session` (P6-05) : compte connecté et permissions de l'interface, recopiés des props partagées Inertia à
// chaque visite (app.ts : router.on('navigate')). La décision d'accès reste côté serveur (policies) : `can()` ne sert
// qu'à afficher ou masquer un menu, un bouton.
import { defineStore } from 'pinia';
import type { SharedProps, SharedShell, SharedUser } from '@/app/types';

export const useSessionStore = defineStore('session', {
    state: () => ({
        user: null as SharedUser | null,
        abilities: {} as Record<string, boolean>,
        /** Gabarit membre (P6-03) : carte profil, SOS flottant, lien d'administration. */
        shell: null as SharedShell | null,
        neutralTitle: false,
        neutralLabel: '',
        bellPollSeconds: 30,
        outboxAccount: null as string | null,
        ttlDays: 7,
        sosHoldMs: 2000,
        sosCountdown: 5,
        locale: 'fr',
    }),
    getters: {
        authenticated: (state): boolean => state.user !== null,
        pseudo: (state): string => state.user?.pseudo ?? '',
    },
    actions: {
        hydrate(props: Partial<SharedProps>) {
            if (props.auth) this.user = props.auth.user ?? null;
            if (props.shell !== undefined) this.shell = props.shell ?? null;
            if (props.can) this.abilities = { ...props.can };
            if (props.lang?.locale) this.locale = props.lang.locale;
            if (props.ui) {
                this.neutralTitle = Boolean(props.ui.neutral_title);
                this.neutralLabel = props.ui.neutral_label ?? '';
                this.bellPollSeconds = Number(props.ui.bell_poll_seconds) || 30;
                this.outboxAccount = props.ui.outbox?.account ?? '';
                this.ttlDays = Number(props.ui.outbox?.ttl_days) || 7;
                this.sosHoldMs = Number(props.ui.sos?.hold_ms) || 2000;
                this.sosCountdown = Number(props.ui.sos?.countdown_seconds) || 5;
            }
        },
        can(ability: string): boolean {
            return this.abilities[ability] === true;
        },
        /** Titre d'onglet : « Notes » en mode neutre, sinon « Titre – Xomiton ». */
        documentTitle(title: string, appName = 'Xomiton'): string {
            if (this.neutralTitle) return this.neutralLabel || 'Notes';
            return title ? `${title} – ${appName}` : appName;
        },
    },
});
