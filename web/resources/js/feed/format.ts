// Mise en forme des cartes du fil (lot fil-ecrans, P7-05 ; prototype inc/render.php x_render_post, x_poll_remaining,
// inc/bootstrap.php x_ago) : temps relatif (« il y a 3 min », dates.relative de lang/fr/dates.php, comme
// App\Support\LocaleFormatter::ago), temps restant d'un sondage, icône de visibilité et liens de partage (Q31 b :
// toujours le lien neutre `share_url` = /p/{ulid}, jamais le détail). Couleur de l'étiquette : `tag_color` de l'API
// (PostTag::badge()).
type Translate = (key: string, named?: Record<string, unknown>, plural?: number) => string;

const MINUTE = 60_000;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

export const VISIBILITY_ICONS: Record<string, string> = { public: 'globe2', followers: 'people', me: 'lock' };

const dateLabel = (date: Date, locale: string): string => {
    try {
        return new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'long' }).format(date);
    } catch {
        return date.toLocaleDateString();
    }
};

/** « à l’instant », « il y a 3 min », « il y a 2 h », « il y a 4 j », puis « le 2 novembre ». */
export function timeAgo(iso: string | null, t: Translate, now: number = Date.now(), locale = 'fr-BJ'): string {
    if (!iso) return '';
    const date = new Date(iso);
    const time = date.getTime();
    if (Number.isNaN(time)) return '';
    const diff = Math.max(0, now - time);
    if (diff < MINUTE) return t('dates.relative.now');
    if (diff < HOUR) return t('dates.relative.minutes', { count: Math.floor(diff / MINUTE) }, Math.floor(diff / MINUTE));
    if (diff < DAY) return t('dates.relative.hours', { count: Math.floor(diff / HOUR) }, Math.floor(diff / HOUR));
    if (diff < 7 * DAY) return t('dates.relative.days', { count: Math.floor(diff / DAY) }, Math.floor(diff / DAY));
    return t('dates.relative.on_date', { date: dateLabel(date, locale) });
}

/** Temps restant d'un sondage (x_poll_remaining) : terminé, sans fin, minutes, heures ou jours. */
export function pollRemaining(endsAt: string | null, closed: boolean, t: Translate, now: number = Date.now()): string {
    if (closed) return t('feed.poll.closed');
    if (!endsAt) return t('feed.poll.no_end');
    const diff = new Date(endsAt).getTime() - now;
    if (Number.isNaN(diff)) return t('feed.poll.no_end');
    if (diff <= 0) return t('feed.poll.closed');
    if (diff < HOUR) return t('feed.poll.ends_in_minutes', { count: Math.max(1, Math.floor(diff / MINUTE)) });
    if (diff < DAY) return t('feed.poll.ends_in_hours', { count: Math.floor(diff / HOUR) });
    return t('feed.poll.ends_in_days', { count: Math.floor(diff / DAY) });
}

export interface ShareLink {
    key: 'whatsapp' | 'telegram' | 'facebook';
    icon: string;
    href: string;
}

/** Liens de partage (P7-05) : le lien neutre seulement, sans texte ni pseudonyme. */
export function shareLinks(shareUrl: string): ShareLink[] {
    const url = encodeURIComponent(shareUrl);
    return [
        { key: 'whatsapp', icon: 'whatsapp', href: `https://wa.me/?text=${url}` },
        { key: 'telegram', icon: 'telegram', href: `https://t.me/share/url?url=${url}` },
        { key: 'facebook', icon: 'facebook', href: `https://www.facebook.com/sharer/sharer.php?u=${url}` },
    ];
}
