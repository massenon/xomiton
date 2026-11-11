<script setup lang="ts">
// Onglet « Médias » (lot profil, P8-07 ; prototype my-profile-media.php) : grille de vignettes carrées (col-6 col-md-4,
// `xm-ratio-1x1`) des images des publications visibles et des albums visibles, lien vers la publication ; bouton
// « Médiathèque » sur son profil (/medias, P8-12). La visionneuse plein écran du prototype (GLightbox) n'est pas
// reprise : chaque vignette mène à sa publication.
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { MemberProfile, ProfileLinks, ProfileMediaItem } from '@/profile/types';

defineProps<{
    profile: MemberProfile;
    items: ProfileMediaItem[];
    links: ProfileLinks;
}>();

const { t } = useI18n();
</script>

<template>
    <section class="card" aria-labelledby="profileMediaTitle">
        <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
            <h2 id="profileMediaTitle" class="h5 card-title mb-0">{{ t('profile.media_tab.title') }}</h2>
            <a v-if="profile.is_self && links.media_library" class="btn btn-primary-soft btn-sm" :href="links.media_library">{{ t('profile.media_tab.library') }}</a>
        </div>
        <div class="card-body">
            <ul v-if="items.length" class="row g-3 list-unstyled mb-0">
                <li v-for="item in items" :key="item.id" class="col-6 col-md-4">
                    <component :is="item.href ? Link : 'div'" v-bind="item.href ? { href: item.href } : {}" class="d-block">
                        <div class="xm-ratio-1x1 rounded"><img class="xm-img-cover" :src="item.url" :alt="item.alt || t('profile.media_tab.alt', { name: profile.pseudo })" :width="item.width ?? undefined" :height="item.height ?? undefined" loading="lazy" decoding="async"></div>
                        <span v-if="item.href" class="visually-hidden">{{ t('profile.media_tab.open') }}</span>
                    </component>
                </li>
            </ul>
            <p v-else class="text-center text-secondary mb-0">{{ profile.is_self ? t('profile.empty.media') : t('profile.media_tab.empty_other', { name: profile.pseudo }) }}</p>
        </div>
    </section>
</template>
