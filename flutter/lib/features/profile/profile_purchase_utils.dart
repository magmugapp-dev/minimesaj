import 'package:magmug/app_core.dart';
import 'package:in_app_purchase/in_app_purchase.dart';
import 'package:magmug/features/payment/payment_result_flow.dart';
import 'package:magmug/l10n/app_localizations.dart';

class ProfilePurchaseResultCopy {
  final PaymentResultTone tone;
  final String badge;
  final String title;
  final String subtitle;
  final String statusLabel;

  const ProfilePurchaseResultCopy({
    required this.tone,
    required this.badge,
    required this.title,
    required this.subtitle,
    required this.statusLabel,
  });
}

class RestorePurchasesUpdateResolution {
  final int restoredInBatch;
  final String? notice;
  final bool shouldStopRestoring;
  final List<PurchaseDetails> purchasesToComplete;

  const RestorePurchasesUpdateResolution({
    required this.restoredInBatch,
    required this.notice,
    required this.shouldStopRestoring,
    required this.purchasesToComplete,
  });
}

RestorePurchasesUpdateResolution resolveRestorePurchasesUpdate(
  List<PurchaseDetails> purchases,
  AppLocalizations l10n,
) {
  var restoredInBatch = 0;
  String? nextNotice;
  final purchasesToComplete = <PurchaseDetails>[];

  for (final purchase in purchases) {
    switch (purchase.status) {
      case PurchaseStatus.pending:
        nextNotice = l10n.restorePurchasesPreparing;
        break;
      case PurchaseStatus.purchased:
      case PurchaseStatus.restored:
        restoredInBatch++;
        break;
      case PurchaseStatus.error:
        nextNotice =
            purchase.error?.message ?? l10n.restorePurchasesResponseUnreadable;
        break;
      case PurchaseStatus.canceled:
        nextNotice = l10n.restorePurchasesCancelled;
        break;
    }

    if (purchase.pendingCompletePurchase) {
      purchasesToComplete.add(purchase);
    }
  }

  return RestorePurchasesUpdateResolution(
    restoredInBatch: restoredInBatch,
    notice: nextNotice,
    shouldStopRestoring:
        restoredInBatch > 0 ||
        (nextNotice != null && nextNotice != l10n.restorePurchasesPreparing),
    purchasesToComplete: purchasesToComplete,
  );
}

String resolveRestorePurchasesTimeoutNotice(
  AppLocalizations l10n,
  int restoredCount,
) {
  return restoredCount > 0
      ? l10n.restorePurchasesRestoredCount(restoredCount)
      : l10n.restorePurchasesNotFound;
}

String resolveRestorePurchasesPrimaryActionLabel(
  AppLocalizations l10n, {
  required bool checkingStore,
  required bool restoring,
}) {
  if (checkingStore) {
    return l10n.restorePurchasesChecking;
  }
  if (restoring) {
    return l10n.restorePurchasesProcessing;
  }
  return l10n.restorePurchasesAction;
}

int resolveRecommendedSelectionIndex<T>(
  List<T> items,
  int selectedIndex, {
  required bool Function(T item) isRecommended,
}) {
  if (items.isEmpty) {
    return selectedIndex;
  }

  if (selectedIndex < items.length) {
    return selectedIndex;
  }

  final recommendedIndex = items.indexWhere(isRecommended);
  return recommendedIndex >= 0 ? recommendedIndex : 0;
}

(String, String) profilePriceParts(String priceLabel) {
  final normalized = priceLabel.replaceAll(RegExp(r'[^0-9,\.]'), '').trim();
  if (normalized.isEmpty) {
    return ('0', ',00');
  }

  final commaIndex = normalized.lastIndexOf(',');
  if (commaIndex != -1) {
    return (
      normalized.substring(0, commaIndex),
      normalized.substring(commaIndex),
    );
  }

  final dotIndex = normalized.lastIndexOf('.');
  if (dotIndex != -1) {
    return (
      normalized.substring(0, dotIndex),
      ',${normalized.substring(dotIndex + 1)}',
    );
  }

  return (normalized, ',00');
}

String subscriptionPlanTitle(
  AppSubscriptionPackage package,
  AppLocalizations l10n,
) {
  return switch (package.months) {
    1 => l10n.paywallPlanMonth,
    3 => l10n.paywallPlanQuarter,
    _ => '${package.months} Ay',
  };
}

String subscriptionPeriodTitle(
  AppSubscriptionPackage package,
  AppLocalizations l10n,
) {
  return switch (package.months) {
    1 => l10n.paywallPeriodMonth,
    3 => l10n.paywallPeriodQuarter,
    _ => AppRuntimeText.instance.t(
      'paywallPeriodMonths',
      '{count} Ay Premium',
      args: {'count': package.months},
    ),
  };
}

ProfilePurchaseResultCopy premiumPurchaseAuthRequiredCopy(
  AppLocalizations l10n,
) {
  return ProfilePurchaseResultCopy(
    tone: PaymentResultTone.failure,
    badge: AppRuntimeText.instance.t(
      'purchaseAuthRequiredBadge',
      'OTURUM GEREKLI',
    ),
    title: AppRuntimeText.instance.t(
      'premiumPurchaseStartFailedTitle',
      'Premium satin alimi baslatilamadi',
    ),
    subtitle: AppRuntimeText.instance.t(
      'purchaseAuthRequiredMessage',
      'Devam etmek icin once oturum acman gerekiyor.',
    ),
    statusLabel: AppRuntimeText.instance.t(
      'purchaseFailedStatus',
      'Islem tamamlanamadi',
    ),
  );
}

ProfilePurchaseResultCopy premiumPurchaseSuccessCopy(AppLocalizations l10n) {
  return ProfilePurchaseResultCopy(
    tone: PaymentResultTone.success,
    badge: AppRuntimeText.instance.t('premiumActiveBadge', 'PREMIUM AKTIF'),
    title: AppRuntimeText.instance.t(
      'premiumPurchaseSuccessTitle',
      'Premium planin hazir',
    ),
    subtitle: AppRuntimeText.instance.t(
      'premiumPurchaseSuccessMessage',
      'Satin alma dogrulandi ve premium erisimin hesabina tanimlandi.',
    ),
    statusLabel: AppRuntimeText.instance.t(
      'premiumActiveStatus',
      'Premium aktif',
    ),
  );
}

ProfilePurchaseResultCopy premiumPurchaseFailureCopy(
  AppLocalizations l10n, {
  required String message,
}) {
  return ProfilePurchaseResultCopy(
    tone: PaymentResultTone.failure,
    badge: AppRuntimeText.instance.t('paymentFailedBadge', 'ODEME BASARISIZ'),
    title: AppRuntimeText.instance.t(
      'premiumPurchaseFailedTitle',
      'Premium satin alimi tamamlanamadi',
    ),
    subtitle: message,
    statusLabel: AppRuntimeText.instance.t(
      'purchaseFailedStatus',
      'Islem tamamlanamadi',
    ),
  );
}

ProfilePurchaseResultCopy jetonPurchaseAuthRequiredCopy(AppLocalizations l10n) {
  return ProfilePurchaseResultCopy(
    tone: PaymentResultTone.failure,
    badge: AppRuntimeText.instance.t(
      'purchaseAuthRequiredBadge',
      'OTURUM GEREKLI',
    ),
    title: AppRuntimeText.instance.t(
      'jetonPurchaseStartFailedTitle',
      'Jeton satin alimi baslatilamadi',
    ),
    subtitle: AppRuntimeText.instance.t(
      'purchaseAuthRequiredMessage',
      'Devam etmek icin once oturum acman gerekiyor.',
    ),
    statusLabel: AppRuntimeText.instance.t(
      'selectedPackageStatus',
      'Secilen paket hazir',
    ),
  );
}

ProfilePurchaseResultCopy jetonPurchaseSuccessCopy(AppLocalizations l10n) {
  return ProfilePurchaseResultCopy(
    tone: PaymentResultTone.success,
    badge: AppRuntimeText.instance.t('paymentSuccessBadge', 'ODEME BASARILI'),
    title: AppRuntimeText.instance.t(
      'jetonPurchaseSuccessTitle',
      'Kredi paketin hazir',
    ),
    subtitle: AppRuntimeText.instance.t(
      'jetonPurchaseSuccessMessage',
      'Krediler hesabina eklendi, sohbete devam edebilirsin.',
    ),
    statusLabel: l10n.jetonCreditsAdded,
  );
}

ProfilePurchaseResultCopy jetonPurchaseFailureCopy(
  AppLocalizations l10n, {
  required String message,
}) {
  return ProfilePurchaseResultCopy(
    tone: PaymentResultTone.failure,
    badge: AppRuntimeText.instance.t('paymentFailedBadge', 'ODEME BASARISIZ'),
    title: AppRuntimeText.instance.t(
      'jetonPurchaseFailedTitle',
      'Jeton satin alimi tamamlanamadi',
    ),
    subtitle: message,
    statusLabel: AppRuntimeText.instance.t(
      'selectedPackageStatus',
      'Secilen paket hazir',
    ),
  );
}

void openPremiumPurchaseResultScreen(
  BuildContext context, {
  required ProfilePurchaseResultCopy copy,
  required String productLabel,
  required String amountLabel,
  required AppLocalizations l10n,
}) {
  Navigator.of(context).push(
    cupertinoRoute(
      PaymentResultScreen(
        tone: copy.tone,
        badge: copy.badge,
        title: copy.title,
        subtitle: copy.subtitle,
        productLabel: productLabel,
        amountLabel: amountLabel,
        statusLabel: copy.tone == PaymentResultTone.pending
            ? l10n.paywallPendingStatus
            : copy.statusLabel,
        primaryLabel: l10n.commonDone,
        secondaryLabel: l10n.paywallBackToPlans,
        onSecondaryTap: () => Navigator.of(context).maybePop(),
      ),
    ),
  );
}

void showJetonPurchaseResultSheet(
  BuildContext context, {
  required ProfilePurchaseResultCopy copy,
  required AppCreditPackage selectedPackage,
  required AppLocalizations l10n,
}) {
  final rootNavigator = Navigator.of(context, rootNavigator: true);

  WidgetsBinding.instance.addPostFrameCallback((_) {
    if (!context.mounted) {
      return;
    }

    showCupertinoModalPopup<void>(
      context: rootNavigator.context,
      builder: (_) => PaymentResultSheet(
        tone: copy.tone,
        badge: copy.badge,
        title: copy.title,
        subtitle: copy.subtitle,
        productLabel: l10n.jetonAmountLabel('${selectedPackage.credits}'),
        amountLabel: selectedPackage.displayPrice,
        statusLabel: copy.statusLabel,
        primaryLabel: l10n.commonDone,
      ),
    );
  });
}
