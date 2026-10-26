import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../../core/util/password_strength.dart';
import '../data/auth_api.dart';
import 'otp_page.dart';

/// Mot de passe oublié : réponse 202 identique que le compte existe ou non,
/// puis saisie du code (OTP `reset`).
class ForgotPasswordPage extends ConsumerStatefulWidget {
  const ForgotPasswordPage({super.key});

  @override
  ConsumerState<ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends ConsumerState<ForgotPasswordPage> {
  final _email = TextEditingController();
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final email = _email.text.trim();
    if (email.isEmpty || !kEmailPattern.hasMatch(email)) {
      setState(() => _error = email.isEmpty ? vRequired(context, 'email') : vEmail(context, 'email'));
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(authApiProvider).forgotPassword(email);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.t('auth_forgot_sent'))));
      context.push('/otp?purpose=reset&email=${Uri.encodeQueryComponent(email)}');
    } catch (e) {
      if (mounted) setState(() => _error = ApiException.from(e).status == 429 ? null : errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(),
        body: SafeArea(
          child: ListView(padding: const EdgeInsets.all(XmSpace.gutter), children: [
            Semantics(
              header: true,
              child: Text(context.t('auth_forgot_heading'), style: Theme.of(context).textTheme.headlineMedium),
            ),
            const SizedBox(height: XmSpace.s3),
            Text(context.t('auth_forgot_intro'), style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: XmSpace.s5),
            TextField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              autofillHints: const [AutofillHints.email],
              autocorrect: false,
              textInputAction: TextInputAction.done,
              onSubmitted: (_) => _submit(),
              decoration: InputDecoration(labelText: context.t('auth_forgot_email'), errorText: _error),
            ),
            const SizedBox(height: XmSpace.s5),
            FilledButton(
              onPressed: _busy ? null : _submit,
              child: _busy
                  ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5))
                  : Text(context.t('auth_forgot_submit')),
            ),
            const SizedBox(height: XmSpace.s3),
            TextButton(onPressed: () => context.go('/login'), child: Text(context.t('auth_forgot_back'))),
          ]),
        ),
      );
}

/// Nouveau mot de passe (preuve `reset_token` délivrée par l'OTP `reset`).
class ResetPasswordPage extends ConsumerStatefulWidget {
  const ResetPasswordPage({super.key, required this.args});
  final ResetArgs args;

  @override
  ConsumerState<ResetPasswordPage> createState() => _ResetPasswordPageState();
}

class _ResetPasswordPageState extends ConsumerState<ResetPasswordPage> {
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  String? _passwordError;
  String? _confirmError;
  String? _formError;
  bool _busy = false;

  @override
  void dispose() {
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final pw = _password.text;
    setState(() {
      _passwordError = pw.isEmpty
          ? vRequired(context, 'password')
          : (PasswordStrength(pw).meetsPolicy ? null : context.t('auth_register_account_password_hint'));
      _confirmError = pw == _confirm.text ? null : vConfirmed(context, 'password');
      _formError = null;
    });
    if (_passwordError != null || _confirmError != null) return;
    setState(() => _busy = true);
    try {
      await ref.read(authApiProvider).resetPassword(
            email: widget.args.email,
            resetToken: widget.args.resetToken,
            password: pw,
            confirmation: _confirm.text,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.t('auth_reset_done'))));
      context.go('/login');
    } catch (e) {
      if (!mounted) return;
      final a = ApiException.from(e);
      setState(() {
        _passwordError = a.firstError('password');
        _formError = a.code == 'otp_invalid'
            ? context.t('auth_reset_expired')
            : (a.status == 429 || _passwordError != null ? null : errorMessage(context, e));
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(),
        body: SafeArea(
          child: ListView(padding: const EdgeInsets.all(XmSpace.gutter), children: [
            Semantics(
              header: true,
              child: Text(context.t('auth_reset_heading'), style: Theme.of(context).textTheme.headlineMedium),
            ),
            const SizedBox(height: XmSpace.s3),
            Text(context.t('auth_reset_intro'), style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: XmSpace.s5),
            if (_formError != null) ...[XmAlert(_formError!), const SizedBox(height: XmSpace.s4)],
            PasswordField(
              controller: _password,
              label: context.t('auth_reset_password'),
              autofill: AutofillHints.newPassword,
              errorText: _passwordError,
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: XmSpace.s3),
            PasswordStrengthMeter(_password.text),
            const SizedBox(height: XmSpace.s4),
            PasswordField(
              controller: _confirm,
              label: context.t('auth_reset_password_confirmation'),
              autofill: AutofillHints.newPassword,
              errorText: _confirmError,
              textInputAction: TextInputAction.done,
              onSubmitted: (_) => _submit(),
            ),
            const SizedBox(height: XmSpace.s5),
            FilledButton(
              onPressed: _busy ? null : _submit,
              child: _busy
                  ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5))
                  : Text(context.t('auth_reset_submit')),
            ),
          ]),
        ),
      );
}
