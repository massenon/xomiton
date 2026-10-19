<script setup lang="ts">
// Carte d'un contact de l'annuaire (lot annuaire-membre, P8-12 ; prototype annuaire.php l.84-108) : avatar à initiales,
// nom, rôle (`role_color` = DirectoryRole::memberBadge() côté serveur, comme /urgences : jamais le rouge de l'urgence), organisation,
// commune et département (ou « National »), disponibilité, Appeler (`tel:`), WhatsApp (wa.me, nouvel onglet), E-mail
// (`mailto:`) ; pied « Demander à être rappelé·e » (fiche publique seulement, Q51), sinon la mention « Réservé au
// réseau ». Props : contact. Émet `callback`.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { DirectoryContact } from '@/directory/types';
import XmAvatar from '@/components/XmAvatar.vue';
import XmBadge from '@/components/XmBadge.vue';

const props = defineProps<{ contact: DirectoryContact }>();
const emit = defineEmits<{ callback: [contact: DirectoryContact, opener: HTMLElement | null] }>();

const { t } = useI18n();
const headingId = computed(() => `xm-dir-contact-${props.contact.id}`);
// Point focal national ou fiche sans département : « National », comme /urgences (`national` de la fiche).
const place = computed(() => {
    if (props.contact.national) return t('directory.card.national');
    const parts = [props.contact.commune?.name, props.contact.zone?.name].filter((part): part is string => Boolean(part));
    return parts.length > 0 ? parts.join(', ') : t('directory.card.national');
});
</script>

<template>
    <li class="col-md-6" :data-xm-contact="contact.id">
        <article class="card h-100" :aria-labelledby="headingId">
            <div class="card-body">
                <div class="d-flex gap-3">
                    <XmAvatar class="flex-shrink-0" size="lg" :name="contact.name" aria-hidden="true" />
                    <div class="xm-dir-shrink">
                        <h2 :id="headingId" class="h6 mb-1 text-break">{{ contact.name }}</h2>
                        <XmBadge soft :color="contact.role_color">{{ contact.role_label }}</XmBadge>
                        <XmBadge v-if="!contact.is_public" soft color="neutral" icon="shield-lock" class="ms-1">{{ t('directory.card.reserved') }}</XmBadge>
                        <div v-if="contact.org" class="small text-secondary mt-1">{{ contact.org }}</div>
                        <div class="small"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ place }}</div>
                        <div v-if="contact.availability" class="small"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ contact.availability }}</div>
                    </div>
                </div>
                <div v-if="contact.tel || contact.whatsapp_url || contact.email" class="d-flex flex-wrap gap-2 mt-3">
                    <a v-if="contact.tel" class="btn btn-sm btn-success" :href="contact.tel">
                        <i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ t('directory.card.call') }}<span class="visually-hidden"> {{ contact.name }} ({{ contact.phone }})</span>
                    </a>
                    <a v-if="contact.whatsapp_url" class="btn btn-sm btn-success-soft" :href="contact.whatsapp_url" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>{{ t('directory.card.whatsapp') }}<span class="visually-hidden"> {{ contact.name }} {{ t('microcopy.a11y.opens_new_tab') }}</span>
                    </a>
                    <a v-if="contact.email" class="btn btn-sm btn-light" :href="`mailto:${contact.email}`">
                        <i class="bi bi-envelope me-1" aria-hidden="true"></i>{{ t('directory.card.email') }}<span class="visually-hidden"> {{ contact.name }}</span>
                    </a>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <button
                    v-if="contact.can.callback"
                    class="btn btn-sm btn-primary-soft w-100"
                    type="button"
                    aria-haspopup="dialog"
                    @click="emit('callback', contact, $event.currentTarget as HTMLElement)"
                >
                    <i class="bi bi-telephone-inbound me-1" aria-hidden="true"></i>{{ t('directory.card.callback') }}<span class="visually-hidden"> — {{ contact.name }}</span>
                </button>
                <p v-else class="small text-secondary mb-0">{{ t('directory.card.callback_unavailable') }}</p>
            </div>
        </article>
    </li>
</template>
