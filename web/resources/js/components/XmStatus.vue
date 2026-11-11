<script setup lang="ts">
// Statut de cas : même HTML que <x-status> (badge scope « case » + icône + libellé en toutes lettres).
// status : new | assigned | in_progress | pending_victim_response | resolved | closed | unresolved.
import XmBadge from './XmBadge.vue';

withDefaults(defineProps<{ status?: string }>(), { status: 'new' });
</script>

<template>
    <XmBadge scope="case" :status="status" />
</template>
