<script setup lang="ts">
// Badges vérifiés par l'administration (P18-09, Q97 : « Membre vérifié », « Pair animateur·rice »), à côté du pseudo
// dans le fil, les commentaires, les forums et le profil. Attribués à la main par l'équipe, jamais de classement ; rien
// n'est rendu sans badge. Le libellé est un texte (lu par les lecteurs d'écran), l'icône est décorative.
import type { VerifiedBadge } from '@/feed/types';

withDefaults(defineProps<{ badges?: VerifiedBadge[] | null }>(), { badges: () => [] });
</script>

<template>
    <template v-if="badges && badges.length">
        <span v-for="badge in badges" :key="badge.key" class="badge bg-primary bg-opacity-10 text-primary ms-1 xm-verified-badge" :data-badge="badge.key"><i :class="`bi bi-${badge.icon} me-1`" aria-hidden="true"></i>{{ badge.label }}</span>
    </template>
</template>
