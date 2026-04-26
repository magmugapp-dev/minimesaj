import 'package:magmug/app_core.dart';
import 'package:url_launcher/url_launcher.dart';

Future<void> showOnboardingApiErrorModal(
  BuildContext context,
  Object error, {
  required String fallbackTitle,
  SocialAuthProvider? provider,
}) async {
  final message = AppAuthErrorFormatter.messageFrom(error, provider: provider);

  if (error is AppUpdateRequiredException) {
    await _showOnboardingAuthErrorModal(
      context,
      title: AppRuntimeText.instance.t(
        'updateRequiredTitle',
        'Guncelleme gerekli',
      ),
      message: message,
      dismissLabel: AppRuntimeText.instance.t(
        'updateLaterAction',
        'Daha sonra',
      ),
      actionLabel: AppRuntimeText.instance.t(
        'openPlayStoreAction',
        'Play Store\'u ac',
      ),
      onAction: () => _launchOnboardingUpdateUrl(context, error.updateUrl),
    );
    return;
  }

  await _showOnboardingAuthErrorModal(
    context,
    title: fallbackTitle,
    message: message,
  );
}

Future<void> _launchOnboardingUpdateUrl(
  BuildContext context,
  String? rawUrl,
) async {
  final fallbackUrl = AppEnvironment.androidPlayStoreUrl;
  final target = (rawUrl ?? '').trim().isEmpty ? fallbackUrl : rawUrl!.trim();
  final uri = Uri.tryParse(target);

  if (uri != null &&
      await launchUrl(uri, mode: LaunchMode.externalApplication)) {
    return;
  }

  if (!context.mounted) {
    return;
  }

  await showCupertinoDialog<void>(
    context: context,
    builder: (dialogContext) => CupertinoAlertDialog(
      title: Text(
        AppRuntimeText.instance.t(
          'storeLinkOpenFailedTitle',
          'Magaza baglantisi acilamadi',
        ),
      ),
      content: Text(
        AppRuntimeText.instance.t(
          'storeLinkOpenFailedMessage',
          'Play Store baglantisi su anda acilamiyor. Biraz sonra tekrar deneyebilirsin.',
        ),
      ),
      actions: [
        CupertinoDialogAction(
          onPressed: () => Navigator.of(dialogContext).pop(),
          child: Text(AppRuntimeText.instance.t('commonOk', 'Tamam')),
        ),
      ],
    ),
  );
}

Future<void> _showOnboardingAuthErrorModal(
  BuildContext context, {
  required String title,
  required String message,
  String? dismissLabel,
  String? actionLabel,
  Future<void> Function()? onAction,
}) async {
  await showCupertinoDialog<void>(
    context: context,
    builder: (dialogContext) => CupertinoAlertDialog(
      title: Text(title),
      content: Text(message),
      actions: [
        CupertinoDialogAction(
          onPressed: () => Navigator.of(dialogContext).pop(),
          child: Text(
            dismissLabel ?? AppRuntimeText.instance.t('commonOk', 'Tamam'),
          ),
        ),
        if (actionLabel != null && onAction != null)
          CupertinoDialogAction(
            isDefaultAction: true,
            onPressed: () async {
              Navigator.of(dialogContext).pop();
              await onAction();
            },
            child: Text(actionLabel),
          ),
      ],
    ),
  );
}
