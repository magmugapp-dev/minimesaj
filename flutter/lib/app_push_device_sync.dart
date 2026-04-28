import 'dart:convert';

import 'package:crypto/crypto.dart';
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
  dynamic prefs;
  try {
    prefs = await AppHiveBoxes.preferences();
  } catch (_) {
    prefs = null;
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
      await prefs?.delete(_pushDeviceSyncFingerprintKey);
      return;
    }

    final notificationsEnabled = session?.user?.notificationsEnabled != false;
    final resolvedLanguageCode =
        (languageCode == null || languageCode.trim().isEmpty)
        ? AppPreferencesStorage.fallbackLanguage().code
        : languageCode.trim();
    final fingerprint = _pushDeviceSyncFingerprint(
      userId: session?.user?.id,
      deviceToken: normalizedDeviceToken,
      platform: currentPushPlatformName,
      permissionGranted: permissionGranted && notificationsEnabled,
      languageCode: resolvedLanguageCode,
    );
    if (!hasPreviousAuthToken &&
        !hasPreviousDeviceToken &&
        prefs?.get(_pushDeviceSyncFingerprintKey)?.toString() == fingerprint) {
      return;
    }

    final register = registerDevice ?? api!.registerNotificationDevice;
    await register(
      authToken,
      deviceToken: normalizedDeviceToken,
      platform: currentPushPlatformName,
      notificationPermission: permissionGranted && notificationsEnabled,
      languageCode: resolvedLanguageCode,
    );
    await prefs?.put(_pushDeviceSyncFingerprintKey, fingerprint);
  } finally {
    api?.close();
  }
}

const String _pushDeviceSyncFingerprintKey = 'push.device.sync.fingerprint';

String _pushDeviceSyncFingerprint({
  required int? userId,
  required String deviceToken,
  required String platform,
  required bool permissionGranted,
  required String languageCode,
}) {
  return sha256
      .convert(
        utf8.encode(
          '$userId|$platform|$permissionGranted|$languageCode|$deviceToken',
        ),
      )
      .toString();
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
