import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';
import 'package:magmug/core/models/payment_models.dart';
import 'package:magmug/core/models/public_settings_models.dart';
import 'package:magmug/core/network/app_auth_api.dart';
import 'package:magmug/core/providers/app_session_providers.dart';

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
  final api = AppAuthApi();
  try {
    return await api.fetchPublicSettings();
  } finally {
    api.close();
  }
});

final appCreditPackagesProvider = FutureProvider<List<AppCreditPackage>>((
  ref,
) async {
  final authState = ref.watch(appAuthProvider).asData?.value;
  if (authState == null || authState.token.trim().isEmpty) {
    return const <AppCreditPackage>[];
  }

  final api = AppAuthApi();
  final platform = currentMobileStorePlatform();
  try {
    return await api.fetchCreditPackages(authState.token, platform: platform);
  } finally {
    api.close();
  }
});

final appSubscriptionPackagesProvider =
    FutureProvider<List<AppSubscriptionPackage>>((ref) async {
      final authState = ref.watch(appAuthProvider).asData?.value;
      if (authState == null || authState.token.trim().isEmpty) {
        return const <AppSubscriptionPackage>[];
      }

      final api = AppAuthApi();
      final platform = currentMobileStorePlatform();
      try {
        return await api.fetchSubscriptionPackages(
          authState.token,
          platform: platform,
        );
      } finally {
        api.close();
      }
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
