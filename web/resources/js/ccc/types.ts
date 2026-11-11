// Types des outils CCC lus par `GET /api/v1/ccc` et la page `/ccc` (P8-12, App\Http\Resources\Ccc\CccToolResource) :
// outils publiés seulement, `chapters` et `pages` dans l'ordre stocké, images déjà résolues (image de remplacement
// comprise), `alt` jamais vide.

export interface CccPage {
    position: number;
    image: string;
    caption: string | null;
    alt: string;
}

export interface ManualModule {
    key: string;
    family: 'manuel';
    title: string;
    description: string | null;
    cover: null;
    duration_minutes: number | null;
    chapters: string[];
    chapters_count: number;
}

export interface PagedTool {
    key: string;
    family: 'boites_images' | 'bd';
    title: string;
    description: string | null;
    cover: string;
    pages: CccPage[];
    pages_count: number;
}

export interface CccTools {
    manuel: ManualModule[];
    boites_images: PagedTool[];
    bd: PagedTool[];
}

export interface CccLaws {
    count: number;
    url: string;
}

/** Planche à projeter en mode animation : une page d'une boîte à images avec le guide de sa boîte. */
export interface Slide {
    key: string;
    title: string;
    guide: string | null;
    image: string;
    caption: string | null;
    alt: string;
}
