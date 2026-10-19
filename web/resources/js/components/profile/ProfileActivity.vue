<script setup lang="ts">
// Onglet « Activité » (lot profil, P8-07 ; prototype my-profile-activity.php « Journal de mon activité ») : son profil
// seulement, entrées servies par ProfileActivity (connexions, réglages, publications et sujets non anonymes,
// participations, groupes rejoints, connexions acceptées), icône par type, détail, date relative (date complète au
// survol et pour les lecteurs d'écran par <time datetime>).
import { useI18n } from 'vue-i18n';
import { timeAgo } from '@/feed/format';
import type { ActivityEntry } from '@/profile/types';

defineProps<{ entries: ActivityEntry[] }>();

const { t } = useI18n();

const fullDate = (iso: string): string => {
    try {
        return new Intl.DateTimeFormat('fr-BJ', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso));
    } catch {
        return iso;
    }
};
</script>

<template>
    <section class="card" aria-labelledby="profileActivityTitle">
        <div class="card-header border-0 pb-0">
            <h2 id="profileActivityTitle" class="h5 card-title mb-0">{{ t('profile.activity.title') }}</h2>
            <p class="small text-secondary mb-0"><i class="bi bi-lock me-1" aria-hidden="true"></i>{{ t('profile.activity.private') }}</p>
        </div>
        <div class="card-body">
            <ol v-if="entries.length" class="list-unstyled mb-0">
                <li v-for="entry in entries" :key="entry.id" class="d-flex gap-3 pb-3 mb-3 border-bottom">
                    <span class="xm-profile-activity-icon rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center"><i :class="`bi bi-${entry.icon}`" aria-hidden="true"></i></span>
                    <div class="overflow-hidden">
                        <div class="fw-bold">{{ entry.label }}</div>
                        <div v-if="entry.detail" class="small text-break">
                            <a v-if="entry.href" :href="entry.href">{{ entry.detail }}</a><template v-else>{{ entry.detail }}</template>
                        </div>
                        <time class="small text-secondary" :datetime="entry.at" :title="fullDate(entry.at)">{{ timeAgo(entry.at, t) }}</time>
                    </div>
                </li>
            </ol>
            <p v-else class="text-center text-secondary mb-0">{{ t('profile.empty.activity') }}</p>
        </div>
    </section>
</template>
