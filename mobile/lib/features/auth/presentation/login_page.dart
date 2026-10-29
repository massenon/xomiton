import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/xm_rainbow_deco.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/session_controller.dart';
import '../data/auth_api.dart';

class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key});

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
  final _login = TextEditingController();
  final _password = TextEditingController();
  String? _loginError;
  String? _passwordError;
  String? _formError;
  bool _busy = false;

  @override
  void dispose() {
    _login.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _loginError = _login.text.trim().isEmpty
          ? vRequired(context, 'login')
          : null;
      _passwordError = _password.text.isEmpty
          ? vRequired(context, 'password')
          : null;
      _formError = null;
    });
    if (_loginError != null || _passwordError != null) return;
    setState(() => _busy = true);
    try {
      final result = await ref
          .read(authApiProvider)
          .login(_login.text, _password.text);
      if (!mounted) return;
      switch (result) {
        case LoginOtpRequired(:final email):
          context.push(
            '/otp?purpose=login&email=${Uri.encodeQueryComponent(email)}',
          );
        case LoginSuccess(:final token, :final user):
          await ref.read(sessionProvider.notifier).signIn(token, user);
          // Connexion unique : le routeur choisit l'écran d'arrivée selon les espaces du compte.
          if (mounted) context.go('/spaces');
      }
    } catch (e) {
      if (!mounted) return;
      final api = ApiException.from(e);
      // Message indifférencié : jamais « compte inconnu » (docs/API.md).
      setState(
        () => _formError = api.status == 429 ? null : errorMessage(context, e),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: XmAuthDecor(
        child: SafeArea(
          top: false,
          child: AutofillGroup(
            child: ListView(
              padding: const EdgeInsets.all(XmSpace.gutter),
              children: [
                const SizedBox(height: XmSpace.s5),
                const Align(
                  alignment: Alignment.centerLeft,
                  child: XmLogo(width: 120),
                ),
                const SizedBox(height: XmSpace.s5),
                Semantics(
                  header: true,
                  child: Text(
                    context.t('auth_login_heading'),
                    style: Theme.of(context).textTheme.headlineMedium,
                  ),
                ),
                const SizedBox(height: XmSpace.s2),
                Text(
                  context.t('auth_login_intro'),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: XmSpace.s5),
                if (_formError != null) ...[
                  XmAlert(_formError!),
                  const SizedBox(height: XmSpace.s4),
                ],
                TextField(
                  controller: _login,
                  keyboardType: TextInputType.emailAddress,
                  autofillHints: const [AutofillHints.username],
                  textInputAction: TextInputAction.next,
                  autocorrect: false,
                  decoration: InputDecoration(
                    labelText: context.t('auth_login_identifier'),
                    errorText: _loginError,
                  ),
                ),
                const SizedBox(height: XmSpace.s4),
                PasswordField(
                  controller: _password,
                  label: context.t('auth_login_password'),
                  errorText: _passwordError,
                  textInputAction: TextInputAction.done,
                  onSubmitted: (_) => _submit(),
                ),
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton(
                    onPressed: () => context.push('/forgot'),
                    child: Text(context.t('auth_login_forgot')),
                  ),
                ),
                const SizedBox(height: XmSpace.s2),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(strokeWidth: 2.5),
                        )
                      : Text(context.t('auth_login_submit')),
                ),
                const SizedBox(height: XmSpace.s5),
                Center(
                  child: Text(
                    context.t('auth_login_no_account'),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
                const SizedBox(height: XmSpace.s2),
                OutlinedButton(
                  onPressed: () => context.go('/signup'),
                  child: Text(context.t('auth_login_create_account')),
                ),
                const SizedBox(height: XmSpace.s4),
                // Sans compte (RG-01) : SOS, signalement, suivi et urgences restent ouverts.
                Text(
                  context.t('alerts_report_sos_hint'),
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                Wrap(
                  alignment: WrapAlignment.center,
                  spacing: XmSpace.s2,
                  children: [
                    TextButton(
                      onPressed: () => context.push('/alerte-sos'),
                      child: Text(context.t('mobile_tab_sos')),
                    ),
                    TextButton(
                      onPressed: () => context.push('/signaler'),
                      child: Text(context.t('alerts_report_title')),
                    ),
                    TextButton(
                      onPressed: () => context.push('/suivi'),
                      child: Text(context.t('alerts_report_tracking_title')),
                    ),
                    TextButton(
                      onPressed: () => context.push('/urgences'),
                      child: Text(context.t('alerts_emergency_title')),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
