import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';
import 'package:magmug/core/i18n/app_runtime_text.dart';
import 'package:magmug/core/models/app_content_models.dart';
import 'package:magmug/core/models/payment_models.dart';
import 'package:magmug/core/models/public_settings_models.dart';
import 'package:magmug/core/providers/app_session_providers.dart';
import 'package:magmug/core/repository/app_repository.dart';
import 'package:magmug/core/storage/app_storage.dart';

String? currentMobileStorePlatform() {
  if (kIsWeb) {
    return null;
  }

  switch (defaultTargetPlatform) {
    case TargetPlatform.android:
      return 'android';
    case TargetPlatform.iOS:
      return 'ios';
    default:
      return null;
  }
}

final appPublicSettingsProvider = FutureProvider<AppPublicSettings>((
  ref,
) async {
  return AppRepository.instance.publicSettings();
});

class AppContentController extends AsyncNotifier<AppContent> {
  @override
  Future<AppContent> build() async {
    final language =
        ref.watch(appLanguageProvider).asData?.value ??
        AppPreferencesStorage.fallbackLanguage();

    final cached =
        await AppContentStorage.read(language.code) ??
        await AppContentStorage.read('en') ??
        await AppContentStorage.readLast();

    if (cached != null) {
      AppRuntimeText.instance.update(cached);
      unawaited(_refresh(language.code, cached.version));
      return cached;
    }

    try {
      return await _fetchAndCache(language.code);
    } catch (_) {
      final fallback = AppContent.empty(languageCode: language.code);
      AppRuntimeText.instance.update(fallback);
      return fallback;
    }
  }

  Future<void> _refresh(String languageCode, String cachedVersion) async {
    try {
      final fresh = await _fetchAndCache(languageCode);
      if (fresh.version == cachedVersion) {
        return;
      }
      state = AsyncData(fresh);
    } catch (_) {
      // Cache uygulamada kalir; gecici API hatasi UI'yi dusurmez.
    }
  }

  Future<AppContent> _fetchAndCache(String languageCode) async {
    final content = await AppRepository.instance.appContent(languageCode);
    await AppContentStorage.save(content);
    AppRuntimeText.instance.update(content);
    return content;
  }
}

final appContentProvider =
    AsyncNotifierProvider<AppContentController, AppContent>(
      AppContentController.new,
    );

final appLegalTextsProvider = FutureProvider.autoDispose<AppLegalTexts>((
  ref,
) async {
  final content = await ref.watch(appContentProvider.future);
  return AppLegalTexts.fromAppContent(content);
});

final appCreditPackagesProvider = FutureProvider<List<AppCreditPackage>>((
  ref,
) async {
  final authState = ref.watch(appAuthProvider).asData?.value;
  final ownerUserId = authState?.user?.id;
  if (authState == null ||
      authState.token.trim().isEmpty ||
      ownerUserId == null) {
    return const <AppCreditPackage>[];
  }

  final platform = currentMobileStorePlatform();
  return AppRepository.instance.creditPackages(
    token: authState.token,
    ownerUserId: ownerUserId,
    platform: platform,
  );
});

final appSubscriptionPackagesProvider =
    FutureProvider<List<AppSubscriptionPackage>>((ref) async {
      final authState = ref.watch(appAuthProvider).asData?.value;
      final ownerUserId = authState?.user?.id;
      if (authState == null ||
          authState.token.trim().isEmpty ||
          ownerUserId == null) {
        return const <AppSubscriptionPackage>[];
      }

      final platform = currentMobileStorePlatform();
      return AppRepository.instance.subscriptionPackages(
        token: authState.token,
        ownerUserId: ownerUserId,
        platform: platform,
      );
    });

final appRewardAdStatusProvider = FutureProvider<AppRewardAdStatus>((
  ref,
) async {
  final authState = ref.watch(appAuthProvider).asData?.value;
  final ownerUserId = authState?.user?.id;
  if (authState == null ||
      authState.token.trim().isEmpty ||
      ownerUserId == null) {
    return const AppRewardAdStatus(
      active: false,
      rewardPoints: 0,
      dailyLimit: 0,
      watchedToday: 0,
      remainingRights: 0,
    );
  }

  return AppRepository.instance.rewardAdStatus(
    token: authState.token,
    ownerUserId: ownerUserId,
  );
});

final conversationFeedRefreshProvider = StateProvider<int>((ref) => 0);
final notificationsFeedRefreshProvider = StateProvider<int>((ref) => 0);
final pendingPushPayloadProvider = StateProvider<Map<String, String>?>(
  (ref) => null,
);

class AppPushSupport {
  AppPushSupport._();

  static bool get isSupported {
    if (kIsWeb) {
      return false;
    }

    return defaultTargetPlatform == TargetPlatform.android ||
        defaultTargetPlatform == TargetPlatform.iOS ||
        defaultTargetPlatform == TargetPlatform.macOS;
  }
}
