import 'dart:convert';

import 'package:magmug/core/models/communication_models.dart';
import 'package:magmug/core/models/app_content_models.dart';
import 'package:magmug/core/models/match_models.dart';
import 'package:magmug/core/models/payment_models.dart';
import 'package:magmug/core/models/public_settings_models.dart';
import 'package:magmug/core/network/app_auth_api.dart';
import 'package:magmug/core/storage/app_storage.dart';

class AppRepository {
  AppRepository._();

  static final AppRepository instance = AppRepository._();

  static const Duration _publicConfigTtl = Duration(hours: 24);
  static const Duration _paymentTtl = Duration(hours: 24);
  static const Duration _giftTtl = Duration(hours: 24);
  static const Duration _notificationTtl = Duration(minutes: 5);
  static const Duration _rewardStatusTtl = Duration(seconds: 45);

  _CacheEntry<AppPublicSettings>? _publicSettings;
  _CacheEntry<AppLegalTexts>? _legalTexts;
  final Map<String, _CacheEntry<AppContent>> _appContent =
      <String, _CacheEntry<AppContent>>{};
  final Map<String, _CacheEntry<List<AppCreditPackage>>> _creditPackages =
      <String, _CacheEntry<List<AppCreditPackage>>>{};
  final Map<String, _CacheEntry<List<AppSubscriptionPackage>>>
  _subscriptionPackages = <String, _CacheEntry<List<AppSubscriptionPackage>>>{};
  final Map<int, _CacheEntry<List<AppGift>>> _gifts =
      <int, _CacheEntry<List<AppGift>>>{};
  final Map<int, _CacheEntry<List<AppNotification>>> _notifications =
      <int, _CacheEntry<List<AppNotification>>>{};
  final Map<int, _CacheEntry<AppRewardAdStatus>> _rewardStatus =
      <int, _CacheEntry<AppRewardAdStatus>>{};

  Future<AppPublicSettings> publicSettings() async {
    final cached = _publicSettings?.valueIfFresh;
    if (cached != null) {
      return cached;
    }
    final box = await AppHiveBoxes.publicSettings();
    final persisted = await _readPublicSettingsCache(box);
    if (persisted?.isFresh == true) {
      _publicSettings = _CacheEntry(persisted!.settings, _publicConfigTtl);
      return persisted.settings;
    }

    final api = AppAuthApi();
    try {
      final result = await api.fetchMobileConfigResult(
        etag: persisted?.etag,
        fallback: persisted?.settings,
      );
      final value = result.settings;
      _publicSettings = _CacheEntry(value, _publicConfigTtl);
      await _writeCache(
        box,
        'mobile_config',
        _publicSettingsToJson(value),
        _publicConfigTtl,
        etag: result.etag ?? persisted?.etag,
      );
      return value;
    } finally {
      api.close();
    }
  }

  Future<AppLegalTexts> legalTexts() async {
    final api = AppAuthApi();
    try {
      final value = await api.fetchLegalTexts();
      _legalTexts = _CacheEntry(value, _publicConfigTtl);
      return value;
    } catch (_) {
      final cached = _legalTexts?.valueIfFresh;
      if (cached != null) {
        return cached;
      }
      rethrow;
    } finally {
      api.close();
    }
  }

  Future<AppContent> appContent(String languageCode) async {
    final normalizedLanguage = languageCode.trim().toLowerCase();
    final api = AppAuthApi();
    try {
      final value = await api.fetchAppContent(normalizedLanguage);
      _appContent[normalizedLanguage] = _CacheEntry(value, _publicConfigTtl);
      return value;
    } finally {
      api.close();
    }
  }

  Future<List<AppCreditPackage>> creditPackages({
    required String token,
    required int ownerUserId,
    String? platform,
  }) async {
    final key = '$ownerUserId:${platform ?? ''}';
    final cached = _creditPackages[key]?.valueIfFresh;
    if (cached != null) {
      return cached;
    }
    final persisted = await _readCacheList(
      await AppHiveBoxes.preferences(),
      'payment.credit_packages.$key',
      AppCreditPackage.fromJson,
    );
    if (persisted != null) {
      _creditPackages[key] = _CacheEntry(persisted, _paymentTtl);
      return persisted;
    }

    final api = AppAuthApi();
    try {
      final value = await api.fetchCreditPackages(token, platform: platform);
      _creditPackages[key] = _CacheEntry(value, _paymentTtl);
      await _writeCache(
        await AppHiveBoxes.preferences(),
        'payment.credit_packages.$key',
        value.map(_creditPackageToJson).toList(growable: false),
        _paymentTtl,
      );
      return value;
    } finally {
      api.close();
    }
  }

  Future<List<AppSubscriptionPackage>> subscriptionPackages({
    required String token,
    required int ownerUserId,
    String? platform,
  }) async {
    final key = '$ownerUserId:${platform ?? ''}';
    final cached = _subscriptionPackages[key]?.valueIfFresh;
    if (cached != null) {
      return cached;
    }
    final persisted = await _readCacheList(
      await AppHiveBoxes.preferences(),
      'payment.subscription_packages.$key',
      AppSubscriptionPackage.fromJson,
    );
    if (persisted != null) {
      _subscriptionPackages[key] = _CacheEntry(persisted, _paymentTtl);
      return persisted;
    }

    final api = AppAuthApi();
    try {
      final value = await api.fetchSubscriptionPackages(
        token,
        platform: platform,
      );
      _subscriptionPackages[key] = _CacheEntry(value, _paymentTtl);
      await _writeCache(
        await AppHiveBoxes.preferences(),
        'payment.subscription_packages.$key',
        value.map(_subscriptionPackageToJson).toList(growable: false),
        _paymentTtl,
      );
      return value;
    } finally {
      api.close();
    }
  }

  Future<List<AppGift>> gifts({
    required String token,
    required int ownerUserId,
  }) async {
    final cached = _gifts[ownerUserId]?.valueIfFresh;
    if (cached != null) {
      return cached;
    }
    final persisted = await _readCacheList(
      await AppHiveBoxes.preferences(),
      'gifts.$ownerUserId',
      AppGift.fromJson,
    );
    if (persisted != null) {
      _gifts[ownerUserId] = _CacheEntry(persisted, _giftTtl);
      return persisted;
    }

    final api = AppAuthApi();
    try {
      final value = await api.fetchGifts(token);
      _gifts[ownerUserId] = _CacheEntry(value, _giftTtl);
      await _writeCache(
        await AppHiveBoxes.preferences(),
        'gifts.$ownerUserId',
        value.map(_giftToJson).toList(growable: false),
        _giftTtl,
      );
      return value;
    } finally {
      api.close();
    }
  }

  Future<List<AppNotification>> notifications({
    required String token,
    required int ownerUserId,
  }) async {
    final cached = _notifications[ownerUserId]?.valueIfFresh;
    if (cached != null) {
      final todayOnly = _todayNotifications(cached);
      if (todayOnly.length != cached.length) {
        _notifications[ownerUserId] = _CacheEntry(todayOnly, _notificationTtl);
      }
      return todayOnly;
    }
    final persisted = await _readCacheList(
      await AppHiveBoxes.notifications(),
      'notifications.$ownerUserId',
      _notificationFromJson,
    );
    if (persisted != null) {
      final todayOnly = _todayNotifications(persisted);
      _notifications[ownerUserId] = _CacheEntry(todayOnly, _notificationTtl);
      return todayOnly;
    }

    final api = AppAuthApi();
    try {
      final value = _todayNotifications(await api.fetchNotifications(token));
      _notifications[ownerUserId] = _CacheEntry(value, _notificationTtl);
      await _writeCache(
        await AppHiveBoxes.notifications(),
        'notifications.$ownerUserId',
        value.map(_notificationToJson).toList(growable: false),
        _notificationTtl,
      );
      return value;
    } finally {
      api.close();
    }
  }

  Future<AppRewardAdStatus> rewardAdStatus({
    required String token,
    required int ownerUserId,
  }) async {
    final cached = _rewardStatus[ownerUserId]?.valueIfFresh;
    if (cached != null) {
      return cached;
    }

    final api = AppAuthApi();
    try {
      final value = await api.fetchRewardAdStatus(token);
      _rewardStatus[ownerUserId] = _CacheEntry(value, _rewardStatusTtl);
      return value;
    } finally {
      api.close();
    }
  }

  void invalidateNotifications(int ownerUserId) {
    _notifications.remove(ownerUserId);
  }

  void invalidateRewardAdStatus(int ownerUserId) {
    _rewardStatus.remove(ownerUserId);
  }

  void clearUserScopedCaches(int ownerUserId) {
    _gifts.remove(ownerUserId);
    _notifications.remove(ownerUserId);
    _rewardStatus.remove(ownerUserId);
    _creditPackages.removeWhere((key, _) => key.startsWith('$ownerUserId:'));
    _subscriptionPackages.removeWhere(
      (key, _) => key.startsWith('$ownerUserId:'),
    );
  }

  Future<List<T>?> _readCacheList<T>(
    dynamic box,
    String key,
    T Function(Map<String, dynamic> json) parser,
  ) async {
    final row = _asMap(box.get(key));
    if (row == null || !_cacheFresh(row)) {
      return null;
    }
    final data = row['data'];
    if (data is! List) {
      return null;
    }
    try {
      return data
          .map(_asMap)
          .whereType<Map<String, dynamic>>()
          .map(parser)
          .toList(growable: false);
    } catch (_) {
      await box.delete(key);
      return null;
    }
  }

  Future<_PublicSettingsCache?> _readPublicSettingsCache(dynamic box) async {
    final row = _asMap(box.get('mobile_config'));
    if (row == null) {
      return null;
    }
    final data = _asMap(row['data']);
    if (data == null) {
      return null;
    }
    try {
      return _PublicSettingsCache(
        settings: AppPublicSettings.fromJson(data),
        etag: row['etag']?.toString(),
        isFresh: _cacheFresh(row),
      );
    } catch (_) {
      await box.delete('mobile_config');
      return null;
    }
  }

  Future<void> _writeCache(
    dynamic box,
    String key,
    Object? data,
    Duration ttl, {
    String? etag,
  }) {
    return box.put(key, {
      'expires_at_ms': DateTime.now().add(ttl).millisecondsSinceEpoch,
      'data': data,
      if (etag != null && etag.trim().isNotEmpty) 'etag': etag.trim(),
    });
  }

  bool _cacheFresh(Map<String, dynamic> row) {
    final expiresAtMs = (row['expires_at_ms'] as num?)?.toInt() ?? 0;
    return expiresAtMs > DateTime.now().millisecondsSinceEpoch;
  }

  Map<String, dynamic>? _asMap(Object? value) {
    if (value is Map<String, dynamic>) {
      return value;
    }
    if (value is Map) {
      return value.map((key, val) => MapEntry(key.toString(), val));
    }
    if (value is String && value.trim().isNotEmpty) {
      try {
        final decoded = jsonDecode(value);
        if (decoded is Map) {
          return decoded.map((key, val) => MapEntry(key.toString(), val));
        }
      } catch (_) {}
    }
    return null;
  }

  Map<String, dynamic> _publicSettingsToJson(AppPublicSettings value) => {
    'uygulama_adi': value.appName,
    'uygulama_logosu': value.appLogoUrl,
    'uygulama_versiyonu': value.appVersion,
    'mobil_minimum_versiyon': value.minimumSupportedVersion,
    'varsayilan_dil': value.defaultLanguage,
    'kayit_aktif_mi': value.registrationEnabled,
    'destek_eposta': value.supportEmail,
    'destek_whatsapp': value.supportWhatsApp,
    'android_play_store_url': value.androidPlayStoreUrl,
    'ios_app_store_url': value.iosAppStoreUrl,
    'reklamlar': {
      'aktif_mi': value.ads.enabled,
      'test_modu': value.ads.testMode,
      'odul_puani': value.ads.rewardPoints,
      'gunluk_odul_limiti': value.ads.dailyRewardLimit,
      'android': {
        'app_id': value.ads.android.appId,
        'rewarded_unit_id': value.ads.android.rewardedUnitId,
        'match_native_unit_id': value.ads.android.matchNativeUnitId,
      },
      'ios': {
        'app_id': value.ads.ios.appId,
        'rewarded_unit_id': value.ads.ios.rewardedUnitId,
        'match_native_unit_id': value.ads.ios.matchNativeUnitId,
      },
    },
  };

  Map<String, dynamic> _creditPackageToJson(AppCreditPackage value) => {
    'id': value.id,
    'kod': value.code,
    'magaza_urun_kodu': value.storeProductCode,
    'puan': value.credits,
    'fiyat': value.price,
    'para_birimi': value.currency,
    'rozet': value.badge,
    'onerilen_mi': value.isRecommended,
    'aktif': value.isActive,
    'sira': value.order,
  };

  Map<String, dynamic> _subscriptionPackageToJson(
    AppSubscriptionPackage value,
  ) => {
    'id': value.id,
    'kod': value.code,
    'magaza_urun_kodu': value.storeProductCode,
    'sure_ay': value.months,
    'fiyat': value.price,
    'para_birimi': value.currency,
    'rozet': value.badge,
    'onerilen_mi': value.isRecommended,
    'aktif': value.isActive,
    'sira': value.order,
  };

  Map<String, dynamic> _giftToJson(AppGift value) => {
    'id': value.id,
    'kod': value.code,
    'ad': value.name,
    'ikon': value.icon,
    'puan_bedeli': value.cost,
    'aktif': value.active,
    'sira': value.order,
  };

  AppNotification _notificationFromJson(Map<String, dynamic> json) {
    final routeParameters = _asMap(json['route_parameters']) ?? const {};
    return AppNotification(
      id: json['id']?.toString() ?? '',
      type: json['type']?.toString(),
      title: json['title']?.toString() ?? '',
      message: json['message']?.toString() ?? '',
      route: json['route']?.toString(),
      routeParameters: routeParameters.map(
        (key, value) => MapEntry(key, value?.toString() ?? ''),
      ),
      payload: _asMap(json['payload']) ?? const {},
      isRead: json['is_read'] == true,
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
    );
  }

  Map<String, dynamic> _notificationToJson(AppNotification value) => {
    'id': value.id,
    'type': value.type,
    'title': value.title,
    'message': value.message,
    'route': value.route,
    'route_parameters': value.routeParameters,
    'payload': value.payload,
    'is_read': value.isRead,
    'created_at': value.createdAt?.toIso8601String(),
  };

  List<AppNotification> _todayNotifications(
    List<AppNotification> notifications,
  ) {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    return notifications.where((notification) {
      final createdAt = notification.createdAt;
      if (createdAt == null) {
        return false;
      }
      final day = DateTime(createdAt.year, createdAt.month, createdAt.day);
      return day == today;
    }).toList();
  }
}

class _CacheEntry<T> {
  final T value;
  final DateTime expiresAt;

  _CacheEntry(this.value, Duration ttl) : expiresAt = DateTime.now().add(ttl);

  T? get valueIfFresh {
    if (DateTime.now().isAfter(expiresAt)) {
      return null;
    }
    return value;
  }
}

class _PublicSettingsCache {
  final AppPublicSettings settings;
  final String? etag;
  final bool isFresh;

  const _PublicSettingsCache({
    required this.settings,
    required this.etag,
    required this.isFresh,
  });
}
