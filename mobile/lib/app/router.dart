import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/auth_spaces/app_space.dart';
import '../core/auth_spaces/presentation/space_widgets.dart';
import '../core/auth_spaces/space_providers.dart';
import '../features/alerts/presentation/alerts_pages.dart';
import '../features/alerts/presentation/report_page.dart';
import '../features/alerts/presentation/sos_page.dart';
import '../features/auth/application/onboarding_provider.dart';
import '../features/security/application/pin_lock.dart';
import '../features/security/presentation/pin_setup_page.dart';
import '../features/community/presentation/community_pages.dart';
import '../features/community/presentation/messages_pages.dart';
import '../features/feed/presentation/feed_pages.dart';
import '../features/auth/application/session_controller.dart';
import '../features/auth/data/auth_api.dart';
import '../features/auth/presentation/login_page.dart';
import '../features/auth/presentation/otp_page.dart';
import '../features/auth/presentation/password_pages.dart';
import '../features/auth/presentation/signup_page.dart';
import '../features/auth/presentation/splash_carousel_page.dart';
import '../features/home/presentation/dashboard_page.dart';
import '../features/home/presentation/notifications_page.dart';
import '../features/home/presentation/resources_page.dart';
import '../features/home/presentation/themes_pages.dart';
import '../features/pair/presentation/pair_alerts_pages.dart';
import '../features/pair/presentation/pair_cases_pages.dart';
import '../features/pair/presentation/pair_collect_page.dart';
import '../features/pair/presentation/pair_home_page.dart';
import '../features/pair/presentation/pair_sync_pages.dart';
import '../features/profile/presentation/profile_page.dart';
import '../features/profile/presentation/progressive_page.dart';
import '../features/profile/presentation/security_page.dart';
import '../features/profile/presentation/settings_page.dart';
import '../features/shell/presentation/shell_pages.dart';
import '../features/alerts/presentation/sos_page.dart' show SosPage;
import 'shells/space_shells.dart';

final rootNavigatorKey = GlobalKey<NavigatorState>(debugLabel: 'root');

/// Parcours de sécurité accessibles SANS compte (RG-01, P15-04).
const Set<String> kPublicPrefixes = {
  '/alerte-sos',
  '/signaler',
  '/suivi',
  '/urgences',
};

/// Écrans réservés aux personnes sans session.
const _guestOnly = {'/splash', '/login', '/forgot', '/reset'};

/// Redirection (plan §5.3). Ordre : (1) onboarding, (2) session, (2 bis) juste
/// après la connexion, `/spaces` si le compte a plus d'un espace, (4) garde
/// d'espace : `/pair/**` n'est ouvert que si le SERVEUR a déclaré l'espace
/// pair au compte (`spaces` de `GET me`), `/admin/**` idem, sinon retour à
/// l'accueil Membre. Le verrou PIN (3) est posé par `PinGate`, au-dessus du routeur.
///
/// (4 bis) code PIN : `/pin-setup` tant qu'aucun code n'existe pour entrer en Pair ou Admin.
///
/// - session ouverte : jamais sur les écrans invités ; l'inscription (phases
///   2 à 4) et l'OTP d'inscription restent ouverts, la session existant déjà
///   après la phase 1 ;
/// - sans session : le carrousel d'abord (une seule fois), puis la connexion.
///   Les écrans SOS / signalement / suivi / urgences restent accessibles sans
///   compte (RG-01) : voir [kPublicPrefixes]. Le SOS d'un·e membre est l'onglet
///   `/sos` ; sans session, `/sos` mène à l'écran plein `/alerte-sos`.
///
/// Garde de confort seulement : chaque route serveur `pair/*` et `admin/*`
/// revérifie (403), le client n'est jamais une frontière de sécurité.
String? appRedirect({
  required bool authenticated,
  required bool onboardingSeen,
  required Uri uri,
  Set<String> publicPrefixes = kPublicPrefixes,
  Set<AppSpace> spaces = const {AppSpace.member},
  AppSpace activeSpace = AppSpace.member,
  bool spaceChoicePending = false,
  bool pinSetupNeeded = false,
}) {
  final loc = uri.path;
  if (!authenticated && loc == '/sos') return '/alerte-sos';
  if (authenticated) {
    final several = spaces.length > 1;
    // Arrivée après connexion : sélecteur d'espace (compte à plusieurs espaces),
    // sinon dernier espace actif s'il est toujours déclaré, sinon Membre.
    final arrival = several && spaceChoicePending
        ? '/spaces'
        : (spaces.contains(activeSpace) ? activeSpace : AppSpace.member).homeRoute;
    if (loc == '/alerte-sos') return '/sos';
    if (_guestOnly.contains(loc)) return arrival;
    if (loc == '/otp' && uri.queryParameters['purpose'] != 'signup') {
      return arrival;
    }
    if (loc == '/spaces') return several ? null : AppSpace.member.homeRoute;
    final owner = AppSpace.forPath(loc);
    if (owner != null && !spaces.contains(owner)) return AppSpace.member.homeRoute;
    // Code PIN obligatoire pour entrer dans l'espace Pair ou Admin (D-MOB-6) :
    // tant qu'aucun code n'existe, on choisit d'abord le code.
    if (spaceRequiresPin(owner) && pinSetupNeeded) {
      return '/pin-setup?from=${Uri.encodeQueryComponent(uri.toString())}';
    }
    if (loc == '/pin-setup' && !pinSetupNeeded && uri.queryParameters['from'] != null) {
      return uri.queryParameters['from'];
    }
    return null;
  }
  if (loc == '/splash') return onboardingSeen ? '/login' : null;
  if (_guestOnly.contains(loc) || loc == '/signup' || loc.startsWith('/signup/') || loc == '/otp') {
    return null;
  }
  if (publicPrefixes.any(loc.startsWith)) return null;
  return onboardingSeen ? '/login' : '/splash';
}

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = ValueNotifier<int>(0);
  ref.onDispose(refresh.dispose);
  ref.listen(sessionProvider, (_, _) => refresh.value++);
  ref.listen(onboardingProvider, (_, _) => refresh.value++);
  ref.listen(activeSpaceProvider, (_, _) => refresh.value++);
  ref.listen(pinLockProvider.select((s) => s.ready && !s.configured), (_, _) => refresh.value++);

  final router = GoRouter(
    navigatorKey: rootNavigatorKey,
    initialLocation: '/splash',
    refreshListenable: refresh,
    redirect: (context, state) {
      final session = ref.read(sessionProvider);
      final pin = ref.read(pinLockProvider);
      return appRedirect(
        authenticated: session.authenticated,
        onboardingSeen: ref.read(onboardingProvider).seen,
        uri: state.uri,
        spaces: ref.read(spacesProvider).toSet(),
        activeSpace: ref.read(activeSpaceProvider),
        spaceChoicePending: session.spaceChoicePending,
        pinSetupNeeded: pin.ready && !pin.configured,
      );
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, _) => const SplashCarouselPage()),
      GoRoute(path: '/login', builder: (_, _) => const LoginPage()),
      GoRoute(path: '/signup', builder: (_, _) => const SignupPage()),
      // Lien d'invitation à l'équipe : mêmes écrans que l'inscription publique (membre).
      GoRoute(
        path: '/signup/:invitation',
        builder: (_, state) => SignupPage(invitation: state.pathParameters['invitation']),
      ),
      GoRoute(path: '/spaces', builder: (_, _) => const SpaceSelectorPage()),
      GoRoute(
        path: '/pin-setup',
        builder: (_, state) => PinSetupPage(from: state.uri.queryParameters['from']),
      ),
      GoRoute(
        path: '/otp',
        builder: (_, state) => OtpPage(
          purpose: OtpPurpose.values.firstWhere(
            (p) => p.name == state.uri.queryParameters['purpose'],
            orElse: () => OtpPurpose.login,
          ),
          email: state.uri.queryParameters['email'] ?? '',
        ),
      ),
      GoRoute(path: '/forgot', builder: (_, _) => const ForgotPasswordPage()),
      GoRoute(
        path: '/reset',
        // La preuve de réinitialisation voyage en `extra` (jamais dans l'URL).
        redirect: (_, state) => state.extra is ResetArgs ? null : '/forgot',
        builder: (_, state) =>
            ResetPasswordPage(args: state.extra! as ResetArgs),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => AppShell(navigationShell: shell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(path: '/home', builder: (_, _) => const DashboardPage()),
              GoRoute(
                path: '/thematiques',
                builder: (_, _) => const ThemesPage(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(path: '/feed', builder: (_, _) => const FeedTabPage()),
            ],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: '/sos', builder: (_, _) => const SosPage())],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/messages',
                builder: (_, _) => const MessagesTabPage(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/profile',
                builder: (_, _) => const ProfileTabPage(),
              ),
            ],
          ),
        ],
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => SpaceNavShell(
          space: AppSpace.peer,
          tabs: kPeerTabs,
          navLabelKey: 'mobile_peer_nav_label',
          navigationShell: shell,
        ),
        branches: [
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/pair/accueil',
              builder: (_, _) => const PairHomePage(),
              routes: [
                GoRoute(
                  path: 'synchro',
                  builder: (_, _) => const PairSyncPage(),
                  routes: [
                    GoRoute(
                      path: 'conflit/:id',
                      builder: (_, state) => PairConflictPage(id: int.tryParse(state.pathParameters['id'] ?? '') ?? 0),
                    ),
                  ],
                ),
              ],
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/pair/alertes',
              builder: (_, _) => const PairAlertsPage(),
              routes: [
                GoRoute(path: ':id', builder: (_, state) => PairAlertDetailPage(id: state.pathParameters['id']!)),
              ],
            ),
          ]),
          StatefulShellBranch(routes: [GoRoute(path: '/pair/sos', builder: (_, _) => const SosPage())]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/pair/dossiers',
              builder: (_, _) => const PairCasesPage(),
              routes: [
                // Avant `:code`, sinon « collecte » serait lu comme un code de dossier.
                GoRoute(path: 'collecte', builder: (_, _) => const PairCollectPage()),
                GoRoute(
                  path: ':code',
                  builder: (_, state) => PairCasePage(code: state.pathParameters['code']!),
                  routes: [
                    GoRoute(
                      path: 'suivi',
                      builder: (_, state) => PairFollowupFormPage(code: state.pathParameters['code']!),
                    ),
                  ],
                ),
              ],
            ),
          ]),
        ],
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => SpaceNavShell(
          space: AppSpace.admin,
          tabs: kAdminTabs,
          navLabelKey: 'mobile_admin_nav_label',
          navigationShell: shell,
        ),
        branches: [
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/admin/astreinte',
              builder: (_, _) => const SpacePlaceholderPage(space: AppSpace.admin, titleKey: 'mobile_admin_tab_duty'),
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/admin/alertes',
              builder: (_, _) => const SpacePlaceholderPage(space: AppSpace.admin, titleKey: 'mobile_admin_tab_alerts'),
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/admin/gerer',
              builder: (_, _) => const SpacePlaceholderPage(space: AppSpace.admin, titleKey: 'mobile_admin_tab_manage'),
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/admin/notifications',
              builder: (_, _) =>
                  const SpacePlaceholderPage(space: AppSpace.admin, titleKey: 'mobile_admin_tab_notifications'),
            ),
          ]),
        ],
      ),
      GoRoute(
        path: '/thematiques/:slug',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) =>
            ThemeDetailPage(slug: state.pathParameters['slug']!),
      ),
      GoRoute(
        path: '/ressources',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const ResourcesPage(),
      ),
      GoRoute(
        path: '/notifications',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const NotificationsPageScreen(),
      ),
      GoRoute(
        path: '/alerte-sos',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const SosPage(standalone: true),
      ),
      GoRoute(
        path: '/signaler',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) =>
            ReportPage(initialType: state.uri.queryParameters['type']),
      ),
      GoRoute(
        path: '/suivi',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const TrackingPage(),
      ),
      GoRoute(
        path: '/urgences',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const EmergencyPage(),
      ),
      GoRoute(
        path: '/signalements',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const MyReportsPage(),
      ),
      GoRoute(
        path: '/feed/new',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const CreatePostPage(),
      ),
      GoRoute(
        path: '/feed/:id',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) => PostDetailPage(id: state.pathParameters['id']!),
      ),
      GoRoute(
        path: '/messages/new',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const NewConversationPage(),
      ),
      GoRoute(
        path: '/messages/:id',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) =>
            ConversationPage(id: state.pathParameters['id']!),
      ),
      GoRoute(
        path: '/groupes',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const GroupsPage(),
      ),
      GoRoute(
        path: '/groupes/:id',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) => GroupDetailPage(id: state.pathParameters['id']!),
      ),
      GoRoute(
        path: '/evenements',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const EventsPage(),
      ),
      GoRoute(
        path: '/evenements/:id',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) => EventDetailPage(id: state.pathParameters['id']!),
      ),
      GoRoute(
        path: '/assistant',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const AssistantPage(),
      ),
      for (final r in <(String, Widget)>[
        ('/mon-profil', const MyProfilePage()),
        ('/profil-progressif', const ProgressiveProfilePage()),
        ('/parametres', const SettingsPage()),
        ('/securite', const SecurityPage()),
        ('/appareils', const DevicesPage()),
      ])
        GoRoute(
          path: r.$1,
          parentNavigatorKey: rootNavigatorKey,
          builder: (_, _) => r.$2,
        ),
      GoRoute(
        path: '/soon/:page',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) => ComingSoonPage(
          pageKey: state.pathParameters['page']!,
          showBack: true,
        ),
      ),
    ],
  );
  ref.onDispose(router.dispose);
  return router;
});
