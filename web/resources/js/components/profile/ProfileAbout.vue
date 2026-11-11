<script setup lang="ts">
// Onglet « À propos » (lot profil, P8-07 ; prototype my-profile-about.php) : Informations (pseudonyme, pronoms, type
// de membre, zone si affichée, inscription — ni nom réel, ni e-mail, ni téléphone : RG-02, le prototype les montrait),
// Centres d'intérêt, Informations personnelles sensibles et Confidentialité. Champs sensibles : ceux que le serveur
// sert (MemberProfileResource) — pour une autre personne, seulement ceux que le profil a choisi de montrer ; pour soi,
// chacun avec « Visible sur ton profil » ou « Visible par toi seul·e ». Listes <dl> : chaque <div> ne contient que
// <dt> et <dd> (icône dans le <dt>, placée par profil.css), pour une restitution correcte aux lecteurs d'écran.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { MemberProfile, ProfileLinks } from '@/profile/types';

const props = withDefaults(defineProps<{
    profile: MemberProfile;
    links: ProfileLinks;
    canComplete?: boolean;
}>(), { canComplete: false });

defineEmits<{ (event: 'complete'): void }>();

const { t } = useI18n();

const rows = computed(() => [
    { icon: 'at', label: t('profile.settings.profile.pseudo'), value: t('profile.pseudo', { pseudo: props.profile.pseudo }) },
    { icon: 'chat-quote', label: t('profile.about.pronouns'), value: props.profile.pronouns || t('profile.about.not_provided') },
    { icon: 'person-badge', label: t('profile.about.member_type'), value: props.profile.member_type_label || props.profile.role_label },
    { icon: 'geo-alt', label: t('profile.about.zone'), value: props.profile.zone_label || (props.profile.is_self ? t('profile.about.zone_hidden') : t('profile.about.not_provided')) },
    { icon: 'calendar2-plus', label: t('profile.about.joined'), value: props.profile.joined_label || '' },
]);
</script>

<template>
    <div class="card">
        <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
            <h2 class="h5 card-title mb-0">{{ t('profile.about.info') }}</h2>
            <a v-if="profile.is_self" class="btn btn-primary-soft btn-sm" :href="links.settings"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ t('profile.about.edit') }}</a>
        </div>
        <div class="card-body">
            <dl class="row g-3 mb-0">
                <div v-for="row in rows" :key="row.icon" class="col-sm-6 xm-profile-about-item">
                    <dt class="small text-secondary fw-normal"><i :class="`bi bi-${row.icon} fs-5 text-primary`" aria-hidden="true"></i>{{ row.label }}</dt>
                    <dd class="fw-bold mb-0 text-break">{{ row.value }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div v-if="profile.bio" class="card card-body">
        <h2 class="h5 card-title">{{ t('profile.about.bio') }}</h2>
        <p class="mb-0 text-break xm-group-description-full">{{ profile.bio }}</p>
    </div>

    <div v-if="profile.interests.length" class="card">
        <div class="card-header border-0 pb-0"><h2 class="h5 card-title mb-0">{{ t('profile.about.interests') }}</h2></div>
        <div class="card-body">
            <ul class="d-flex flex-wrap gap-2 list-unstyled mb-0">
                <li v-for="interest in profile.interests" :key="interest.slug" class="xm-chip">{{ interest.label }}</li>
            </ul>
        </div>
    </div>

    <div v-if="profile.sensitive.length" class="card">
        <div class="card-header border-0 pb-0"><h2 class="h5 card-title mb-0">{{ t('profile.about.sensitive_title') }}</h2></div>
        <div class="card-body">
            <dl class="row g-3 mb-0">
                <div v-for="item in profile.sensitive" :key="item.field" class="col-sm-6">
                    <dt class="small text-secondary fw-normal">{{ item.label }}</dt>
                    <dd class="fw-bold mb-0">{{ item.value_label }}</dd>
                    <dd v-if="profile.is_self" class="small text-secondary mb-0">
                        <i :class="`bi bi-${item.shared ? 'eye' : 'eye-slash'} me-1`" aria-hidden="true"></i>{{ item.shared ? t('profile.about.sensitive_shared') : t('profile.about.sensitive_private') }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    <div v-if="profile.is_self" class="card card-body">
        <h2 class="h5 card-title">{{ t('profile.about.privacy_title') }}</h2>
        <p v-if="profile.visibility_label" class="small mb-2">{{ t('profile.about.visibility', { value: profile.visibility_label }) }}</p>
        <p class="small text-secondary mb-2">{{ t('profile.about.privacy_text') }}</p>
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <a :href="links.privacy" class="small">{{ t('profile.about.manage_privacy') }}</a>
            <button v-if="canComplete" class="btn btn-sm btn-primary-soft" type="button" @click="$emit('complete')">{{ t('profile.about.complete') }}</button>
        </div>
    </div>
</template>
