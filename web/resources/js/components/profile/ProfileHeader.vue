<script setup lang="ts">
// En-tête du profil (lot profil, P8-07 ; prototype partials/profile-head.php) : couverture 200 px, avatar xxl,
// pseudonyme (jamais le nom réel, RG-02) + badge « Rôle vérifié », @pseudo · connexions, actions (son profil :
// « Modifier le profil » + menu Confidentialité / Télécharger mes données / Paramètres ; une autre personne :
// « Envoyer un message » et liens « Tu suis ce profil » / « Te suit » / « Connexion »), rôle · zone · membre depuis,
// statistiques, complétion (son profil) et six onglets du prototype en <Link> Inertia (`nav-bottom-line`, compteur de
// publications). Couverture et avatar : boutons « Changer… » sur son profil (POST me/profile/{kind}, image ré-encodée
// sans métadonnées ; e-mail confirmé exigé, comme toute publication) et « Retirer… » (DELETE me/profile/{kind}) ; ce sont
// de vrais <button> (focus visible, WCAG 2.4.7) qui ouvrent un champ fichier masqué ; le menu Bootstrap est chargé au
// premier geste (bootstrapOnDemand), jamais importé ici.
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { profileApi, type ProfileImages } from '@/profile/api';
import type { MemberProfile, ProfileLinks, ProfileTab, ProfileTabLink } from '@/profile/types';
import XmAvatar from '@/components/XmAvatar.vue';

const props = defineProps<{
    profile: MemberProfile;
    tabs: ProfileTabLink[];
    active: ProfileTab;
    links: ProfileLinks;
}>();

const emit = defineEmits<{ (event: 'images', value: ProfileImages): void }>();

const { t } = useI18n();
const toast = useToast();
const api = profileApi(useApi());
const busy = ref<'avatar' | 'cover' | null>(null);
const coverInput = ref<HTMLInputElement | null>(null);
const avatarInput = ref<HTMLInputElement | null>(null);
const status = ref('');

const canChange = computed(() => props.profile.is_self && props.profile.can_change_images === true);
const stats = computed(() => props.profile.stats);
const completion = computed(() => Math.max(0, Math.min(100, props.profile.completion ?? 0)));

async function upload(kind: 'avatar' | 'cover', event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file || busy.value) return;
    busy.value = kind;
    status.value = t('profile.images.uploading');
    try {
        const images = await api.uploadImage(kind, file);
        emit('images', images);
        const message = t(kind === 'avatar' ? 'profile.settings.toasts.avatar_saved' : 'profile.settings.toasts.cover_saved');
        status.value = message;
        toast.show(message, 'success');
    } catch (cause) {
        const message = cause instanceof ApiError && cause.errors.image?.[0] ? cause.errors.image[0] : t('profile.images.failed');
        status.value = message;
        toast.show(message, 'danger');
    } finally {
        busy.value = null;
    }
}

function pick(kind: 'avatar' | 'cover') {
    if (busy.value) return;
    (kind === 'avatar' ? avatarInput : coverInput).value?.click();
}

async function remove(kind: 'avatar' | 'cover') {
    if (busy.value) return;
    busy.value = kind;
    try {
        emit('images', await api.removeImage(kind));
        status.value = t('profile.images.removed');
        toast.show(status.value, 'success');
    } catch {
        status.value = t('profile.images.remove_failed');
        toast.show(status.value, 'danger');
    } finally {
        busy.value = null;
    }
}
</script>

<template>
    <div class="card">
        <div class="xm-profile-cover">
            <img class="xm-img-cover" :src="profile.cover_url" :alt="t('profile.cover_alt')" decoding="async" fetchpriority="high">
            <div v-if="profile.is_self && (canChange || profile.has_cover)" class="xm-profile-cover-action d-flex gap-2">
                <button v-if="profile.has_cover" class="btn btn-sm btn-light" type="button" :disabled="busy !== null" :aria-busy="busy === 'cover' ? 'true' : undefined" @click="remove('cover')">
                    <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ t('profile.images.remove_cover') }}
                </button>
                <template v-if="canChange">
                    <button class="btn btn-sm btn-light" type="button" :disabled="busy !== null" :aria-busy="busy === 'cover' ? 'true' : undefined" @click="pick('cover')">
                        <i class="bi bi-camera me-1" aria-hidden="true"></i>{{ t('profile.images.change_cover') }}
                    </button>
                    <input ref="coverInput" class="visually-hidden" type="file" tabindex="-1" aria-hidden="true" accept="image/jpeg,image/png,image/webp" :disabled="busy !== null" @change="upload('cover', $event)">
                </template>
            </div>
        </div>
        <div class="card-body py-0">
            <div class="d-sm-flex align-items-start text-center text-sm-start">
                <div class="xm-profile-avatar">
                    <XmAvatar class="mt-n5 mb-3" size="xxl" :name="profile.pseudo" :src="profile.avatar_url" />
                    <div v-if="profile.is_self && (canChange || profile.has_avatar)" class="xm-profile-avatar-action d-flex gap-1">
                        <button v-if="profile.has_avatar" class="btn btn-sm btn-light rounded-circle" type="button" :title="t('profile.images.remove_avatar')" :disabled="busy !== null" :aria-busy="busy === 'avatar' ? 'true' : undefined" @click="remove('avatar')">
                            <i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ t('profile.images.remove_avatar') }}</span>
                        </button>
                        <template v-if="canChange">
                            <button class="btn btn-sm btn-light rounded-circle" type="button" :title="t('profile.images.change_avatar')" :disabled="busy !== null" :aria-busy="busy === 'avatar' ? 'true' : undefined" @click="pick('avatar')">
                                <i class="bi bi-camera" aria-hidden="true"></i><span class="visually-hidden">{{ t('profile.images.change_avatar') }}</span>
                            </button>
                            <input ref="avatarInput" class="visually-hidden" type="file" tabindex="-1" aria-hidden="true" accept="image/jpeg,image/png,image/webp" :disabled="busy !== null" @change="upload('avatar', $event)">
                        </template>
                    </div>
                </div>
                <div class="ms-sm-4 mt-sm-3">
                    <h1 class="mb-0 h5">
                        {{ profile.pseudo }}
                        <i v-if="profile.verified" class="bi bi-patch-check-fill text-primary small" role="img" :aria-label="t('profile.verified_role', { role: profile.role_label })" :title="t('profile.verified_role', { role: profile.role_label })"></i>
                    </h1>
                    <p class="mb-0 text-secondary">
                        <span v-if="profile.pronouns">{{ profile.pronouns }} · </span>{{ t('profile.pseudo', { pseudo: profile.pseudo }) }} · {{ t('profile.connections', { count: stats.connections }, stats.connections) }}
                    </p>
                </div>
                <div class="d-flex mt-3 justify-content-center ms-sm-auto gap-2">
                    <template v-if="profile.is_self">
                        <a class="btn btn-primary-soft" :href="links.settings"><i class="bi bi-pencil-fill pe-1" aria-hidden="true"></i> {{ t('profile.menu.edit') }}</a>
                        <div class="dropdown">
                            <button id="profileAction" class="icon-md btn btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" :aria-label="t('profile.more_actions')"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileAction">
                                <li><a class="dropdown-item" :href="links.privacy"><i class="bi bi-lock xm-fw pe-2" aria-hidden="true"></i>{{ t('profile.menu.privacy') }}</a></li>
                                <li><a class="dropdown-item" :href="links.data"><i class="bi bi-download xm-fw pe-2" aria-hidden="true"></i>{{ t('profile.menu.data') }}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" :href="links.settings"><i class="bi bi-gear xm-fw pe-2" aria-hidden="true"></i>{{ t('profile.menu.settings') }}</a></li>
                            </ul>
                        </div>
                    </template>
                    <Link v-else-if="profile.message_url" class="btn btn-primary-soft" :href="profile.message_url"><i class="bi bi-chat-dots pe-1" aria-hidden="true"></i> {{ t('profile.message') }}</Link>
                </div>
            </div>
            <ul class="list-inline mb-0 text-center text-sm-start mt-3 mt-sm-0 small">
                <li class="list-inline-item"><i class="bi bi-person-badge me-1" aria-hidden="true"></i>{{ profile.role_label }}</li>
                <li v-if="profile.zone_label" class="list-inline-item"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ profile.zone_label }}</li>
                <li v-if="profile.joined_label" class="list-inline-item"><i class="bi bi-calendar2-plus me-1" aria-hidden="true"></i>{{ t('profile.member_since', { date: profile.joined_label }) }}</li>
            </ul>
            <ul class="list-inline mb-0 mt-2 text-center text-sm-start small text-secondary" :aria-label="t('profile.stats_label')">
                <li class="list-inline-item">{{ t('profile.posts_count', { count: stats.posts }, stats.posts) }}</li>
                <li class="list-inline-item">{{ t('profile.followers', { count: stats.followers }, stats.followers) }}</li>
                <li class="list-inline-item">{{ t('profile.following', { count: stats.following }, stats.following) }}</li>
                <template v-if="profile.relationship">
                    <li v-if="profile.relationship.following" class="list-inline-item"><span class="badge bg-primary bg-opacity-10 text-primary">{{ t('profile.relationship.following') }}</span></li>
                    <li v-if="profile.relationship.follows_you" class="list-inline-item"><span class="badge bg-secondary bg-opacity-10 text-secondary">{{ t('profile.relationship.follows_you') }}</span></li>
                    <li v-if="profile.relationship.connected" class="list-inline-item"><span class="badge bg-success bg-opacity-10 text-success">{{ t('profile.relationship.connected') }}</span></li>
                </template>
            </ul>
            <div v-if="profile.is_self" class="mt-3">
                <div class="d-flex justify-content-between small mb-1"><span id="profileCompletion">{{ t('profile.completion', { percent: completion }) }}</span></div>
                <div class="progress xm-profile-progress" role="progressbar" aria-labelledby="profileCompletion" :aria-valuenow="completion" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" :style="{ width: `${completion}%` }"></div>
                </div>
                <p v-if="!canChange" class="small text-secondary mt-2 mb-0">{{ t('profile.images.verify_first') }}</p>
            </div>
            <p class="visually-hidden" role="status" aria-live="polite">{{ status }}</p>
        </div>
        <div class="card-footer mt-3 pt-2 pb-0">
            <nav :aria-label="t('profile.tabs_label')">
                <ul class="nav nav-bottom-line align-items-center justify-content-center justify-content-md-start mb-0 border-0 flex-nowrap overflow-auto xm-profile-tabs">
                    <li v-for="tab in tabs" :key="tab.key" class="nav-item">
                        <Link :class="['nav-link text-nowrap', { active: active === tab.key }]" :href="tab.href" :aria-current="active === tab.key ? 'page' : undefined" preserve-scroll>
                            {{ t(`profile.tabs.${tab.key}`) }}<span v-if="tab.key === 'posts' && tab.count !== null" class="badge bg-primary bg-opacity-10 text-primary ms-1">{{ tab.count }}</span>
                        </Link>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</template>
