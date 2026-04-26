import 'package:magmug/core/models/communication_models.dart';
import 'package:magmug/core/models/app_content_models.dart';
import 'package:magmug/core/models/match_models.dart';
import 'package:magmug/core/models/payment_models.dart';
import 'package:magmug/core/models/public_settings_models.dart';
import 'package:magmug/core/network/app_auth_api.dart';

class AppRepository {
  AppRepository._();

  static final AppRepository instance = AppRepository._();

  static const Duration _publicConfigTtl = Duration(hours: 24);
  static const Duration _paymentTtl = Duration(hours: 12);
  static const Duration _giftTtl = Duration(hours: 12);
  static const Duration _notificationTtl = Duration(minutes: 1);
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

    final api = AppAuthApi();
    try {
      final value = await api.fetchMobileConfig();
      _publicSettings = _CacheEntry(value, _publicConfigTtl);
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

    final api = AppAuthApi();
    try {
      final value = await api.fetchCreditPackages(token, platform: platform);
      _creditPackages[key] = _CacheEntry(value, _paymentTtl);
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

    final api = AppAuthApi();
    try {
      final value = await api.fetchSubscriptionPackages(
        token,
        platform: platform,
      );
      _subscriptionPackages[key] = _CacheEntry(value, _paymentTtl);
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

    final api = AppAuthApi();
    try {
      final value = await api.fetchGifts(token);
      _gifts[ownerUserId] = _CacheEntry(value, _giftTtl);
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

    final api = AppAuthApi();
    try {
      final value = _todayNotifications(await api.fetchNotifications(token));
      _notifications[ownerUserId] = _CacheEntry(value, _notificationTtl);
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
