import 'package:flutter/cupertino.dart';
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

  String get amountLabel => '$credits Kredi';

  String? get badgeLabel => badge ?? (isRecommended ? 'Onerilen' : null);

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

  String? get badgeLabel => badge ?? (isRecommended ? 'Onerilen' : null);

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
