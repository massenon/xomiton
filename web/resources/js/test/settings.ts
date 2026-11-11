// Données types de l'écran /parametres (lot parametres-notifications, P8-09) : props de SettingsPageController.
export function settingsProps(overrides: Record<string, unknown> = {}): Record<string, unknown> {
    return {
        title: 'Paramètres',
        identity: { name: 'Awa Sossou', pseudo: 'Awa_S', email: 'awa@exemple.bj', phone: null, bio: 'Bonjour', zone_id: 4, commune_id: null },
        zones: [
            { id: 4, name: 'Zou', communes: [{ id: 41, name: 'Abomey' }, { id: 42, name: 'Bohicon' }] },
            { id: 5, name: 'Littoral', communes: [{ id: 51, name: 'Cotonou' }] },
        ],
        privacy: { visibility: 'community', show_zone: true, searchable: true, sos_share_gps: false, sensitive_consent: false },
        visibilities: [
            { value: 'public', label: 'Tout le monde' }, { value: 'community', label: 'Toute la communauté' },
            { value: 'followers', label: 'Mes abonné·es seulement' }, { value: 'private', label: 'Privé' },
        ],
        notifications: {
            prefs: {
                securite: { in_app: true, email: true, push: true },
                sos: { in_app: true, email: true, push: true },
                messages: { in_app: true, email: false, push: true },
                evenements: { in_app: true, email: true, push: false },
            },
            categories: [{ key: 'securite', locked: true }, { key: 'sos', locked: true }, { key: 'messages', locked: false }, { key: 'evenements', locked: false }],
            channels: ['in_app', 'email', 'push'],
            digest: 'weekly',
            digests: ['never', 'daily', 'weekly'],
        },
        security: { discreet: false, mask_previews: false, pin_enabled: false, neutral_tab_title: false },
        appearance: { theme: 'auto' },
        language: { current: 'fr', available: ['fr'], planned: ['en', 'fon', 'yo'] },
        newsletter: {
            subscribed: false, status: null, pending_confirmation: false, interests: [], confirmed_at: null,
            available_interests: [{ slug: 'sante', label: 'Santé' }, { slug: 'droits', label: 'Droits' }],
        },
        sessions: [
            { id: 'web-abc', kind: 'web', name: 'Firefox', ip: null, current: true, last_active_at: null, created_at: null },
            { id: 'token-9', kind: 'token', name: 'Pixel 7', ip: null, current: false, last_active_at: null, created_at: null },
        ],
        devices: [{ device_uuid: '7d9f0c1e-2b3a-4c5d-8e9f-0a1b2c3d4e5f', label: 'Pixel 7', platform: 'android', app_version: '1.0.0', revoked: false, last_seen_at: null, last_sync_at: null, created_at: null }],
        limits: { password_min: 10, name_max: 120, bio_max: 200, deletion_days: 30, export_hours: 48 },
        can: { delete_account: true },
        ...overrides,
    };
}
