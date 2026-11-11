// Contrat des écrans des groupes et des pages (lot groupes-pages, P8-02, P8-03) : objets de l'API /api/v1 (docs/API.md
// §13 et §14 : App\Http\Resources\Community\GroupResource, GroupMemberResource, PageResource) et props Inertia de
// App\Http\Controllers\Web\Member\GroupsPageController et PagesPageController (premières pages sous la forme de l'API).

export interface GroupMembership {
    /** `actif` | `demande` */
    status: string;
    status_label: string;
    role: string | null;
    role_label: string | null;
}

export interface Group {
    id: string;
    name: string;
    /** null pour une fiche réduite (groupe privé dont on n'est pas membre). */
    description: string | null;
    /** `public` | `prive` */
    privacy: string;
    privacy_label: string;
    zone: { id: number; name: string } | null;
    zone_label: string;
    members_count: number;
    cover_url: string;
    url: string;
    restricted: boolean;
    is_owner: boolean;
    membership: GroupMembership | null;
    pending_requests: number | null;
    created_at: string | null;
    can: {
        join: boolean;
        cancel_request: boolean;
        leave: boolean;
        post: boolean;
        discuss: boolean;
        moderate: boolean;
        change_role: boolean;
    };
}

export interface GroupMember {
    id: number;
    pseudo: string;
    avatar_url: string | null;
    /** `membre` | `moderateur` | `admin` */
    role: string;
    role_label: string;
    status: string;
    is_owner: boolean;
    is_me: boolean;
    joined_at: string | null;
    requested_at: string | null;
    can: { accept: boolean; remove: boolean; change_role: boolean; make_admin: boolean };
}

export interface OrganizationPage {
    id: string;
    name: string;
    category: string;
    description: string | null;
    website: string | null;
    cover_url: string;
    followers_count: number;
    is_verified: boolean;
    is_featured: boolean;
    /** `approved` | `pending` */
    status: string;
    status_label: string;
    is_mine: boolean;
    following: boolean;
    url: string;
    created_at: string | null;
    can: { follow: boolean; update: boolean };
}

/** Liste paginée par page (`meta.current_page`, `meta.last_page`). */
export interface PagedList<T> {
    data: T[];
    meta: { current_page: number; last_page: number; total?: number; [key: string]: unknown };
}

export interface Option<V = string> {
    value: V;
    label: string;
}

export interface GroupCreateOptions {
    can: boolean;
    zones: Array<{ id: number; name: string }>;
    privacies: Option[];
    limits: { name_min: number; name_max: number; description_max: number };
}

export interface FollowState {
    following: boolean;
    followers_count: number;
    changed: boolean;
}
