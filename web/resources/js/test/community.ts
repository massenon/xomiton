// Données de test des écrans des groupes et des pages (lot groupes-pages) : objets au format de GroupResource,
// GroupMemberResource et PageResource (docs/API.md §13 et §14).
import type { Group, GroupCreateOptions, GroupMember, OrganizationPage } from '@/community/types';

export const GROUP_ID = '01JC1000000000000000000001';
export const PAGE_ID = '01JC2000000000000000000001';

export function makeGroup(overrides: Partial<Group> = {}): Group {
    const id = overrides.id ?? GROUP_ID;
    return {
        id,
        name: 'Cercle d’entraide de Bohicon',
        description: 'Écoute et entraide entre pair·es.',
        privacy: 'public',
        privacy_label: 'Public',
        zone: { id: 4, name: 'Zou' },
        zone_label: 'Zou',
        members_count: 12,
        cover_url: '/assets/images/xomiton/covers/gr-1.jpg',
        url: `/groupes/${id}`,
        restricted: false,
        is_owner: false,
        membership: null,
        pending_requests: null,
        created_at: '2026-10-01T10:00:00Z',
        can: { join: true, cancel_request: false, leave: false, post: false, discuss: false, moderate: false, change_role: false },
        ...overrides,
    };
}

export const memberOf = (group: Group, role = 'membre'): Group => ({
    ...group,
    members_count: group.members_count + 1,
    membership: { status: 'actif', status_label: 'Actif', role, role_label: role === 'admin' ? 'Administrateur·rice' : 'Membre' },
    can: { ...group.can, join: false, leave: true, post: true, discuss: true, moderate: role !== 'membre', change_role: role === 'admin' },
});

export function makeMember(id: number, overrides: Partial<GroupMember> = {}): GroupMember {
    return {
        id,
        pseudo: `Membre_${id}`,
        avatar_url: null,
        role: 'membre',
        role_label: 'Membre',
        status: 'actif',
        is_owner: false,
        is_me: false,
        joined_at: '2026-10-02T10:00:00Z',
        requested_at: null,
        can: { accept: false, remove: false, change_role: false, make_admin: false },
        ...overrides,
    };
}

export function createOptions(overrides: Partial<GroupCreateOptions> = {}): GroupCreateOptions {
    return {
        can: true,
        zones: [{ id: 12, name: 'Alibori' }, { id: 3, name: 'Atacora' }],
        privacies: [{ value: 'prive', label: 'Privé' }, { value: 'public', label: 'Public' }],
        limits: { name_min: 3, name_max: 80, description_max: 300 },
        ...overrides,
    };
}

export function makePage(overrides: Partial<OrganizationPage> = {}): OrganizationPage {
    const id = overrides.id ?? PAGE_ID;
    return {
        id,
        name: 'Clinique amie de Parakou',
        category: 'Santé',
        description: 'Consultations confidentielles.',
        website: 'https://exemple.bj',
        cover_url: '/assets/images/xomiton/hero-09.jpg',
        followers_count: 41,
        is_verified: true,
        is_featured: false,
        status: 'approved',
        status_label: 'Approuvée',
        is_mine: false,
        following: false,
        url: `/pages/${id}`,
        created_at: '2026-10-01T10:00:00Z',
        can: { follow: true, update: false },
        ...overrides,
    };
}
