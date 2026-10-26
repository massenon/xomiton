import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/alerts/presentation/alerts_pages.dart';
import '../features/alerts/presentation/report_page.dart';
import '../features/alerts/presentation/sos_page.dart';
import '../features/auth/application/onboarding_provider.dart';
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
import '../features/profile/presentation/profile_page.dart';
import '../features/profile/presentation/progressive_page.dart';
import '../features/profile/presentation/security_page.dart';
import '../features/profile/presentation/settings_page.dart';
import '../features/shell/presentation/shell_pages.dart';

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

/// Redirection : onboarding vu ? session ouverte ?
///
/// - session ouverte : jamais sur les écrans invités ; l'inscription (phases
///   2 à 4) et l'OTP d'inscription restent ouverts, la session existant déjà
///   après la phase 1 ;
/// - sans session : le carrousel d'abord (une seule fois), puis la connexion.
///   Les écrans SOS / signalement / suivi / urgences restent accessibles sans
///   compte (RG-01) : voir [kPublicPrefixes]. Le SOS d'un·e membre est l'onglet
///   `/sos` ; sans session, `/sos` mène à l'écran plein `/alerte-sos`.
String? appRedirect({
  required bool authenticated,
  required bool onboardingSeen,
  required Uri uri,
  Set<String> publicPrefixes = kPublicPrefixes,
}) {
  final loc = uri.path;
  if (!authenticated && loc == '/sos') return '/alerte-sos';
  if (authenticated) {
    if (loc == '/alerte-sos') return '/sos';
    if (_guestOnly.contains(loc)) return '/home';
    if (loc == '/otp' && uri.queryParameters['purpose'] != 'signup') {
      return '/home';
    }
    return null;
  }
  if (loc == '/splash') return onboardingSeen ? '/login' : null;
  if (_guestOnly.contains(loc) || loc == '/signup' || loc == '/otp') {
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

  final router = GoRouter(
    navigatorKey: rootNavigatorKey,
    initialLocation: '/splash',
    refreshListenable: refresh,
    redirect: (context, state) => appRedirect(
      authenticated: ref.read(sessionProvider).authenticated,
      onboardingSeen: ref.read(onboardingProvider).seen,
      uri: state.uri,
    ),
    routes: [
      GoRoute(path: '/splash', builder: (_, _) => const SplashCarouselPage()),
      GoRoute(path: '/login', builder: (_, _) => const LoginPage()),
      GoRoute(path: '/signup', builder: (_, _) => const SignupPage()),
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
