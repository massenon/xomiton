<script setup lang="ts">
// Onglet « Membres » d'un groupe (lot groupes-pages, P8-02 ; prototype group-details.php l.82-97) : membres actives
// en deux colonnes (avatar, pseudonyme, rôle), équipe d'abord ; profils non visibles comptés sans être nommés
// (`hidden_count`, RG-02). Équipe du groupe : « Demandes en attente » (Accepter / Refuser) et, par membre, un menu
// Nommer admin · Nommer modérateur·rice · Repasser membre · Retirer du groupe (confirmation), selon `can` de l'API.
// Chargé à l'ouverture de l'onglet (API `GET groups/{ulid}/members`, `?status=requested`), « Charger plus » par page
// (membres et demandes). Après « Accepter », la liste des membres est relue (droits `can` de la nouvelle membre).
// Émet `updated` avec la fiche du groupe à jour (compteurs) après une décision.
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { communityApi } from '@/community/api';
import type { Group, GroupMember, PagedList } from '@/community/types';
import XmAvatar from '@/components/XmAvatar.vue';
import XmModal from '@/components/XmModal.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

const props = defineProps<{ group: Group }>();
const emit = defineEmits<{ updated: [group: Group] }>();

const { t } = useI18n();
const toast = useToast();
const api = communityApi(useApi());

const members = ref<GroupMember[]>([]);
const requests = ref<GroupMember[]>([]);
const requestsPage = ref(1);
const requestsLastPage = ref(1);
const requestsTotal = ref(0);
const moreRequests = ref(false);
const page = ref(1);
const lastPage = ref(1);
const hidden = ref(0);
const loading = ref(true);
const more = ref(false);
const busy = ref<number | null>(null);
const removing = ref<GroupMember | null>(null);
const removeModal = ref<InstanceType<typeof XmModal> | null>(null);

const team = computed(() => props.group.can.moderate);

function report(cause: unknown) {
    if (cause instanceof ApiError && (cause.status === 429 || cause.status === 419 || cause.status >= 500)) return;
    toast.show(cause instanceof ApiError ? cause.message : t('microcopy.error.generic'), 'warning');
}

async function load() {
    loading.value = true;
    try {
        const [list, pending] = await Promise.all([
            api.members(props.group.id),
            team.value ? api.members(props.group.id, { status: 'requested' }) : Promise.resolve(null),
        ]);
        applyMembers(list);
        requests.value = pending?.data ?? [];
        requestsPage.value = pending?.meta.current_page ?? 1;
        requestsLastPage.value = pending?.meta.last_page ?? 1;
        requestsTotal.value = Number(pending?.meta.total ?? requests.value.length) || 0;
    } catch (cause) {
        report(cause);
    } finally {
        loading.value = false;
    }
}

function applyMembers(list: PagedList<GroupMember>) {
    members.value = list.data;
    page.value = list.meta.current_page;
    lastPage.value = list.meta.last_page;
    hidden.value = Number(list.meta.hidden_count) || 0;
}

// Relit la première page des membres (nouvelle membre avec ses droits `can`) ; un échec garde la liste affichée.
async function refreshMembers() {
    try {
        applyMembers(await api.members(props.group.id));
    } catch (cause) {
        report(cause);
    }
}

async function loadMoreRequests() {
    if (moreRequests.value || requestsPage.value >= requestsLastPage.value) return;
    moreRequests.value = true;
    try {
        const next = await api.members(props.group.id, { status: 'requested', page: requestsPage.value + 1 });
        const known = new Set(requests.value.map((member) => member.id));
        requests.value = [...requests.value, ...next.data.filter((member) => !known.has(member.id))];
        requestsPage.value = next.meta.current_page;
        requestsLastPage.value = next.meta.last_page;
        requestsTotal.value = Number(next.meta.total ?? requestsTotal.value) || 0;
    } catch (cause) {
        report(cause);
    } finally {
        moreRequests.value = false;
    }
}

async function loadMore() {
    if (more.value || page.value >= lastPage.value) return;
    more.value = true;
    try {
        const next = await api.members(props.group.id, { page: page.value + 1 });
        const known = new Set(members.value.map((member) => member.id));
        members.value = [...members.value, ...next.data.filter((member) => !known.has(member.id))];
        page.value = next.meta.current_page;
        lastPage.value = next.meta.last_page;
    } catch (cause) {
        report(cause);
    } finally {
        more.value = false;
    }
}

async function decide(member: GroupMember, accept: boolean) {
    if (busy.value) return;
    busy.value = member.id;
    try {
        const updated = accept ? await api.accept(props.group.id, member.id) : await api.removeMember(props.group.id, member.id);
        requests.value = requests.value.filter((item) => item.id !== member.id);
        requestsTotal.value = Math.max(0, requestsTotal.value - 1);
        emit('updated', updated);
        if (accept) void refreshMembers();
        toast.show(accept ? t('community.groups.toasts.request_accepted') : t('community.groups.toasts.request_declined'), 'success');
    } catch (cause) {
        report(cause);
    } finally {
        busy.value = null;
    }
}

async function setRole(member: GroupMember, role: string) {
    if (busy.value) return;
    busy.value = member.id;
    try {
        const updated = await api.setRole(props.group.id, member.id, role);
        members.value = members.value.map((item) => (item.id === member.id ? updated : item));
        toast.show(t('community.groups.toasts.role_changed'), 'success');
    } catch (cause) {
        report(cause);
    } finally {
        busy.value = null;
    }
}

async function confirmRemove() {
    const member = removing.value;
    if (!member || busy.value) return;
    busy.value = member.id;
    try {
        const updated = await api.removeMember(props.group.id, member.id);
        members.value = members.value.filter((item) => item.id !== member.id);
        emit('updated', updated);
        toast.show(t('community.groups.toasts.member_removed'), 'success');
        removeModal.value?.close();
    } catch (cause) {
        report(cause);
    } finally {
        busy.value = null;
    }
}

const ROLE_ACTIONS: Array<{ role: string; label: string; show: (member: GroupMember) => boolean }> = [
    { role: 'admin', label: 'community.groups.actions.make_admin', show: (member) => member.can.make_admin },
    { role: 'moderateur', label: 'community.groups.actions.make_moderator', show: (member) => member.can.change_role && member.role !== 'moderateur' },
    { role: 'membre', label: 'community.groups.actions.make_member', show: (member) => member.can.change_role && member.role !== 'membre' },
];
const hasMenu = (member: GroupMember) => member.can.remove || ROLE_ACTIONS.some((action) => action.show(member));

onMounted(load);
</script>

<template>
    <div class="vstack gap-4">
        <section v-if="team" class="card" aria-labelledby="groupRequestsTitle">
            <div class="card-header border-0 pb-0"><h2 id="groupRequestsTitle" class="h6 mb-0">{{ t('community.groups.detail.requests') }} <span v-if="requestsTotal" class="badge bg-warning text-dark ms-1">{{ requestsTotal }}</span></h2></div>
            <div class="card-body">
                <p v-if="!loading && requests.length === 0" class="small text-secondary mb-0">{{ t('community.groups.detail.requests_empty') }}</p>
                <ul v-else class="list-unstyled vstack gap-3 mb-0">
                    <li v-for="member in requests" :key="member.id" class="d-flex align-items-center gap-2 flex-wrap">
                        <XmAvatar :name="member.pseudo" :src="member.avatar_url" size="sm" />
                        <span class="h6 mb-0 me-auto">{{ member.pseudo }}</span>
                        <button class="btn btn-sm btn-primary" type="button" :disabled="busy === member.id" :aria-label="`${t('community.groups.actions.accept')} – ${member.pseudo}`" @click="decide(member, true)">{{ t('community.groups.actions.accept') }}</button>
                        <button class="btn btn-sm btn-light" type="button" :disabled="busy === member.id" :aria-label="`${t('community.groups.actions.decline')} – ${member.pseudo}`" @click="decide(member, false)">{{ t('community.groups.actions.decline') }}</button>
                    </li>
                </ul>
                <div v-if="requestsPage < requestsLastPage" class="xm-load-more mt-3">
                    <button class="btn btn-primary-soft btn-sm px-4" type="button" :disabled="moreRequests" :aria-busy="moreRequests ? 'true' : undefined" @click="loadMoreRequests">{{ moreRequests ? t('microcopy.loading.more') : t('common.load_more') }}</button>
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="groupMembersTitle">
            <div class="card-header border-0 pb-0"><h2 id="groupMembersTitle" class="h6 mb-0">{{ t('community.groups.detail.members') }}</h2></div>
            <div class="card-body" :aria-busy="loading ? 'true' : 'false'">
                <XmSkeleton v-if="loading" type="avatar" :label="t('microcopy.loading.more')" />
                <template v-else>
                    <div v-if="members.length" class="row g-3">
                        <div v-for="member in members" :key="member.id" class="col-sm-6">
                            <div class="hstack gap-2">
                                <XmAvatar :name="member.pseudo" :src="member.avatar_url" />
                                <div class="overflow-hidden me-auto">
                                    <div class="h6 mb-0 text-truncate">{{ member.pseudo }}</div>
                                    <p class="small mb-0 text-secondary">{{ member.role_label }}</p>
                                </div>
                                <div v-if="hasMenu(member)" class="dropdown">
                                    <button type="button" class="btn btn-sm btn-light py-1 px-2" data-bs-toggle="dropdown" aria-expanded="false" :disabled="busy === member.id" :aria-label="t('community.groups.actions.member_actions', { name: member.pseudo })" :title="t('community.groups.actions.member_actions', { name: member.pseudo })"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <template v-for="action in ROLE_ACTIONS" :key="action.role">
                                            <li v-if="action.show(member)"><button class="dropdown-item" type="button" @click="setRole(member, action.role)">{{ t(action.label) }}</button></li>
                                        </template>
                                        <li v-if="member.can.remove"><button class="dropdown-item text-danger" type="button" @click="removing = member">{{ t('community.groups.actions.remove') }}</button></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="small text-secondary mb-0">{{ t('community.groups.detail.members_empty') }}</p>
                    <p v-if="hidden > 0" class="small text-secondary mt-3 mb-0">{{ t('community.groups.detail.hidden_members', { count: hidden }, hidden) }}</p>
                    <div v-if="page < lastPage" class="xm-load-more mt-3">
                        <button class="btn btn-primary-soft btn-sm px-4" type="button" :disabled="more" :aria-busy="more ? 'true' : undefined" @click="loadMore">{{ more ? t('microcopy.loading.more') : t('common.load_more') }}</button>
                    </div>
                </template>
            </div>
        </section>

        <XmModal v-if="removing" ref="removeModal" id="modalRemoveMember" :title="t('community.groups.detail.remove_title', { name: removing.pseudo })" type="confirm" :open="true" :action="t('community.groups.actions.remove')" :busy="busy === removing.id" @action="confirmRemove" @hidden="removing = null">
            <p class="mb-0">{{ t('community.groups.detail.remove_body') }}</p>
        </XmModal>
    </div>
</template>
