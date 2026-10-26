import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/home_providers.dart';
import '../data/home_api.dart';
import '../data/home_models.dart';

class ResourcesPage extends ConsumerStatefulWidget {
  const ResourcesPage({super.key});

  @override
  ConsumerState<ResourcesPage> createState() => _ResourcesPageState();
}

class _ResourcesPageState extends ConsumerState<ResourcesPage> {
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
    final query = ResourcesQuery(categorie: _categorie, q: _q);
    final res = ref.watch(resourcesProvider(query));
    final cats = res.value?.categories ?? const <Category>[];
    return Scaffold(
      appBar: AppBar(title: Text(context.t('pages_ressources_title'))),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(XmSpace.gutter, XmSpace.s2, XmSpace.gutter, XmSpace.s2),
          child: TextField(
            textInputAction: TextInputAction.search,
            decoration: InputDecoration(
              prefixIcon: const Icon(Icons.search),
              hintText: context.t('pages_ressources_search_placeholder'),
              labelText: context.t('pages_ressources_search_label'),
            ),
            onChanged: (v) {
              _debounce?.cancel();
              _debounce = Timer(const Duration(milliseconds: 300), () => setState(() => _q = v.trim()));
            },
          ),
        ),
        if (cats.isNotEmpty)
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: XmSpace.gutter, vertical: 6),
            child: Row(
              children: [
                Padding(
                  padding: const EdgeInsets.only(right: XmSpace.s2),
                  child: ChoiceChip(
                    label: Text(context.t('pages_ressources_filter_all')),
                    selected: _categorie == null,
                    onSelected: (_) => setState(() => _categorie = null),
                  ),
                ),
                for (final c in cats)
                  Padding(
                    padding: const EdgeInsets.only(right: XmSpace.s2),
                    child: ChoiceChip(
                      label: Text(c.label),
                      selected: _categorie == c.slug,
                      onSelected: (_) => setState(() => _categorie = c.slug),
                    ),
                  ),
              ],
            ),
          ),
        Expanded(
          child: res.when(
            loading: () => const LoadingView(),
            error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(resourcesProvider(query))),
            data: (d) => d.items.isEmpty
                ? EmptyView(context.t(_q.isEmpty && _categorie == null
                    ? 'pages_ressources_empty_none'
                    : 'pages_ressources_empty_title'))
                : ListView.separated(
                    padding: const EdgeInsets.all(XmSpace.gutter),
                    itemCount: d.items.length + 1,
                    separatorBuilder: (_, _) => const SizedBox(height: XmSpace.s3),
                    itemBuilder: (context, i) {
                      if (i == d.items.length) {
                        return d.hasMore
                            ? OutlinedButton(
                                onPressed: () => ref.read(resourcesProvider(query).notifier).loadMore(),
                                child: Text(context.t('common_load_more')))
                            : Padding(
                                padding: const EdgeInsets.all(XmSpace.s3),
                                child: Text(
                                  context.t('pages_ressources_shown_of', {'shown': d.items.length, 'total': d.total}),
                                  textAlign: TextAlign.center,
                                  style: Theme.of(context).textTheme.labelSmall,
                                ),
                              );
                      }
                      return ResourceTile(resource: d.items[i]);
                    },
                  ),
          ),
        ),
      ]),
    );
  }
}

class ResourceTile extends ConsumerStatefulWidget {
  const ResourceTile({super.key, required this.resource});
  final ResourceCardData resource;

  @override
  ConsumerState<ResourceTile> createState() => _ResourceTileState();
}

class _ResourceTileState extends ConsumerState<ResourceTile> {
  bool _busy = false;

  Future<void> _download() async {
    final r = widget.resource;
    setState(() => _busy = true);
    try {
      Uri? uri;
      if (r.downloadable) {
        uri = await ref.read(homeApiProvider).resourceDownloadUrl(r.id);
      } else if (r.externalUrl != null) {
        uri = Uri.tryParse(r.externalUrl!);
      }
      final ok = uri != null && await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!ok && mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(context.t('mobile_resources_download_failed'))));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(errorMessage(context, e))));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final r = widget.resource;
    final t = Theme.of(context).textTheme;
    final size = r.fileSizeLabel ?? (r.downloadable ? context.t('mobile_resources_size_unknown') : null);
    final meta = [
      ?r.kindLabel,
      ?size,
      ?r.category?.label,
    ].join(' ${context.t('common_separator')} ');
    final canAct = r.downloadable || r.externalUrl != null;
    final actionLabel = r.downloadable
        ? context.t('common_download')
        : context.t('pages_ressources_open_external');
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(XmSpace.s4),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(r.title, style: t.titleSmall),
          if (meta.isNotEmpty) Text(meta, style: t.labelSmall),
          if (r.summary case final summary?)
            Padding(
              padding: const EdgeInsets.only(top: XmSpace.s2),
              child: Text(summary, style: t.bodySmall, maxLines: 3, overflow: TextOverflow.ellipsis),
            ),
          if (canAct)
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: _busy ? null : _download,
                icon: _busy
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : Icon(r.downloadable ? Icons.download_outlined : Icons.open_in_new),
                label: Text(actionLabel),
              ),
            ),
        ]),
      ),
    );
  }
}
