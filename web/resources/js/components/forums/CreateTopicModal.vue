<script setup lang="ts">
// Modale « Nouveau sujet » (lot forums, P8-05 ; prototype forums.php l.95-115), chargée à la demande par la page
// /forums (aucun import statique depuis app.ts) : forum (forum filtré présélectionné, sinon le premier), titre (5–140),
// message (10–5000), interrupteur « Publier de façon anonyme » (aide : « Membre anonyme » s'affichera). Bornes vérifiées
// avant l'envoi (aucun appel si elles manquent) ; envoi `POST forums/topics` ; 422 → erreurs par champ, résumé focalisé ;
// succès → émet `created` (la page ouvre le sujet).
// Props : options (create de ForumsPageController::index), forum (clé filtrée). Émet `created`, `hidden`.
import { computed, nextTick, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { length } from '@/feed/composer';
import { forumsApi } from '@/forums/api';
import type { ForumTopic, TopicCreateOptions } from '@/forums/types';
import XmAlert from '@/components/XmAlert.vue';
import XmErrorSummary from '@/components/XmErrorSummary.vue';
import XmField from '@/components/XmField.vue';
import XmModal from '@/components/XmModal.vue';

const props = withDefaults(defineProps<{ options: TopicCreateOptions; forum?: string | null }>(), { forum: null });
const emit = defineEmits<{ created: [topic: ForumTopic]; hidden: [] }>();

const { t } = useI18n();
const api = forumsApi(useApi());
const modal = ref<InstanceType<typeof XmModal> | null>(null);
const initialForum = props.options.forums.some((option) => option.value === props.forum) ? props.forum : props.options.forums[0]?.value;
const form = reactive({ forum: initialForum ?? '', title: '', body: '', anonymous: false });
const errors = ref<Record<string, string>>({});
const failure = ref('');
const busy = ref(false);

const forums = computed(() => Object.fromEntries(props.options.forums.map((option) => [option.value, option.label])));
const dirty = computed(() => form.title.trim() !== '' || form.body.trim() !== '');

function validate(): Record<string, string> {
    const { limits } = props.options;
    const found: Record<string, string> = {};
    const title = form.title.trim();
    const body = form.body.trim();
    if (!form.forum) found.forum = t('community.forums.create.forum_required');
    if (length(title) < limits.title_min) found.title = t('community.forums.create.title_min', { min: limits.title_min });
    else if (length(title) > limits.title_max) found.title = t('feed.create.text_max', { max: limits.title_max });
    if (length(body) < limits.body_min) found.body = t('community.forums.create.body_min', { min: limits.body_min });
    else if (length(body) > limits.body_max) found.body = t('feed.create.text_max', { max: limits.body_max });
    return found;
}

async function focusError() {
    await nextTick();
    document.querySelector<HTMLElement>('#modalTopic .xm-error-summary, #modalTopic [aria-invalid="true"]')?.focus();
}

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
        const topic = await api.createTopic({ forum: form.forum, title: form.title.trim(), body: form.body.trim(), is_anonymous: form.anonymous });
        emit('created', topic);
        modal.value?.close();
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 422) {
            errors.value = fieldErrors(cause);
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
    <XmModal ref="modal" id="modalTopic" :title="t('community.forums.create.title')" type="form" form :open="true" :action="t('community.forums.create.submit')" :busy="busy" :dirty="dirty" @submit="onSubmit" @hidden="emit('hidden')">
        <XmErrorSummary :messages="errors" :title="t('feed.create.errors_title')" />
        <XmAlert v-if="failure" type="danger" class="small">{{ failure }}</XmAlert>
        <XmField v-model="form.forum" name="forum" type="select" :label="t('community.forums.create.forum')" :options="forums" :error="errors.forum" />
        <XmField v-model="form.title" name="title" :label="t('community.forums.create.name')" :placeholder="t('community.forums.create.name_placeholder')" :maxlength="options.limits.title_max" :minlength="options.limits.title_min" :error="errors.title" required />
        <XmField v-model="form.body" name="body" type="textarea" :rows="5" :label="t('community.forums.create.body')" :maxlength="options.limits.body_max" :minlength="options.limits.body_min" :error="errors.body" required />
        <XmField v-model="form.anonymous" name="is_anonymous" type="switch" :label="t('community.forums.create.anonymous')" :hint="t('community.forums.create.anonymous_help')" />
    </XmModal>
</template>
