import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/platform/platform_services.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../../core/util/html_text.dart';
import '../application/home_providers.dart';
import '../data/home_models.dart';
import 'theme_card.dart';

class ThemesPage extends ConsumerStatefulWidget {
  const ThemesPage({super.key});

  @override
  ConsumerState<ThemesPage> createState() => _ThemesPageState();
}

class _ThemesPageState extends ConsumerState<ThemesPage> {
  String? _categorie;
  String _q = '';
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final query = ThemesQuery(categorie: _categorie, q: _q);
    final list = ref.watch(themesProvider(query));
    // Garde les puces de catégorie même pendant le rechargement.
    final cats = list.value?.categories ?? const <Category>[];
    return Scaffold(
      appBar: AppBar(title: Text(context.t('pages_thematiques_title'))),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              XmSpace.gutter,
              XmSpace.s2,
              XmSpace.gutter,
              XmSpace.s2,
            ),
            child: TextField(
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.search),
                hintText: context.t('pages_thematiques_search_placeholder'),
                labelText: context.t('pages_thematiques_search_label'),
              ),
              onChanged: (v) {
                _debounce?.cancel();
                _debounce = Timer(
                  const Duration(milliseconds: 300),
                  () => setState(() => _q = v.trim()),
                );
              },
            ),
          ),
          if (cats.isNotEmpty)
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(
                horizontal: XmSpace.gutter,
                vertical: 6,
              ),
              child: Row(
                children: [
                  _CategoryChip(
                    label: context.t('pages_thematiques_filter_all', {
                      'count': list.value?.total ?? 0,
                    }),
                    selected: _categorie == null,
                    onTap: () => setState(() => _categorie = null),
                  ),
                  for (final c in cats)
                    _CategoryChip(
                      label: c.count == null
                          ? c.label
                          : '${c.label} (${c.count})',
                      selected: _categorie == c.slug,
                      onTap: () => setState(() => _categorie = c.slug),
                    ),
                ],
              ),
            ),
          Expanded(
            child: list.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(
                error: e,
                onRetry: () => ref.invalidate(themesProvider(query)),
              ),
              data: (d) => d.items.isEmpty
                  ? EmptyView(
                      context.t(
                        _q.isEmpty && _categorie == null
                            ? 'pages_thematiques_empty_none'
                            : 'pages_thematiques_empty_title',
                      ),
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.all(XmSpace.gutter),
                      itemCount: d.items.length + (d.stale ? 1 : 0),
                      separatorBuilder: (_, _) =>
                          const SizedBox(height: XmSpace.s3),
                      itemBuilder: (context, i) {
                        if (d.stale && i == 0) {
                          return Text(
                            context.t('mobile_stale_notice'),
                            style: Theme.of(context).textTheme.labelSmall,
                          );
                        }
                        return ThemeCard(theme: d.items[i - (d.stale ? 1 : 0)]);
                      },
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(right: XmSpace.s2),
    child: ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onTap(),
    ),
  );
}

class ThemeDetailPage extends ConsumerWidget {
  const ThemeDetailPage({super.key, required this.slug});
  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(themeDetailProvider(slug));
    return Scaffold(
      appBar: AppBar(),
      body: detail.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          error: e,
          onRetry: () => ref.invalidate(themeDetailProvider(slug)),
        ),
        data: (d) => _Detail(d: d),
      ),
    );
  }
}

class _Detail extends StatelessWidget {
  const _Detail({required this.d});
  final ThemeDetailData d;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    final c = d.card;
    final body = htmlToText(d.body);
    return ListView(
      padding: const EdgeInsets.only(bottom: XmSpace.s7),
      children: [
        if (c.coverUrl != null)
          SizedBox(
            height: 180,
            width: double.infinity,
            child: CoverImage(c.coverUrl, height: 180),
          ),
        Padding(
          padding: const EdgeInsets.all(XmSpace.gutter),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (c.category != null) Chip(label: Text(c.category!.label)),
              Semantics(
                header: true,
                child: Text(c.title, style: t.headlineSmall),
              ),
              if (c.readingMinutes != null)
                Padding(
                  padding: const EdgeInsets.only(top: XmSpace.s1),
                  child: Text(
                    context.t('pages_thematiques_reading_minutes', {
                      'count': c.readingMinutes,
                    }),
                    style: t.labelSmall,
                  ),
                ),
              if (c.summary != null)
                Padding(
                  padding: const EdgeInsets.only(top: XmSpace.s3),
                  child: Text(c.summary!, style: t.bodyLarge),
                ),
              if (d.contentWarning != null && d.contentWarning!.isNotEmpty) ...[
                const SizedBox(height: XmSpace.s4),
                XmAlert(
                  d.contentWarning!,
                  icon: Icons.info_outline,
                  tone: context.xm.info,
                ),
              ],
              if (body.isNotEmpty) ...[
                const SizedBox(height: XmSpace.s4),
                Text(body),
              ],
              const SizedBox(height: XmSpace.s4),
              for (var i = 0; i < d.sections.length; i++)
                Card(
                  margin: const EdgeInsets.only(bottom: XmSpace.s2),
                  clipBehavior: Clip.antiAlias,
                  child: ExpansionTile(
                    initiallyExpanded: i == 0,
                    title: Text(d.sections[i].title, style: t.titleSmall),
                    childrenPadding: const EdgeInsets.fromLTRB(
                      XmSpace.s4,
                      0,
                      XmSpace.s4,
                      XmSpace.s4,
                    ),
                    expandedCrossAxisAlignment: CrossAxisAlignment.start,
                    children: [Text(htmlToText(d.sections[i].body))],
                  ),
                ),
              if (d.faqs.isNotEmpty) ...[
                const SizedBox(height: XmSpace.s4),
                Text(
                  context.t('pages_thematiques_faq_title'),
                  style: t.titleMedium,
                ),
                const SizedBox(height: XmSpace.s2),
                for (final f in d.faqs)
                  Card(
                    margin: const EdgeInsets.only(bottom: XmSpace.s2),
                    clipBehavior: Clip.antiAlias,
                    child: ExpansionTile(
                      title: Text(f.question, style: t.titleSmall),
                      childrenPadding: const EdgeInsets.fromLTRB(
                        XmSpace.s4,
                        0,
                        XmSpace.s4,
                        XmSpace.s4,
                      ),
                      expandedCrossAxisAlignment: CrossAxisAlignment.start,
                      children: [Text(htmlToText(f.answer))],
                    ),
                  ),
              ],
              if (d.contacts.isNotEmpty) ...[
                const SizedBox(height: XmSpace.s4),
                Semantics(
                  header: true,
                  child: Text(
                    context.t('pages_thematiques_contacts_label'),
                    style: t.titleMedium,
                  ),
                ),
                const SizedBox(height: XmSpace.s2),
                for (final k in d.contacts) _ContactTile(contact: k),
              ],
              if (d.primaryResource != null) ...[
                const SizedBox(height: XmSpace.s4),
                Text(
                  context.t('pages_thematiques_resource_title'),
                  style: t.titleMedium,
                ),
                const SizedBox(height: XmSpace.s2),
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.description_outlined),
                    title: Text(d.primaryResource!.title),
                    subtitle: d.primaryResource!.fileSizeLabel == null
                        ? null
                        : Text(d.primaryResource!.fileSizeLabel!),
                    onTap: () => context.push('/ressources'),
                  ),
                ),
              ],
              if (d.related.isNotEmpty) ...[
                const SizedBox(height: XmSpace.s4),
                Text(
                  context.t('pages_thematiques_related_title'),
                  style: t.titleMedium,
                ),
                const SizedBox(height: XmSpace.s2),
                for (final r in d.related)
                  Padding(
                    padding: const EdgeInsets.only(bottom: XmSpace.s2),
                    child: ThemeCard(theme: r),
                  ),
              ],
              const SizedBox(height: XmSpace.s4),
              Text(
                context.t('pages_thematiques_help_title'),
                style: t.titleMedium,
              ),
              const SizedBox(height: XmSpace.s2),
              OutlinedButton(
                onPressed: () => context.push('/soon/emergency'),
                child: Text(context.t('pages_thematiques_link_emergency')),
              ),
              const SizedBox(height: XmSpace.s2),
              OutlinedButton(
                onPressed: () => context.push('/soon/sos'),
                child: Text(context.t('pages_thematiques_link_sos')),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _ContactTile extends ConsumerWidget {
  const _ContactTile({required this.contact});
  final Contact contact;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final phone = contact.phone;
    return Card(
      margin: const EdgeInsets.only(bottom: XmSpace.s2),
      child: ListTile(
        title: Text(contact.name),
        subtitle: Text(
          [
            if (contact.roleLabel != null) contact.roleLabel!,
            if (contact.org != null) contact.org!,
            if (contact.availability != null) contact.availability!,
          ].join(' ${context.t('common_separator')} '),
        ),
        trailing: phone == null
            ? null
            : IconButton(
                tooltip:
                    '${context.t('pages_thematiques_call')} ${contact.name}',
                icon: const Icon(Icons.call_outlined),
                onPressed: () =>
                    ref.read(linkLauncherProvider).open(telUri(phone)),
              ),
      ),
    );
  }
}
