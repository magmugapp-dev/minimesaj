import 'package:flutter/cupertino.dart';
import 'package:magmug/core/i18n/app_runtime_text.dart';
import 'package:magmug/core/utils/currency_format.dart';

String? _nullableString(String? value) {
  final normalized = value?.trim();
  if (normalized == null || normalized.isEmpty) {
    return null;
  }
  return normalized;
}

@immutable
class AppCreditPackage {
  final int id;
  final String code;
  final String? storeProductCode;
  final int credits;
  final double price;
  final String currency;
  final String? badge;
  final bool isRecommended;
  final bool isActive;
  final int order;

  const AppCreditPackage({
    required this.id,
    required this.code,
    required this.storeProductCode,
    required this.credits,
    required this.price,
    required this.currency,
    required this.badge,
    required this.isRecommended,
    required this.isActive,
    required this.order,
  });

  String get displayPrice => formatCurrencyAmount(price, currency: currency);

  String get amountLabel => AppRuntimeText.instance.t(
    'payment.credit_package.amount',
    '{credits} Kredi',
    args: {'credits': credits},
  );

  String? get badgeLabel =>
      badge ??
      (isRecommended
          ? AppRuntimeText.instance.t('payment.package.recommended', 'Onerilen')
          : null);

  factory AppCreditPackage.fromJson(Map<String, dynamic> json) {
    return AppCreditPackage(
      id: (json['id'] as num?)?.toInt() ?? 0,
      code: json['kod']?.toString() ?? '',
      storeProductCode: _nullableString(json['magaza_urun_kodu']?.toString()),
      credits: (json['puan'] as num?)?.toInt() ?? 0,
      price: (json['fiyat'] as num?)?.toDouble() ?? 0,
      currency: _nullableString(json['para_birimi']?.toString()) ?? 'TL',
      badge: _nullableString(json['rozet']?.toString()),
      isRecommended: json['onerilen_mi'] == true,
      isActive: json['aktif'] != false,
      order: (json['sira'] as num?)?.toInt() ?? 0,
    );
  }
}

@immutable
class AppSubscriptionPackage {
  final int id;
  final String code;
  final String? storeProductCode;
  final int months;
  final double price;
  final String currency;
  final String? badge;
  final bool isRecommended;
  final bool isActive;
  final int order;

  const AppSubscriptionPackage({
    required this.id,
    required this.code,
    required this.storeProductCode,
    required this.months,
    required this.price,
    required this.currency,
    required this.badge,
    required this.isRecommended,
    required this.isActive,
    required this.order,
  });

  String get displayPrice => formatCurrencyAmount(price, currency: currency);

  String? get badgeLabel =>
      badge ??
      (isRecommended
          ? AppRuntimeText.instance.t('payment.package.recommended', 'Onerilen')
          : null);

  factory AppSubscriptionPackage.fromJson(Map<String, dynamic> json) {
    return AppSubscriptionPackage(
      id: (json['id'] as num?)?.toInt() ?? 0,
      code: json['kod']?.toString() ?? '',
      storeProductCode: _nullableString(json['magaza_urun_kodu']?.toString()),
      months: (json['sure_ay'] as num?)?.toInt() ?? 0,
      price: (json['fiyat'] as num?)?.toDouble() ?? 0,
      currency: _nullableString(json['para_birimi']?.toString()) ?? 'TL',
      badge: _nullableString(json['rozet']?.toString()),
      isRecommended: json['onerilen_mi'] == true,
      isActive: json['aktif'] != false,
      order: (json['sira'] as num?)?.toInt() ?? 0,
    );
  }
}

@immutable
class AppRewardAdStatus {
  final bool active;
  final int rewardPoints;
  final int dailyLimit;
  final int watchedToday;
  final int remainingRights;

  const AppRewardAdStatus({
    required this.active,
    required this.rewardPoints,
    required this.dailyLimit,
    required this.watchedToday,
    required this.remainingRights,
  });

  bool get canWatch => active && rewardPoints > 0 && remainingRights > 0;

  factory AppRewardAdStatus.fromJson(Map<String, dynamic> json) {
    return AppRewardAdStatus(
      active: json['aktif_mi'] == true,
      rewardPoints: (json['odul_puani'] as num?)?.toInt() ?? 0,
      dailyLimit: (json['gunluk_limit'] as num?)?.toInt() ?? 0,
      watchedToday: (json['bugun_izlenen'] as num?)?.toInt() ?? 0,
      remainingRights: (json['kalan_hak'] as num?)?.toInt() ?? 0,
    );
  }
}

@immutable
class AppRewardAdClaimResult {
  final String message;
  final int rewardPoints;
  final int? currentBalance;
  final int dailyLimit;
  final int watchedToday;
  final int remainingRights;
  final String? eventCode;
  final bool duplicate;

  const AppRewardAdClaimResult({
    required this.message,
    required this.rewardPoints,
    required this.currentBalance,
    required this.dailyLimit,
    required this.watchedToday,
    required this.remainingRights,
    required this.eventCode,
    required this.duplicate,
  });

  factory AppRewardAdClaimResult.fromJson(Map<String, dynamic> json) {
    return AppRewardAdClaimResult(
      message:
          json['mesaj']?.toString() ??
          AppRuntimeText.instance.t(
            'payment.reward.claim.default_message',
            'Odul hesabina eklendi.',
          ),
      rewardPoints: (json['odul_puani'] as num?)?.toInt() ?? 0,
      currentBalance: (json['mevcut_puan'] as num?)?.toInt(),
      dailyLimit: (json['gunluk_limit'] as num?)?.toInt() ?? 0,
      watchedToday: (json['bugun_izlenen'] as num?)?.toInt() ?? 0,
      remainingRights: (json['kalan_hak'] as num?)?.toInt() ?? 0,
      eventCode: _nullableString(json['olay_kodu']?.toString()),
      duplicate: json['tekrar_mi'] == true,
    );
  }
}
