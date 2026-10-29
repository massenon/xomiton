import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../data/account_api.dart';

final newsletterProvider = FutureProvider.autoDispose<NewsletterPrefs>(
  (ref) => ref.watch(accountApiProvider).newsletter(),
);

/// Newsletter (maquette « newsletter ») : abonnement et centres d'intérêt.
/// L'abonnement depuis l'application vaut consentement explicite ; le schéma
/// n'a pas de fréquence (docs/API.md §3), le champ de la maquette n'est donc
/// pas proposé.
class NewsletterPage extends ConsumerStatefulWidget {
  const NewsletterPage({super.key});

  @override
  ConsumerState<NewsletterPage> createState() => _NewsletterPageState();
}

class _NewsletterPageState extends ConsumerState<NewsletterPage> {
  bool _busy = false;

  void _toast(String text) => ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(text)));

  Future<void> _save({bool? subscribed, Set<String>? interests, String? done}) async {
    setState(() => _busy = true);
    try {
      await ref.read(accountApiProvider).patchNewsletter(subscribed: subscribed, interests: interests);
      ref.invalidate(newsletterProvider);
      await ref.read(newsletterProvider.future);
      if (mounted) _toast(done ?? context.t('mobile_newsletter_saved'));
    } catch (e) {
      // 422 newsletter_bounced / email_missing / not_subscribed : texte du serveur.
      if (mounted) _toast(errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final prefs = ref.watch(newsletterProvider);
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(context.t('mobile_newsletter_title'))),
      body: prefs.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(newsletterProvider)),
        data: (p) => ListView(
          padding: const EdgeInsets.all(XmSpace.gutter),
          children: [
            SwitchListTile(
              key: const Key('newsletter-switch'),
              contentPadding: EdgeInsets.zero,
              title: Text(context.t('account_newsletter_subscribed')),
              subtitle: Text(context.t('mobile_newsletter_help')),
              value: p.subscribed,
              onChanged: _busy
                  ? null
                  : (v) => _save(
                      subscribed: v,
                      done: context.t(
                        v ? (p.pendingConfirmation ? 'mobile_newsletter_pending' : 'mobile_newsletter_on') : 'mobile_newsletter_off',
                      ),
                    ),
            ),
            if (p.pendingConfirmation) ...[
              const SizedBox(height: XmSpace.s2),
              XmAlert(context.t('mobile_newsletter_pending'), icon: Icons.mark_email_unread_outlined, tone: context.xm.info),
            ],
            const SizedBox(height: XmSpace.s4),
            Semantics(header: true, child: Text(context.t('mobile_newsletter_interests'), style: theme.textTheme.titleMedium)),
            const SizedBox(height: XmSpace.s2),
            if (!p.subscribed)
              Text(context.t('account_newsletter_not_subscribed'), style: theme.textTheme.bodySmall),
            Wrap(
              spacing: XmSpace.s2,
              runSpacing: XmSpace.s2,
              children: [
                for (final o in p.options)
                  FilterChip(
                    key: Key('newsletter-interest-${o.slug}'),
                    label: Text(o.label),
                    selected: p.interests.contains(o.slug),
                    onSelected: (_busy || !p.subscribed)
                        ? null
                        : (on) => _save(interests: on ? {...p.interests, o.slug} : ({...p.interests}..remove(o.slug))),
                  ),
              ],
            ),
            const SizedBox(height: XmSpace.s5),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.lock_outline, size: 16),
                const SizedBox(width: XmSpace.s2),
                Expanded(child: Text(context.t('mobile_newsletter_privacy'), style: theme.textTheme.bodySmall)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
