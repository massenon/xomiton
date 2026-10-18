<script setup lang="ts">
// Colonne droite de l'espace membre (P6-03, prototype fil.php l.86-116 et inc/render.php x_render_who_to_follow) :
// col-lg-3 à côté du contenu (col-md-8 col-lg-6), sous le contenu sous 992 px (FRONT_END_LAYOUT_INSTRUCTION §2).
// Cartes « Qui suivre » et « Événements » affichées seulement quand la page les fournit (données des phases P7 / P8),
// carte « En cas d'urgence » toujours présente. Repère <aside> nommé.
// suggestions : [{ id, pseudo, avatar_url, href, meta }] (émet `invite` avec l'id) ; events : [{ id, title, href, image, meta }].
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { LINKS, hrefOf } from '@/app/memberNav';
import XmAvatar from './XmAvatar.vue';

export interface Suggestion {
    id: number | string;
    pseudo: string;
    avatar_url?: string | null;
    href: string;
    meta?: string | null;
}

export interface EventItem {
    id: number | string;
    title: string;
    href: string;
    image?: string | null;
    meta?: string | null;
}

withDefaults(defineProps<{ suggestions?: Suggestion[]; events?: EventItem[] }>(), { suggestions: () => [], events: () => [] });
const emit = defineEmits<{ invite: [id: number | string] }>();

const { t } = useI18n();
</script>

<template>
    <aside class="col-lg-3" :aria-label="t('menus.a11y.member_aside')">
        <div class="row g-4">
            <div v-if="suggestions.length" class="col-sm-6 col-lg-12">
                <div class="card">
                    <div class="card-header pb-0 border-0 d-flex justify-content-between align-items-center">
                        <h2 class="h5 card-title mb-0">{{ t('menus.member_more.who_to_follow_title') }}</h2>
                        <Link class="small" :href="hrefOf(LINKS.quiSuivre)">{{ t('menus.member_more.see_all') }}</Link>
                    </div>
                    <div class="card-body">
                        <div v-for="person in suggestions" :key="person.id" class="hstack gap-2 mb-3">
                            <Link :href="person.href" tabindex="-1" aria-hidden="true"><XmAvatar :name="person.pseudo" :src="person.avatar_url" /></Link>
                            <div class="overflow-hidden">
                                <Link class="h6 mb-0" :href="person.href">{{ person.pseudo }}</Link>
                                <p v-if="person.meta" class="mb-0 small text-truncate">{{ person.meta }}</p>
                            </div>
                            <button type="button" class="btn btn-primary-soft rounded-circle icon-md ms-auto" :title="t('menus.member_more.invite')" :aria-label="t('menus.member_more.invite_named', { pseudo: person.pseudo })" @click="emit('invite', person.id)"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="events.length" class="col-sm-6 col-lg-12">
                <div class="card">
                    <div class="card-header pb-0 border-0 d-flex justify-content-between">
                        <h2 class="h5 card-title mb-0">{{ t('menus.member.events') }}</h2>
                        <Link class="small" :href="hrefOf(LINKS.evenements)">{{ t('menus.member_more.see_all') }}</Link>
                    </div>
                    <div class="card-body">
                        <div v-for="event in events" :key="event.id" class="d-flex mb-3">
                            <div class="avatar"><img v-if="event.image" class="avatar-img rounded-2" :src="event.image" alt="" loading="lazy"></div>
                            <div class="ms-2">
                                <h3 class="h6 card-title mb-0"><Link :href="event.href">{{ event.title }}</Link></h3>
                                <p v-if="event.meta" class="small mb-0">{{ event.meta }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-danger">
                    <div class="card-body">
                        <h2 class="h6 text-danger"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i> {{ t('alerts.emergency.feed_card.title') }}</h2>
                        <p class="small mb-2">{{ t('alerts.emergency.feed_card.text') }}</p>
                        <a class="btn btn-danger btn-sm w-100" :href="hrefOf(LINKS.urgences)">{{ t('menus.member.emergency') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>
