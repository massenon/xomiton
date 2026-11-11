<script setup lang="ts">
// Carte d'une suggestion « Qui suivre » (lot qui-suivre, P8-06 ; prototype who-to-follow.php l.107-113) : avatar xl,
// pseudonyme (lien du profil quand il existe), « Rôle · Zone » (zone seulement si la personne l'affiche), « Envoyer une
// invitation » (émet `invite` ; une fois envoyée : « Invitation envoyée », désactivé) et « Suivre » / « Abonné·e »
// (émet `follow` ; l'état est porté par le libellé, sans aria-pressed qui l'annoncerait deux fois ; absent pour un
// profil réservé à ses connexions). Aucune donnée privée n'arrive jusqu'ici (NetworkPersonPayload).
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Suggestion } from '@/network/types';
import XmAvatar from '@/components/XmAvatar.vue';

const props = withDefaults(defineProps<{
    person: Suggestion;
    invited?: boolean;
    canInvite?: boolean;
    /** Gestes en cours sur la page (useNetworkActions). */
    busy?: string[];
}>(), { invited: false, canInvite: true, busy: () => [] });
const emit = defineEmits<{ invite: [person: Suggestion]; follow: [person: Suggestion] }>();

const { t } = useI18n();
const meta = computed(() => [props.person.role_label, props.person.zone?.name].filter(Boolean).join(' · '));
const inviting = computed(() => props.busy.includes(`invite:${props.person.id}`));
const following = computed(() => props.busy.includes(`follow:${props.person.id}`));
</script>

<template>
    <div class="col-sm-6 col-xl-4">
        <article class="card h-100" :aria-labelledby="`person-${person.id}`" :data-xm-person="person.id">
            <div class="card-body text-center">
                <XmAvatar class="mb-2" size="xl" :name="person.pseudo" :src="person.avatar_url" />
                <h2 :id="`person-${person.id}`" class="h6 mb-0">
                    <Link v-if="person.profile_url" :href="person.profile_url">{{ person.pseudo }}</Link>
                    <template v-else>{{ person.pseudo }}</template>
                </h2>
                <p class="small text-secondary mb-3">{{ meta }}</p>
                <div class="vstack gap-2">
                    <button v-if="invited" class="btn btn-sm btn-success-soft w-100" type="button" disabled><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ t('community.follow.actions.invited') }}</button>
                    <button v-else-if="canInvite && person.can.invite" class="btn btn-sm btn-primary-soft w-100" type="button" :disabled="inviting" :aria-busy="inviting ? 'true' : undefined" :aria-label="t('community.follow.actions.invite_named', { name: person.pseudo })" @click="emit('invite', person)"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>{{ t('community.follow.actions.invite') }}</button>
                    <button v-if="person.can.follow" :class="['btn btn-sm w-100', person.following ? 'btn-success-soft' : 'btn-light']" type="button" :disabled="following" :aria-busy="following ? 'true' : undefined" :aria-label="person.following ? t('community.follow.actions.unfollow_named', { name: person.pseudo }) : t('community.follow.actions.follow_named', { name: person.pseudo })" @click="emit('follow', person)"><i :class="`bi bi-${person.following ? 'check2' : 'plus-lg'} me-1`" aria-hidden="true"></i>{{ person.following ? t('community.follow.actions.following') : t('community.follow.actions.follow') }}</button>
                </div>
            </div>
        </article>
    </div>
</template>
