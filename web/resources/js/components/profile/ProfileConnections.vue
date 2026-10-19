<script setup lang="ts">
// Onglet « Connexions » (lot profil, P8-07 ; prototype my-profile-connections.php) : titre + compteur, recherche sur
// le pseudonyme (formulaire GET `?q=` qui marche sans JavaScript ; avec, appel `GET members/{pseudo}/connections`),
// cartes (avatar, pseudo, rôle · zone, bouton « Envoyer un message »), « Charger plus » par page. Vide : lien
// « Envoyer une invitation » (Qui suivre) sur son profil.
import { ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { profileApi } from '@/profile/api';
import type { ConnectionsPage, MemberProfile, ProfileConnection, ProfileLinks } from '@/profile/types';
import XmAvatar from '@/components/XmAvatar.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

const props = defineProps<{
    profile: MemberProfile;
    page: ConnectionsPage;
    links: ProfileLinks;
    action: string;
}>();

const { t } = useI18n();
const toast = useToast();
const api = profileApi(useApi());

const items = ref<ProfileConnection[]>([...props.page.data]);
const current = ref(props.page.meta.current_page);
const last = ref(props.page.meta.last_page);
const total = ref(props.page.meta.total);
const q = ref(props.page.meta.q ?? '');
const searched = ref(props.page.meta.q ?? '');
const loading = ref(false);
const announce = ref('');

watch(() => props.page, (page) => {
    items.value = [...page.data];
    current.value = page.meta.current_page;
    last.value = page.meta.last_page;
    total.value = page.meta.total;
});

async function load(page: number, replace: boolean) {
    if (loading.value) return;
    loading.value = true;
    try {
        const result = await api.connections(props.profile.pseudo, { q: searched.value || null, page });
        const known = new Set(replace ? [] : items.value.map((item) => item.id));
        const fresh = result.data.filter((item) => !known.has(item.id));
        items.value = replace ? result.data : [...items.value, ...fresh];
        current.value = result.meta.current_page;
        last.value = result.meta.last_page;
        total.value = result.meta.total;
        announce.value = replace
            ? t('profile.connections', { count: result.meta.total }, result.meta.total)
            : t('profile.connections_tab.loaded', { count: fresh.length }, fresh.length);
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
    } finally {
        loading.value = false;
    }
}

function search(event: Event) {
    event.preventDefault();
    searched.value = q.value.trim();
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        if (searched.value) url.searchParams.set('q', searched.value); else url.searchParams.delete('q');
        window.history.replaceState(window.history.state, '', url.toString());
    }
    load(1, true);
}
</script>

<template>
    <section class="card" aria-labelledby="profileConnectionsTitle">
        <div class="card-header border-0 pb-0 d-sm-flex justify-content-between align-items-center">
            <h2 id="profileConnectionsTitle" class="h5 card-title mb-2 mb-sm-0">{{ t('profile.connections_tab.title') }} <span class="badge bg-primary bg-opacity-10 text-primary">{{ total }}</span></h2>
            <form method="get" :action="action" role="search" @submit="search">
                <div class="input-group input-group-sm">
                    <input v-model="q" class="form-control" type="search" name="q" maxlength="60" :placeholder="t('profile.connections_tab.search')" :aria-label="t('profile.connections_tab.search')">
                    <button class="btn btn-primary" type="submit" :aria-label="t('profile.connections_tab.search_submit')"><i class="bi bi-search" aria-hidden="true"></i></button>
                </div>
            </form>
        </div>
        <div class="card-body" :aria-busy="loading ? 'true' : 'false'">
            <ul class="row g-3 list-unstyled mb-0">
                <li v-for="person in items" :key="person.id" class="col-sm-6">
                    <div class="d-flex align-items-center gap-3 border rounded p-2">
                        <XmAvatar :name="person.pseudo" :src="person.avatar_url" />
                        <div class="overflow-hidden flex-grow-1">
                            <Link class="h6 mb-0 text-truncate d-block text-reset" :href="person.url">{{ person.pseudo }}</Link>
                            <p class="small text-secondary mb-0 text-truncate">{{ person.role_label }}<template v-if="person.zone_label"> · {{ person.zone_label }}</template></p>
                        </div>
                        <Link v-if="person.message_url" class="btn btn-light btn-sm icon-md" :href="person.message_url" :title="t('profile.sidebar.message')" :aria-label="`${t('profile.sidebar.message')} – ${person.pseudo}`"><i class="bi bi-chat-dots" aria-hidden="true"></i></Link>
                    </div>
                </li>
                <li v-if="items.length === 0 && !loading" class="col-12 text-center text-secondary py-3">
                    {{ searched ? t('profile.connections_tab.empty_search') : t('profile.empty.connections') }}
                    <a v-if="!searched && profile.is_self && links.who_to_follow" :href="links.who_to_follow">{{ t('profile.connections_tab.invite') }}</a>
                </li>
            </ul>
            <XmSkeleton v-if="loading" class="mt-3" type="lines" :label="t('microcopy.loading.more')" />
            <div v-if="current < last" class="xm-load-more mt-3">
                <button class="btn btn-primary-soft px-4" type="button" :disabled="loading" :aria-busy="loading ? 'true' : undefined" @click="load(current + 1, false)">{{ loading ? t('microcopy.loading.more') : t('common.load_more') }}</button>
            </div>
            <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
        </div>
    </section>
</template>
