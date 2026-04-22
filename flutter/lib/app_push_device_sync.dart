import 'package:flutter/foundation.dart';
import 'package:magmug/app_core.dart';

typedef RegisterNotificationDevice =
    Future<void> Function(
      String authToken, {
      required String deviceToken,
      required String platform,
      required bool notificationPermission,
      String? languageCode,
    });

typedef UnregisterNotificationDevice =
    Future<void> Function(String authToken, {required String deviceToken});

Future<void> syncNotificationDevice(
  AppAuthState? session, {
  required String deviceToken,
  required bool permissionGranted,
  String? previousAuthToken,
  String? previousDeviceToken,
  String? languageCode,
  RegisterNotificationDevice? registerDevice,
  UnregisterNotificationDevice? unregisterDevice,
}) async {
  final normalizedDeviceToken = deviceToken.trim();
  if (normalizedDeviceToken.isEmpty) {
    return;
  }

  AppAuthApi? api;
  if (registerDevice == null || unregisterDevice == null) {
    api = AppAuthApi();
  }

  try {
    final authToken = session?.token.trim();
    final oldAuthToken = previousAuthToken?.trim();
    final oldDeviceToken = previousDeviceToken?.trim();
    final hasPreviousAuthToken =
        oldAuthToken != null &&
        oldAuthToken.isNotEmpty &&
        oldAuthToken != authToken;
    final hasPreviousDeviceToken =
        oldDeviceToken != null &&
        oldDeviceToken.isNotEmpty &&
        oldDeviceToken != normalizedDeviceToken;
    final unregister = unregisterDevice ?? api!.unregisterNotificationDevice;

    if (hasPreviousAuthToken) {
      try {
        await unregister(
          oldAuthToken,
          deviceToken: oldDeviceToken?.isNotEmpty == true
              ? oldDeviceToken!
              : normalizedDeviceToken,
        );
      } catch (_) {}
    } else if (hasPreviousDeviceToken &&
        authToken != null &&
        authToken.isNotEmpty) {
      try {
        await unregister(authToken, deviceToken: oldDeviceToken);
      } catch (_) {}
    }

    if (authToken == null || authToken.trim().isEmpty) {
      return;
    }

    final notificationsEnabled = session?.user?.notificationsEnabled != false;
    final register = registerDevice ?? api!.registerNotificationDevice;
    await register(
      authToken,
      deviceToken: normalizedDeviceToken,
      platform: currentPushPlatformName,
      notificationPermission: permissionGranted && notificationsEnabled,
      languageCode: (languageCode == null || languageCode.trim().isEmpty)
          ? AppPreferencesStorage.fallbackLanguage().code
          : languageCode,
    );
  } finally {
    api?.close();
  }
}

String get currentPushPlatformName {
  switch (defaultTargetPlatform) {
    case TargetPlatform.android:
      return 'android';
    case TargetPlatform.iOS:
    case TargetPlatform.macOS:
      return 'ios';
    default:
      return 'web';
  }
}
