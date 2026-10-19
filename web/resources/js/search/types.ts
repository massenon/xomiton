// Types de la recherche membre (lot recherche-membre, P8-13 ; docs/API.md §20) : familles (SearchType), section de
// résultats (SearchSectionPayload), résultat complet (SearchHitResource : forme de l'écran de la famille) et suggestion
// courte de l'en-tête (SearchSuggestionResource).
import type { DirectoryContact } from '@/directory/types';

export type SearchTypeValue = 'ressources' | 'thematiques' | 'contacts' | 'membres';

export interface SearchTypeInfo {
    type: SearchTypeValue;
    label: string;
    icon: string;
}

export interface CategoryChip {
    slug: string;
    label: string;
    icon: string | null;
    color: string | null;
}

export interface ResourceHit {
    type: 'ressources';
    icon: string;
    id: number;
    slug: string;
    title: string;
    kind: string;
    kind_label: string;
    summary: string | null;
    category: CategoryChip | null;
    file_size_label: string | null;
    duration_minutes: number | null;
    url: string;
}

export interface ThematiqueHit {
    type: 'thematiques';
    icon: string;
    id: number;
    slug: string;
    title: string;
    summary: string | null;
    reading_minutes: number;
    category: CategoryChip | null;
    url: string;
}

export interface ContactHit extends DirectoryContact {
    type: 'contacts';
    url: string;
}

export interface MemberHit {
    type: 'membres';
    id: number;
    pseudo: string;
    avatar_url: string | null;
    role: string;
    role_label: string;
    zone: { id: number; name: string } | null;
    profile_url: string | null;
}

export type SearchHit = ResourceHit | ThematiqueHit | ContactHit | MemberHit;

export interface SearchSection<T = SearchHit> extends SearchTypeInfo {
    total: number;
    more: boolean;
    items: T[];
}

export interface SearchPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface SearchLimits {
    min_length: number;
    max_length: number;
}

export interface Suggestion {
    type: SearchTypeValue;
    id: number;
    title: string;
    meta: string | null;
    url: string;
    inertia: boolean;
    icon: string;
    avatar_url: string | null;
}

export interface HitPage {
    data: SearchHit[];
    meta: SearchPagination & { type: SearchTypeValue; too_short: boolean };
}
