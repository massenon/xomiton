<script setup lang="ts">
// Sidenav de l'espace membre (P6-03) : portage de prototype partials/sidenav.php en col-lg-3, offcanvas sous 992 px
// (bouton « Menu »). Carte profil (couverture, avatar, pseudo, département du profil, compteurs réels : publications,
// abonné·es, abonnements — props partagées `shell.stats`), sections Réseau social · Apprendre · Aide & protection ·
// Pair animateur·rice (si `peer_space.access`) · Compte (paramètres, aide, administration si `shell.admin_url`,
// déconnexion par app/logout.ts), liens de pied. Lien actif : classe `active` + `aria-current="page"`. L'offcanvas est
// refermé au début de chaque visite Inertia (le sidenav persiste d'une page à l'autre) ; `aria-expanded` du bouton
// « Menu » suit son ouverture (évènements show / hidden de Bootstrap).
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { loadedBootstrap } from '@/shared/bootstrap-lazy.js';
import { logout } from '@/app/logout';
import { route } from '@/app/routes';
import { ACCOUNT_LINKS, LINKS, SIDENAV_SECTIONS, allowed, hrefOf, isActive, type NavLink } from '@/app/memberNav';
import { useSessionStore } from '@/stores/session';
import XmAvatar from './XmAvatar.vue';

const COVER = '/assets/images/xomiton/profile-cover.jpg';

const { t } = useI18n();
const page = usePage();
const session = useSessionStore();
const offcanvas = ref<HTMLElement | null>(null);

const url = computed(() => page.url ?? '');
const user = computed(() => session.user);
const can = (ability: string) => session.can(ability);
const active = (item: NavLink) => isActive(item, url.value);
const tag = (item: NavLink) => (item.inertia ? Link : 'a');
const sections = computed(() => SIDENAV_SECTIONS.filter((section) => allowed(section, can)));
const stats = computed(() => session.shell?.stats ?? { posts: 0, followers: 0, following: 0 });
const zone = computed(() => session.shell?.zone || t('menus.member_more.country'));
const year = new Date().getFullYear();
const aboutHref = `${route('home', undefined, '/')}#valeurs`;
const privacyHref = route('confidentialite', undefined, '/confidentialite');

/** Bootstrap chargé à la demande (shared/bootstrap-lazy.js) : un offcanvas ouvert implique Bootstrap chargé. */
function close() {
    const el = offcanvas.value;
    if (el?.classList.contains('show')) loadedBootstrap()?.Offcanvas.getOrCreateInstance(el).hide();
}

/** État du bouton « Menu » (aria-expanded) : Bootstrap Offcanvas ne le tient pas à jour, ses évènements le font. */
const expanded = ref(false);
const onShow = () => { expanded.value = true; };
const onHidden = () => { expanded.value = false; };

let offStart: (() => void) | null = null;
onMounted(() => {
    offStart = router.on('start', close);
    offcanvas.value?.addEventListener('show.bs.offcanvas', onShow);
    offcanvas.value?.addEventListener('hidden.bs.offcanvas', onHidden);
});
onBeforeUnmount(() => {
    offStart?.();
    offcanvas.value?.removeEventListener('show.bs.offcanvas', onShow);
    offcanvas.value?.removeEventListener('hidden.bs.offcanvas', onHidden);
});

const onLogout = () => { logout(); };

defineExpose({ close });
</script>

<template>
    <div id="memberSidenav" class="col-lg-3">
        <div class="d-flex align-items-center d-lg-none mb-3">
            <button class="border-0 bg-transparent d-flex align-items-center" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSideNavbar" aria-controls="offcanvasSideNavbar" :aria-expanded="expanded ? 'true' : 'false'">
                <span class="btn btn-primary"><i class="bi bi-sliders" aria-hidden="true"></i></span>
                <span class="h6 mb-0 fw-bold ms-2">{{ t('menus.menu') }}</span>
            </button>
        </div>

        <nav class="navbar navbar-expand-lg mx-0" :aria-label="t('menus.a11y.member_sidenav')">
            <div id="offcanvasSideNavbar" ref="offcanvas" class="offcanvas offcanvas-start" tabindex="-1" :aria-label="t('menus.a11y.member_sidenav')">
                <div class="offcanvas-header">
                    <button type="button" class="btn-close text-reset ms-auto" data-bs-dismiss="offcanvas" :aria-label="t('menus.a11y.close_menu')"></button>
                </div>

                <div class="offcanvas-body d-block px-2 px-lg-0">
                    <div class="card overflow-hidden">
                        <div class="h-50px" :style="{ backgroundImage: `url('${COVER}')`, backgroundPosition: 'center', backgroundSize: 'cover' }"></div>
                        <div class="card-body pt-0">
                            <div class="text-center">
                                <Link :href="hrefOf(LINKS.profil)" class="d-inline-block mt-n5 mb-3" tabindex="-1" aria-hidden="true"><XmAvatar :name="user?.pseudo ?? ''" :src="user?.avatar_url" size="lg" rounded="2" class="border border-white border-3 rounded" /></Link>
                                <p class="h5 mb-0"><Link :href="hrefOf(LINKS.profil)">{{ user?.pseudo }}</Link></p>
                                <small>{{ t('profile.pseudo', { pseudo: user?.pseudo ?? '' }) }} · {{ zone }}</small>
                                <div class="hstack gap-2 gap-xl-3 justify-content-center mt-3" data-xm-profile-stats>
                                    <div><p class="h6 mb-0">{{ stats.posts }}</p><small>{{ t('menus.member_more.stats_posts') }}</small></div>
                                    <div class="vr"></div>
                                    <div><p class="h6 mb-0">{{ stats.followers }}</p><small>{{ t('menus.member_more.stats_followers') }}</small></div>
                                    <div class="vr"></div>
                                    <div><p class="h6 mb-0">{{ stats.following }}</p><small>{{ t('menus.member_more.stats_following') }}</small></div>
                                </div>
                            </div>
                            <hr>

                            <div class="d-flex flex-column gap-1">
                                <template v-for="(section, index) in sections" :key="section.id">
                                    <div :class="['xm-nav-section', { 'pt-0': index === 0 }]">{{ t(section.title) }}</div>
                                    <component :is="tag(item)" v-for="item in section.links" :key="item.key" :class="['xm-side-link', { active: active(item), danger: item.danger }]" :href="hrefOf(item)" :aria-current="active(item) ? 'page' : undefined"><i :class="`bi bi-${item.icon}`" aria-hidden="true"></i><span>{{ t(item.label) }}</span></component>
                                </template>

                                <div class="xm-nav-section">{{ t('menus.member_sections.account') }}</div>
                                <component :is="tag(item)" v-for="item in ACCOUNT_LINKS" :key="item.key" :class="['xm-side-link', { active: active(item) }]" :href="hrefOf(item)" :aria-current="active(item) ? 'page' : undefined"><i :class="`bi bi-${item.icon}`" aria-hidden="true"></i><span>{{ t(item.label) }}</span></component>
                                <a v-if="session.shell?.admin_url" class="xm-side-link" :href="session.shell.admin_url"><i class="bi bi-speedometer2" aria-hidden="true"></i><span>{{ t('menus.member.administration') }}</span></a>
                                <button type="button" class="xm-side-link danger border-0 bg-transparent w-100 text-start" data-xm-logout @click="onLogout"><i class="bi bi-power" aria-hidden="true"></i><span>{{ t('menus.member.logout') }}</span></button>
                            </div>
                        </div>
                    </div>

                    <ul class="nav small mt-3 justify-content-center lh-1" :aria-label="t('menus.a11y.footer_nav')">
                        <li class="nav-item"><a class="nav-link" :href="aboutHref">{{ t('menus.member_more.about') }}</a></li>
                        <li class="nav-item"><a class="nav-link" :href="privacyHref">{{ t('menus.member_more.privacy_terms') }}</a></li>
                    </ul>
                    <p class="small text-center mt-1">{{ t('menus.member_more.copyright', { year }) }}</p>
                </div>
            </div>
        </nav>
    </div>
</template>
