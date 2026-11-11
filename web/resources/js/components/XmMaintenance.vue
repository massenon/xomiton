<script setup lang="ts">
// Écran plein « Maintenance » de l'espace membre (P13-08) : monté à la demande par app.ts (createMaintenanceScreen,
// app/shellBoot.ts) quand une visite Inertia ou un appel d'API reçoit un 503 `maintenance`, au lieu d'un toast ou de la
// modale d'erreur d'Inertia. Même contenu et même feuille que la page publique (P13-07 : resources/views/maintenance/,
// classes .xmm-*, logo et illustration SVG partagés), au tu (espace connecté). L'application reste en mémoire derrière
// l'écran (inerte) : la boîte d'envoi et les brouillons sont gardés. Données : réponse 503 (`maintenance_message`,
// `ends_at`, `overdue`, `timezone`) puis GET /api/v1/status (numéros d'urgence, fin à jour) toutes les 60 s ; à la
// réouverture, la page se recharge. Heures dans le fuseau du serveur (`timezone`, Bénin) : mêmes heures que la page
// publique, quel que soit le réglage de l'appareil. Reprise annoncée dépassée : « Bientôt » et phrase dédiée, sans
// compte à rebours ; zone aria-live aux seuils 60 / 30 / 15 / 5 min, au dépassement et à la réouverture.
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from '@/app/routes';
import type { MaintenancePayload } from '@/app/shellBoot';
import art from '../../views/maintenance/illustration.blade.php?raw';
import logo from '../../views/maintenance/logo.blade.php?raw';
import '../../views/maintenance/content.css';

interface StatusBody {
    data?: {
        maintenance?: { active?: boolean; message?: string | null; ends_at?: string | null; starts_at?: string | null; overdue?: boolean };
        emergency_numbers?: Array<{ label: string; number: string }>;
        timezone?: string | null;
        server_time?: string;
    };
}

const props = withDefaults(defineProps<{
    payload?: MaintenancePayload;
    statusUrl?: string;
    pollSeconds?: number;
    fetcher?: typeof fetch;
    reload?: () => void;
}>(), { payload: () => ({}), statusUrl: '/api/v1/status', pollSeconds: 60, fetcher: undefined, reload: undefined });

const { t, locale } = useI18n();
const NBSP = ' ';
/** Seuils d'annonce du temps restant (minutes), comme MaintenancePage::ANNOUNCE_MINUTES. */
const MARKS = [60, 30, 15, 5];
const stripComment = (raw: string) => raw.replace(/\{\{--[\s\S]*?--\}\}\s*/, '');
const illustration = stripComment(art);
const logoSvg = stripComment(logo);

const message = ref<string>(props.payload.maintenance_message || '');
const endsAt = ref<number>(Date.parse(props.payload.ends_at || ''));
const serverOverdue = ref<boolean>(props.payload.overdue === true);
const timezone = ref<string | undefined>(props.payload.timezone || undefined);
const numbers = ref<Array<{ label: string; number: string }>>([]);
const now = ref<number>(Date.now());
const reopened = ref(false);
const announce = ref('');
const title = ref<HTMLElement | null>(null);
let skew = 0;
let mark: number | null = null;
let saidOverdue = false;
let tickTimer: ReturnType<typeof setInterval> | undefined;
let pollTimer: ReturnType<typeof setTimeout> | undefined;

const urls = {
    sos: route('sos', undefined, '/sos'),
    report: route('signaler', undefined, '/signaler'),
    urgences: route('urgences', undefined, '/urgences'),
};

const intlLocale = computed(() => (locale.value === 'fr' ? 'fr-BJ' : locale.value));
const hasEnd = computed(() => !Number.isNaN(endsAt.value));
const overdue = computed(() => (hasEnd.value ? now.value >= endsAt.value : serverOverdue.value));
const pad = (n: number) => String(n).padStart(2, '0');

/** Parties d'une date dans le fuseau du serveur (repli : fuseau de l'appareil si le fuseau est inconnu). */
function parts(date: Date, options: Intl.DateTimeFormatOptions): Record<string, string> {
    const read = (zone?: string) => Object.fromEntries(new Intl.DateTimeFormat(intlLocale.value, { ...options, timeZone: zone })
        .formatToParts(date).map((part) => [part.type, part.value]));
    try {
        return read(timezone.value);
    } catch {
        return read(undefined);
    }
}
/** « 16 h 30 », « 9 h » (VOICE_AND_TONE §6), comme LocaleFormatter::time. */
const timeText = (date: Date) => {
    const p = parts(date, { hour: 'numeric', minute: '2-digit', hourCycle: 'h23' });
    const hour = String(Number(p.hour ?? '0'));
    return `${hour}${NBSP}h${p.minute && p.minute !== '00' ? NBSP + p.minute : ''}`;
};
/** « lundi 2 novembre 2026 », « dimanche 1er novembre 2026 », comme MaintenancePage::day. */
const dayText = (date: Date) => {
    const p = parts(date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    return `${p.weekday} ${p.day === '1' ? '1er' : p.day} ${p.month} ${p.year}`;
};
const endsTime = computed(() => (hasEnd.value && !overdue.value ? timeText(new Date(endsAt.value)) : t('microcopy.maintenance.soon')));
const endsDay = computed(() => (hasEnd.value && !overdue.value ? dayText(new Date(endsAt.value)) : ''));
const untilText = computed(() => {
    if (overdue.value) return t('microcopy.maintenance.overdue');
    return hasEnd.value
        ? t('microcopy.maintenance.until', { date: endsDay.value, time: timeText(new Date(endsAt.value)) })
        : t('microcopy.maintenance.until_unknown');
});
const remaining = computed(() => {
    if (!hasEnd.value) return '';
    const left = Math.max(0, Math.round((endsAt.value - now.value) / 1000));
    const h = Math.floor(left / 3600);
    return (h > 0 ? `${pad(h)}:` : '') + `${pad(Math.floor((left % 3600) / 60))}:${pad(left % 60)}`;
});
const shownMessage = computed(() => message.value || t('microcopy.maintenance.default_message'));
const telHref = (value: string) => {
    const digits = value.trim().replace(/(?!^\+)[^\d]/g, '');
    return /\d/.test(digits) ? `tel:${digits}` : '';
};
/** Seuil le plus proche au-dessus du temps restant (null au-delà de 60 min). */
const markFor = (minutes: number) => MARKS.filter((m) => minutes <= m).reduce<number | null>((best, m) => (best === null || m < best ? m : best), null);

const doReload = () => (props.reload ? props.reload() : window.location.reload());

function tick() {
    now.value = Date.now() + skew;
    if (overdue.value) {
        if (!saidOverdue) {
            saidOverdue = true;
            announce.value = t('microcopy.maintenance.overdue');
            if (hasEnd.value) void check();
        }
        return;
    }
    if (!hasEnd.value) return;
    const left = Math.max(0, Math.round((endsAt.value - now.value) / 1000));
    const current = markFor(Math.ceil(left / 60));
    // Seuil déjà franchi à l'ouverture : rien n'est annoncé avant le suivant (la phrase en clair suffit).
    if (mark === null) {
        mark = current ?? Number.POSITIVE_INFINITY;
        return;
    }
    if (current !== null && current < mark) {
        mark = current;
        announce.value = t('microcopy.maintenance.remaining_live', { minutes: current });
    }
}

async function check(): Promise<void> {
    if (reopened.value) return;
    try {
        const fetcher = props.fetcher ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));
        const response = await fetcher(props.statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'omit' });
        if (!response.ok) return;
        const body = (await response.json()) as StatusBody;
        const data = body.data;
        if (!data?.maintenance) return;
        if (Array.isArray(data.emergency_numbers)) numbers.value = data.emergency_numbers;
        if (data.timezone) timezone.value = data.timezone;
        const server = Date.parse(data.server_time || '');
        if (!Number.isNaN(server)) skew = server - Date.now();
        if (data.maintenance.active === false) {
            reopened.value = true;
            announce.value = t('microcopy.maintenance.reopened');
            setTimeout(doReload, 1500);
            return;
        }
        if (data.maintenance.message) message.value = data.maintenance.message;
        serverOverdue.value = data.maintenance.overdue === true;
        const next = Date.parse(data.maintenance.ends_at || '');
        if (!Number.isNaN(next) && next !== endsAt.value) {
            endsAt.value = next;
            mark = null;
            saidOverdue = false;
        }
    } catch {
        // Réseau coupé : l'écran reste affiché, nouvel essai au prochain passage.
    }
}

function schedule(delay: number) {
    pollTimer = setTimeout(async () => {
        await check();
        if (!reopened.value) schedule(props.pollSeconds * 1000);
    }, delay);
}

const onVisible = () => {
    if (!document.hidden) void check();
};

onMounted(async () => {
    tick();
    tickTimer = setInterval(tick, 1000);
    void check();
    schedule(Math.round(props.pollSeconds * 1000 * (0.5 + Math.random() * 0.5)));
    document.addEventListener('visibilitychange', onVisible);
    await nextTick();
    title.value?.focus();
});

onBeforeUnmount(() => {
    clearInterval(tickTimer);
    clearTimeout(pollTimer);
    document.removeEventListener('visibilitychange', onVisible);
});
</script>

<template>
    <div class="xmm xmm-js xm-maintenance-screen" :class="{ 'xmm-open': reopened, 'xmm-overdue': overdue }" role="dialog" aria-modal="true" aria-labelledby="xmm-title" data-xm-maintenance-screen>
        <svg class="xmm-sprite" aria-hidden="true" focusable="false">
            <symbol id="xmm-i-sos" viewBox="0 0 24 24"><path d="M8.2 2.5h7.6l5.7 5.7v7.6l-5.7 5.7H8.2l-5.7-5.7V8.2z" /><path d="M12 7.5v5.5M12 16.4v.1" /></symbol>
            <symbol id="xmm-i-flag" viewBox="0 0 24 24"><path d="M5.5 21V3.5M5.5 4h12l-2.5 4.5 2.5 4.5h-12" /></symbol>
            <symbol id="xmm-i-phone" viewBox="0 0 24 24"><path d="M6.8 3.2 9.4 3l1.8 4.3-2.3 1.5a12 12 0 0 0 6.3 6.3l1.5-2.3 4.3 1.8-.2 2.6a2.8 2.8 0 0 1-3 2.6A16 16 0 0 1 4.2 6.2a2.8 2.8 0 0 1 2.6-3z" /></symbol>
            <symbol id="xmm-i-heart" viewBox="0 0 24 24"><path d="M12 20s-7.5-4.6-7.5-10.2A4.2 4.2 0 0 1 12 7.2a4.2 4.2 0 0 1 7.5 2.6C19.5 15.4 12 20 12 20z" /></symbol>
            <symbol id="xmm-i-shield" viewBox="0 0 24 24"><path d="M12 3 5 5.8V11c0 4.4 3 8.2 7 9.5 4-1.3 7-5.1 7-9.5V5.8z" /><path d="m9 11.8 2.1 2.1L15.2 9.8" /></symbol>
            <symbol id="xmm-i-device" viewBox="0 0 24 24"><rect x="7" y="2.5" width="10" height="19" rx="2.6" /><path d="M11 18.2h2" /></symbol>
            <symbol id="xmm-i-refresh" viewBox="0 0 24 24"><path d="M19.5 10.5A7.7 7.7 0 0 0 6 6.6L4.5 8.2M4.5 4v4.2h4.2M4.5 13.5A7.7 7.7 0 0 0 18 17.4l1.5-1.6m0 4.2v-4.2h-4.2" /></symbol>
            <symbol id="xmm-i-lock" viewBox="0 0 24 24"><rect x="5" y="10.5" width="14" height="10" rx="2.4" /><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5" /></symbol>
            <symbol id="xmm-i-arrow" viewBox="0 0 24 24"><path d="M5 12h13.5M13.5 7l5 5-5 5" /></symbol>
        </svg>

        <aside class="xmm-help" aria-labelledby="xmm-help-title">
            <div class="xmm-wrap xmm-help__in">
                <div class="xmm-help__text">
                    <p id="xmm-help-title" class="xmm-help__title"><svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-heart" /></svg>{{ t('microcopy.maintenance.help_title') }}</p>
                    <p class="xmm-help__sub">{{ t('microcopy.maintenance.help_text') }}</p>
                </div>
                <nav class="xmm-help__actions" :aria-label="t('microcopy.maintenance.help_nav')">
                    <a class="xmm-btn xmm-btn--sos" :href="urls.sos"><svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-sos" /></svg>{{ t('microcopy.maintenance.sos') }}</a>
                    <a class="xmm-btn xmm-btn--report" :href="urls.report"><svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-flag" /></svg>{{ t('microcopy.maintenance.report') }}</a>
                    <a class="xmm-btn xmm-btn--help" :href="urls.urgences"><svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-phone" /></svg>{{ t('microcopy.maintenance.emergency') }}</a>
                </nav>
            </div>
        </aside>

        <div class="xmm-top xmm-wrap">
            <!-- Logo partagé avec la page publique (Blade, contenu statique du dépôt) : couleurs par jetons, clair et sombre. -->
            <!-- eslint-disable-next-line vue/no-v-html -->
            <span class="xmm-logo" v-html="logoSvg"></span>
        </div>

        <div class="xmm-wrap xmm-main">
            <section class="xmm-hero">
                <div class="xmm-hero__text">
                    <p class="xmm-eyebrow"><span class="xmm-pulse" aria-hidden="true"></span>{{ t('microcopy.maintenance.eyebrow') }}</p>
                    <h1 id="xmm-title" ref="title" class="xmm-title" tabindex="-1">{{ t('microcopy.maintenance.title_lead') }} <span class="xmm-mark">{{ t('microcopy.maintenance.title_mark') }}</span>.</h1>
                    <p class="xmm-lead">{{ shownMessage }}</p>
                    <p class="xmm-note"><svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-lock" /></svg><span>{{ t('microcopy.maintenance.session') }}</span></p>

                    <section class="xmm-card xmm-status">
                        <dl class="xmm-status__grid">
                            <div class="xmm-status__cell">
                                <dt>{{ t('microcopy.maintenance.ends') }}</dt>
                                <dd><strong class="xmm-status__big" data-xmm-ends>{{ endsTime }}</strong><span v-if="endsDay" class="xmm-status__sub" data-xmm-ends-day>{{ endsDay }}</span></dd>
                            </div>
                            <!-- Compte à rebours visuel : la phrase en clair et la zone aria-live portent déjà l'information. -->
                            <div v-if="hasEnd && !overdue" class="xmm-status__cell xmm-countdown" data-xmm-countdown aria-hidden="true">
                                <dt>{{ t('microcopy.maintenance.remaining') }}</dt>
                                <dd><strong class="xmm-status__big xmm-digits" data-xmm-remaining>{{ remaining }}</strong></dd>
                            </div>
                        </dl>
                        <p class="xmm-status__text"><span data-xmm-until>{{ untilText }}</span></p>
                        <div class="xmm-status__foot">
                            <p class="xmm-check"><span class="xmm-dot" aria-hidden="true"></span><span>{{ reopened ? t('microcopy.maintenance.reopened') : t('microcopy.maintenance.check') }}</span></p>
                            <button type="button" class="xmm-btn xmm-btn--ghost" @click="doReload"><svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-refresh" /></svg>{{ t('microcopy.maintenance.refresh') }}</button>
                        </div>
                        <p class="xmm-vh" aria-live="polite" data-xmm-announce>{{ announce }}</p>
                    </section>
                </div>
                <!-- Illustration partagée avec la page publique (Blade, contenu statique du dépôt). -->
                <!-- eslint-disable-next-line vue/no-v-html -->
                <figure class="xmm-hero__art" aria-hidden="true" v-html="illustration"></figure>
            </section>

            <div class="xmm-grid">
                <section class="xmm-card xmm-numbers" aria-labelledby="xmm-numbers-title">
                    <h2 id="xmm-numbers-title" class="xmm-h2">{{ t('microcopy.maintenance.numbers_title') }}</h2>
                    <p class="xmm-muted">{{ t('microcopy.maintenance.numbers_text') }}</p>
                    <ul v-if="numbers.length" class="xmm-numbers__list">
                        <li v-for="entry in numbers" :key="entry.number">
                            <!-- Nom accessible « Appeler Police secours 117 » : contient le texte visible (WCAG 2.5.3). -->
                            <a class="xmm-number" :href="telHref(entry.number)">
                                <!-- Espaces explicites (le compilateur Vue retire ceux du gabarit) : « Appeler Police secours 117 ». -->
                                <span class="xmm-vh">{{ `${t('microcopy.maintenance.call_prefix')} ` }}</span>
                                <span class="xmm-number__label">{{ entry.label }}</span>{{ ' ' }}<strong class="xmm-number__value">{{ entry.number }}</strong>
                                <span class="xmm-number__icon" aria-hidden="true"><svg class="xmm-i"><use href="#xmm-i-phone" /></svg></span>
                            </a>
                        </li>
                    </ul>
                    <a class="xmm-link" :href="urls.urgences">{{ t('microcopy.maintenance.all_contacts') }}<svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-arrow" /></svg></a>
                </section>
                <section class="xmm-card xmm-reassure" aria-labelledby="xmm-reassure-title">
                    <h2 id="xmm-reassure-title" class="xmm-h2">{{ t('microcopy.maintenance.reassure_title') }}</h2>
                    <ul class="xmm-reassure__list">
                        <li><span class="xmm-badge" aria-hidden="true"><svg class="xmm-i"><use href="#xmm-i-shield" /></svg></span><p><strong>{{ t('microcopy.maintenance.data_title') }}</strong> {{ t('microcopy.maintenance.data_text') }}</p></li>
                        <li><span class="xmm-badge" aria-hidden="true"><svg class="xmm-i"><use href="#xmm-i-device" /></svg></span><p><strong>{{ t('microcopy.maintenance.drafts_title') }}</strong> {{ t('microcopy.maintenance.drafts_text') }}</p></li>
                        <li><span class="xmm-badge" aria-hidden="true"><svg class="xmm-i"><use href="#xmm-i-heart" /></svg></span><p><strong>{{ t('microcopy.maintenance.crisis_title') }}</strong> {{ t('microcopy.maintenance.crisis_text') }}</p></li>
                    </ul>
                </section>
            </div>
        </div>
    </div>
</template>

<style>
/* Écran plein au-dessus de l'application (inerte derrière) et du bouton SOS flottant. */
.xm-maintenance-screen { position: fixed; inset: 0; z-index: 2000; overflow-y: auto; overscroll-behavior: contain; }
</style>
