<script setup lang="ts">
// Carte d'un groupe (lot groupes-pages, P8-02 ; prototype groups.php l.73-93) : couverture 16/9, badge de
// confidentialité (Public · globe / Privé · cadenas), nom, description, membres · zone, bouton d'adhésion (Rejoindre /
// Demander à rejoindre / Demande envoyée / Membre) et « Voir ». Le geste est confié à la page (émet `join`,
// `cancel`), qui tient l'état et les toasts ; « Membre » mène au groupe (on quitte depuis sa fiche, avec confirmation).
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Group } from '@/community/types';

const props = withDefaults(defineProps<{ group: Group; busy?: boolean }>(), { busy: false });
const emit = defineEmits<{ join: [group: Group]; cancel: [group: Group] }>();

const { t } = useI18n();
const isPublic = computed(() => props.group.privacy === 'public');
const member = computed(() => props.group.membership?.status === 'actif');
const requested = computed(() => props.group.membership?.status === 'demande');
const color = computed(() => (isPublic.value ? 'success' : 'secondary'));
</script>

<template>
    <div class="col-sm-6 col-xl-4">
        <article class="card h-100 overflow-hidden xm-card-hover" :aria-labelledby="`group-${group.id}`">
            <Link :href="group.url" tabindex="-1" aria-hidden="true"><div class="xm-ratio-16x9"><img class="xm-img-cover" :src="group.cover_url" alt="" loading="lazy" decoding="async" width="640" height="360"></div></Link>
            <div class="card-body">
                <span :class="`badge bg-${color} bg-opacity-10 text-${color} mb-2`"><i :class="`bi bi-${isPublic ? 'globe2' : 'lock'} me-1`" aria-hidden="true"></i>{{ group.privacy_label }}</span>
                <h2 :id="`group-${group.id}`" class="h6 mb-1"><Link :href="group.url">{{ group.name }}</Link></h2>
                <p v-if="group.description" class="small text-secondary mb-2 xm-group-description">{{ group.description }}</p>
                <p class="small mb-0"><i class="bi bi-people me-1" aria-hidden="true"></i>{{ t('community.groups.members', { count: group.members_count }, group.members_count) }} · <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ group.zone_label }}</p>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0">
                <div class="d-flex gap-2">
                    <Link v-if="member" class="btn btn-sm flex-grow-1 btn-success-soft" :href="group.url"><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ t('community.groups.actions.member') }}</Link>
                    <button v-else-if="requested" class="btn btn-sm flex-grow-1 btn-light" type="button" :disabled="busy" :aria-busy="busy ? 'true' : undefined" :title="t('community.groups.actions.cancel_request')" @click="emit('cancel', group)"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>{{ t('community.groups.actions.requested') }}<span class="visually-hidden"> – {{ t('community.groups.actions.cancel_request') }}</span></button>
                    <button v-else-if="group.can.join" class="btn btn-sm flex-grow-1 btn-primary-soft" type="button" :disabled="busy" :aria-busy="busy ? 'true' : undefined" @click="emit('join', group)"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ isPublic ? t('community.groups.actions.join') : t('community.groups.actions.request') }}<span class="visually-hidden"> – {{ group.name }}</span></button>
                    <Link class="btn btn-sm btn-light" :href="group.url">{{ t('community.groups.actions.see') }}<span class="visually-hidden"> – {{ group.name }}</span></Link>
                </div>
            </div>
        </article>
    </div>
</template>
