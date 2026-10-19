<script setup lang="ts">
// Modale « Créer un événement » (lot evenements, P8-04 ; prototype partials/modals.php l.166-190), chargée à la demande
// par /evenements et par la pastille « Événement » du fil (P7-03) — aucun import statique depuis app.ts : titre (3–200,
// obligatoire), date et heure (15 h par défaut, comme le prototype), heure de fin facultative, ville ou zone (visible de
// toute la communauté), adresse exacte (réservée aux participant·es), en ligne + lien https (réservé aussi), catégorie,
// groupe (membres prévenu·es), description, « Événement public » (modération seulement, Q35 a). Vérifié avant l'envoi
// avec les bornes du serveur ; `POST events` ; 422 → erreurs ramenées aux champs, résumé focalisé ; succès → `created`.
// Props : options (EventCreateOptions), group (ULID présélectionné, facultatif). Émet `created`, `hidden`.
import { computed, nextTick, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { eventsApi } from '@/events/api';
import type { EventCreateOptions, EventItem } from '@/events/types';
import XmAlert from '@/components/XmAlert.vue';
import XmErrorSummary from '@/components/XmErrorSummary.vue';
import XmField from '@/components/XmField.vue';
import XmModal from '@/components/XmModal.vue';

const props = withDefaults(defineProps<{ options: EventCreateOptions; group?: string | null }>(), { group: null });
const emit = defineEmits<{ created: [event: EventItem]; hidden: [] }>();

/** Champs de l'API → champs de la modale (identifiants #f-…, liens du résumé d'erreurs). */
const FIELD_OF: Record<string, string> = { starts_at: 'date', ends_at: 'end_time', category_id: 'category', group: 'group_id' };

const { t } = useI18n();
const api = eventsApi(useApi());
const modal = ref<InstanceType<typeof XmModal> | null>(null);
const form = reactive({
    title: '',
    date: '',
    time: props.options.default_time || '15:00',
    end_time: '',
    place: '',
    address: '',
    is_online: false,
    online_url: '',
    category: '',
    group_id: props.group && props.options.groups.some((option) => option.value === props.group) ? props.group : '',
    description: '',
    is_public: false,
});
const errors = ref<Record<string, string>>({});
const failure = ref('');
const busy = ref(false);

// Clés non numériques (`c<id>`) : un objet JS range les clés entières avant les autres, l'ordre serait perdu.
const categories = computed(() => ({ '': t('community.events.create.category_none'), ...Object.fromEntries(props.options.categories.map((option) => [`c${option.value}`, option.label])) }));
const groups = computed(() => ({ '': t('community.events.create.group_none'), ...Object.fromEntries(props.options.groups.map((option) => [option.value, option.label])) }));
const dirty = computed(() => form.title.trim() !== '' || form.description.trim() !== '' || form.address.trim() !== '' || form.place.trim() !== '');
// Date locale (et non UTC : au Bénin, entre 0 h et 1 h, toISOString donnerait la veille).
const now = new Date();
const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

const startsAt = () => (form.date ? `${form.date}T${form.time || '00:00'}` : '');

function validate(): Record<string, string> {
    const found: Record<string, string> = {};
    const { limits } = props.options;
    const title = form.title.trim();
    if (title.length < limits.title_min) found.title = t('community.events.create.title_min', { min: limits.title_min });
    else if (title.length > limits.title_max) found.title = t('feed.create.text_max', { max: limits.title_max });
    if (!form.date || Number.isNaN(Date.parse(startsAt())) || Date.parse(startsAt()) <= Date.now()) found.date = t('community.events.create.starts_future');
    if (form.end_time && form.date && form.end_time <= (form.time || '00:00')) found.end_time = t('community.events.create.ends_after');
    if (form.place.trim().length > limits.place_max) found.place = t('feed.create.text_max', { max: limits.place_max });
    if (form.address.trim().length > limits.address_max) found.address = t('feed.create.text_max', { max: limits.address_max });
    if (form.is_online && form.online_url.trim() !== '' && !/^https:\/\/\S+$/i.test(form.online_url.trim())) found.online_url = t('community.events.create.online_url_https');
    if (form.is_online && form.online_url.trim() === '' && form.place.trim() === '') found.online_url = t('community.events.create.online_url_required');
    if (form.description.trim().length > limits.description_max) found.description = t('feed.create.text_max', { max: limits.description_max });
    return found;
}

async function focusError() {
    await nextTick();
    document.querySelector<HTMLElement>('#modalCreateEvent .xm-error-summary, #modalCreateEvent [aria-invalid="true"]')?.focus();
}

const optional = (value: string) => (value.trim() === '' ? null : value.trim());

async function onSubmit() {
    if (busy.value) return;
    failure.value = '';
    errors.value = validate();
    if (Object.keys(errors.value).length > 0) {
        await focusError();
        return;
    }
    busy.value = true;
    try {
        const event = await api.create({
            title: form.title.trim(),
            starts_at: startsAt(),
            ends_at: form.end_time ? `${form.date}T${form.end_time}` : null,
            place: optional(form.place),
            address: optional(form.address),
            is_online: form.is_online,
            online_url: form.is_online ? optional(form.online_url) : null,
            category_id: form.category === '' ? null : Number(form.category.slice(1)),
            description: optional(form.description),
            group: form.group_id === '' ? null : form.group_id,
            is_public: props.options.can_publish_public && form.is_public,
        });
        emit('created', event);
        modal.value?.close();
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 422) {
            errors.value = Object.fromEntries(Object.entries(fieldErrors(cause)).map(([field, message]) => [FIELD_OF[field] ?? field, message]));
            if (Object.keys(errors.value).length === 0) failure.value = cause.message;
            await focusError();
        } else {
            failure.value = cause instanceof ApiError ? cause.message : t('microcopy.error.generic');
        }
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <XmModal ref="modal" id="modalCreateEvent" :title="t('community.events.create.title')" type="form" form :open="true" :action="t('community.events.create.submit')" :busy="busy" :dirty="dirty" @submit="onSubmit" @hidden="emit('hidden')">
        <XmErrorSummary :messages="errors" :title="t('feed.create.errors_title')" />
        <XmAlert v-if="failure" type="danger" class="small">{{ failure }}</XmAlert>
        <XmField v-model="form.title" name="title" :label="t('community.events.create.name')" :placeholder="t('community.events.create.name_placeholder')" :maxlength="options.limits.title_max" :error="errors.title" required :minlength="options.limits.title_min" />
        <div class="row g-3">
            <div class="col-sm-6"><XmField v-model="form.date" name="date" type="date" :label="t('community.events.create.date')" :error="errors.date" required :min="today" /></div>
            <div class="col-6 col-sm-3"><XmField v-model="form.time" name="time" type="time" :label="t('community.events.create.time')" :error="errors.time" required /></div>
            <div class="col-6 col-sm-3"><XmField v-model="form.end_time" name="end_time" type="time" optional :label="t('community.events.create.end_time')" :error="errors.end_time" /></div>
        </div>
        <XmField v-model="form.place" name="place" optional :label="t('community.events.create.place')" :placeholder="t('community.events.create.place_placeholder')" :hint="t('community.events.create.place_help')" :maxlength="options.limits.place_max" :error="errors.place" />
        <XmField v-model="form.address" name="address" optional :label="t('community.events.create.address')" :placeholder="t('community.events.create.address_placeholder')" :hint="t('community.events.create.address_help')" :maxlength="options.limits.address_max" :error="errors.address" autocomplete="off" />
        <XmField v-model="form.is_online" name="is_online" type="switch" :label="t('community.events.create.is_online')" :error="errors.is_online" />
        <XmField v-if="form.is_online" v-model="form.online_url" name="online_url" type="url" inputmode="url" :label="t('community.events.create.online_url')" :placeholder="t('community.events.create.online_url_placeholder')" :hint="t('community.events.create.online_url_help')" :maxlength="500" :error="errors.online_url" autocomplete="off" />
        <div class="row g-3">
            <div v-if="options.categories.length" class="col-sm-6"><XmField v-model="form.category" name="category" type="select" :label="t('community.events.create.category')" :options="categories" :error="errors.category" /></div>
            <div v-if="options.groups.length" class="col-sm-6"><XmField v-model="form.group_id" name="group_id" type="select" :label="t('community.events.create.group')" :options="groups" :hint="t('community.events.create.group_help')" :error="errors.group_id" /></div>
        </div>
        <XmField v-model="form.description" name="description" type="textarea" optional :rows="3" :label="t('community.events.create.description')" :maxlength="options.limits.description_max" :error="errors.description" />
        <XmField v-if="options.can_publish_public" v-model="form.is_public" name="is_public" type="switch" :label="t('community.events.create.is_public')" :hint="t('community.events.create.is_public_help')" :error="errors.is_public" />
    </XmModal>
</template>
