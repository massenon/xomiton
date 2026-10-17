// Internationalisation de l'espace Vue : français par défaut, fon, yoruba et anglais prévus (NFR-11).
// Les messages sont injectés par Laravel (props partagées Inertia `lang`) pour n'avoir qu'une source : lang/fr/*.php.
import { createI18n } from 'vue-i18n';

export const i18n = createI18n({
    legacy: false,
    locale: document.documentElement.lang || 'fr',
    fallbackLocale: 'fr',
    messages: { fr: {} },
    missingWarn: false,
    fallbackWarn: false,
});

export function loadMessages(locale: string, messages: Record<string, unknown>): void {
    i18n.global.setLocaleMessage(locale, messages);
    i18n.global.locale.value = locale;
}
