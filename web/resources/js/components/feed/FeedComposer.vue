<script setup lang="ts">
// Carte « Créer une publication » du fil (lot fil-ecrans, P7-03 ; prototype fil.php l.44-55) : avatar, invite
// « Partage tes pensées, {pseudo}… » et pastilles Photo · Événement · Sondage · Violences · Signaler. Ouvre les modales
// (chargées à la demande par la page : émet `open` avec 'post' ou 'poll', et 'photo' pour mettre le champ photo en
// avant) ; « Événement » reste désactivée avec sa mention tant que la modale d'événement (P8-04) n'est pas livrée ;
// Violences et Signaler sont des liens vers les pages publiques. Préchargement de la modale visée au survol / focus
// (`warm` avec 'post' ou 'poll').
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { LINKS, hrefOf } from '@/app/memberNav';
import { useSessionStore } from '@/stores/session';
import type { ComposerOptions } from '@/feed/types';
import XmAvatar from '@/components/XmAvatar.vue';

defineProps<{ options: ComposerOptions }>();
const emit = defineEmits<{ open: [kind: 'post' | 'poll', focus: 'photo' | null]; warm: [kind: 'post' | 'poll'] }>();

const { t } = useI18n();
const session = useSessionStore();
const me = computed(() => session.user);
const prompt = computed(() => (me.value?.pseudo ? t('feed.compose.prompt', { name: me.value.pseudo }) : t('feed.compose.prompt_anonymous')));
</script>

<template>
    <section class="card card-body" :aria-label="t('feed.compose.label')">
        <div class="d-flex mb-3">
            <Link :href="hrefOf(LINKS.profil)" tabindex="-1" aria-hidden="true"><XmAvatar class="me-2" size="xs" :name="me?.pseudo ?? ''" :src="me?.avatar_url" /></Link>
            <button class="form-control text-start text-secondary border-0 bg-light" type="button" aria-haspopup="dialog" @click="emit('open', 'post', null)" @pointerenter="emit('warm', 'post')" @focus="emit('warm', 'post')">{{ prompt }}</button>
        </div>
        <ul class="nav nav-pills nav-stack small fw-normal">
            <li class="nav-item"><button class="nav-link bg-light py-1 px-2 mb-0" type="button" aria-haspopup="dialog" @click="emit('open', 'post', 'photo')" @pointerenter="emit('warm', 'post')" @focus="emit('warm', 'post')"><i class="bi bi-image-fill text-success pe-2" aria-hidden="true"></i>{{ t('feed.compose.photo') }}</button></li>
            <li class="nav-item">
                <button v-if="options.events_available" class="nav-link bg-light py-1 px-2 mb-0" type="button" aria-haspopup="dialog"><i class="bi bi-calendar2-event-fill text-danger pe-2" aria-hidden="true"></i>{{ t('feed.compose.event') }}</button>
                <button v-else class="nav-link bg-light py-1 px-2 mb-0" type="button" aria-disabled="true" :title="t('feed.compose.event_unavailable')" aria-describedby="feedEventSoon"><i class="bi bi-calendar2-event-fill text-danger pe-2" aria-hidden="true"></i>{{ t('feed.compose.event_soon') }}</button>
                <span id="feedEventSoon" class="visually-hidden">{{ t('feed.compose.event_unavailable') }}</span>
            </li>
            <li v-if="options.can_poll" class="nav-item"><button class="nav-link bg-light py-1 px-2 mb-0" type="button" aria-haspopup="dialog" @click="emit('open', 'poll', null)" @pointerenter="emit('warm', 'poll')" @focus="emit('warm', 'poll')"><i class="bi bi-bar-chart-fill text-info pe-2" aria-hidden="true"></i>{{ t('feed.compose.poll') }}</button></li>
            <li class="nav-item"><a class="nav-link bg-light py-1 px-2 mb-0" :href="hrefOf(LINKS.violences)"><i class="bi bi-shield-exclamation text-warning pe-2" aria-hidden="true"></i>{{ t('feed.compose.violences') }}</a></li>
            <li class="nav-item"><a class="nav-link bg-light py-1 px-2 mb-0" :href="hrefOf(LINKS.signaler)"><i class="bi bi-megaphone-fill text-primary pe-2" aria-hidden="true"></i>{{ t('feed.compose.report') }}</a></li>
        </ul>
    </section>
</template>
