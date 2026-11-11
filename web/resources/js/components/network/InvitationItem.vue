<script setup lang="ts">
// Ligne d'une invitation (lot qui-suivre, P8-06 ; prototype who-to-follow.php l.118-138) : avatar, pseudonyme, « Rôle ·
// Zone · il y a 2 h » (reçue) ou « Rôle · envoyée il y a 2 h » (envoyée, sans la zone comme le prototype), puis
// « Accepter » / « Refuser » (reçue) ou « Annuler » (envoyée). Émet le geste ; l'écran appelle l'API.
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { timeAgo } from '@/feed/format';
import type { Invitation } from '@/network/types';
import XmAvatar from '@/components/XmAvatar.vue';

const props = withDefaults(defineProps<{ invitation: Invitation; busy?: string[] }>(), { busy: () => [] });
const emit = defineEmits<{ accept: [invitation: Invitation]; refuse: [invitation: Invitation]; cancel: [invitation: Invitation] }>();

const { t } = useI18n();
const name = computed(() => props.invitation.person?.pseudo ?? t('community.members.member'));
const sub = computed(() => {
    const person = props.invitation.person;
    const ago = timeAgo(props.invitation.created_at, t);
    if (props.invitation.direction === 'sent') return [person?.role_label, ago ? t('community.follow.sent_ago', { ago }) : null].filter(Boolean).join(' · ');
    return [person?.role_label, person?.zone?.name, ago].filter(Boolean).join(' · ');
});
const pending = (action: string) => props.busy.includes(`${action}:${props.invitation.id}`);
const locked = computed(() => ['accept', 'refuse', 'cancel'].some(pending));
</script>

<template>
    <div class="list-group-item d-flex flex-wrap gap-3 align-items-center py-3" :data-xm-invitation="invitation.id">
        <div class="hstack gap-3 flex-grow-1 overflow-hidden">
            <XmAvatar :name="name" :src="invitation.person?.avatar_url" />
            <div class="overflow-hidden">
                <div class="h6 mb-0">
                    <Link v-if="invitation.person?.profile_url" :href="invitation.person.profile_url">{{ name }}</Link>
                    <template v-else>{{ name }}</template>
                </div>
                <p class="small text-secondary mb-0 text-truncate">{{ sub }}</p>
            </div>
        </div>
        <div v-if="invitation.direction === 'received'" class="d-flex gap-2">
            <button v-if="invitation.can.accept" class="btn btn-sm btn-primary" type="button" :disabled="locked" :aria-busy="pending('accept') ? 'true' : undefined" :aria-label="t('community.follow.actions.accept_named', { name })" @click="emit('accept', invitation)">{{ t('community.follow.actions.accept') }}</button>
            <button v-if="invitation.can.refuse" class="btn btn-sm btn-danger-soft" type="button" :disabled="locked" :aria-busy="pending('refuse') ? 'true' : undefined" :aria-label="t('community.follow.actions.refuse_named', { name })" @click="emit('refuse', invitation)">{{ t('community.follow.actions.refuse') }}</button>
        </div>
        <button v-else-if="invitation.can.cancel" class="btn btn-sm btn-light" type="button" :disabled="locked" :aria-busy="pending('cancel') ? 'true' : undefined" :aria-label="t('community.follow.actions.cancel_named', { name })" @click="emit('cancel', invitation)">{{ t('community.follow.actions.cancel') }}</button>
    </div>
</template>
