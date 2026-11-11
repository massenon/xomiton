<script setup lang="ts">
// Colonne du profil (lot profil, P8-07 ; prototype inc/x_reseau.php x_profile_side()) : Présentation (présentation ou
// texte d'accueil du prototype, inscription, zone si affichée, rôle), Médias (6 vignettes, « Tout voir » → onglet
// Médias) et Connexions (6 avatars, « Tout voir » → onglet Connexions). Pseudonyme seulement (RG-02).
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { MemberProfile, ProfileSide, ProfileTabLink } from '@/profile/types';
import XmAvatar from '@/components/XmAvatar.vue';

const props = defineProps<{
    profile: MemberProfile;
    side: ProfileSide;
    tabs: ProfileTabLink[];
}>();

const { t } = useI18n();
const hrefOf = (key: string) => props.tabs.find((tab) => tab.key === key)?.href ?? props.profile.url;
</script>

<template>
    <aside class="col-md-4 col-lg-3" :aria-label="t('profile.aside_label')">
        <div class="row g-4">
            <div class="col-sm-6 col-md-12">
                <div class="card">
                    <div class="card-header border-0 pb-0"><h2 class="h5 card-title mb-0">{{ t('profile.sidebar.about') }}</h2></div>
                    <div class="card-body position-relative pt-0">
                        <p class="mt-3 text-break">{{ profile.bio || t('profile.side.default_bio') }}</p>
                        <ul class="list-unstyled mt-3 mb-0 small">
                            <li v-if="profile.joined_label" class="mb-2"><i class="bi bi-calendar-date xm-fw pe-1" aria-hidden="true"></i> {{ t('profile.side.joined', { date: profile.joined_label }) }}</li>
                            <li v-if="profile.zone_label" class="mb-2"><i class="bi bi-geo-alt xm-fw pe-1" aria-hidden="true"></i> {{ t('profile.side.zone', { zone: profile.zone_label }) }}</li>
                            <li><i class="bi bi-shield-check xm-fw pe-1" aria-hidden="true"></i> {{ t('profile.side.role', { role: profile.role_label }) }}</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div v-if="side.media.length" class="col-sm-6 col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between border-0 pb-0">
                        <h2 class="h5 card-title mb-0">{{ t('profile.sidebar.media') }}</h2>
                        <Link class="btn btn-primary-soft btn-sm" :href="hrefOf('media')" preserve-scroll>{{ t('profile.side.see_all') }}</Link>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div v-for="item in side.media" :key="item.id" class="col-4">
                                <div class="xm-ratio-1x1 rounded"><img class="xm-img-cover" :src="item.url" :alt="item.alt || t('profile.media_tab.alt', { name: profile.pseudo })" loading="lazy" decoding="async"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="side.connections.length" class="col-sm-6 col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between border-0 pb-0">
                        <h2 class="h5 card-title mb-0">{{ t('profile.sidebar.connections') }}</h2>
                        <Link class="btn btn-primary-soft btn-sm" :href="hrefOf('connections')" preserve-scroll>{{ t('profile.side.see_all') }}</Link>
                    </div>
                    <div class="card-body">
                        <ul class="row g-3 list-unstyled mb-0">
                            <li v-for="person in side.connections" :key="person.id" class="col-4 text-center">
                                <Link class="text-reset text-decoration-none d-block" :href="person.url">
                                    <XmAvatar class="mb-1 mx-auto" size="lg" :name="person.pseudo" :src="person.avatar_url" />
                                    <span class="small d-block text-truncate">{{ person.pseudo }}</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>
