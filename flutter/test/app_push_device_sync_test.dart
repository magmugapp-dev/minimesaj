import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hive/hive.dart';
import 'package:magmug/app_core.dart';
import 'package:magmug/app_push_device_sync.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  late Directory hiveDirectory;

  setUpAll(() async {
    hiveDirectory = await Directory.systemTemp.createTemp(
      'magmug_hive_device_sync_test_',
    );
    Hive.init(hiveDirectory.path);
  });

  setUp(() async {
    await _clearHiveBoxes();
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
  });

  tearDown(() async {
    debugDefaultTargetPlatformOverride = null;
    await Hive.close();
  });

  tearDownAll(() async {
    if (await hiveDirectory.exists()) {
      await hiveDirectory.delete(recursive: true);
    }
  });

  AppAuthState session({
    required String token,
    bool notificationsEnabled = true,
  }) {
    return AppAuthState(
      token: token,
      user: AppUser(
        id: 1,
        firstName: 'Test',
        surname: 'User',
        username: 'testuser',
        notificationsEnabled: notificationsEnabled,
      ),
    );
  }

  test(
    'unregisters the same device token from the previous auth session',
    () async {
      final calls = <String>[];

      await syncNotificationDevice(
        session(token: 'new-auth-token'),
        deviceToken: 'device-token',
        permissionGranted: true,
        previousAuthToken: 'old-auth-token',
        languageCode: 'tr',
        registerDevice:
            (
              String authToken, {
              required String deviceToken,
              required String platform,
              required bool notificationPermission,
              String? languageCode,
            }) async {
              calls.add(
                'register:$authToken:$deviceToken:$platform:$notificationPermission:$languageCode',
              );
            },
        unregisterDevice:
            (String authToken, {required String deviceToken}) async {
              calls.add('unregister:$authToken:$deviceToken');
            },
      );

      expect(calls, [
        'unregister:old-auth-token:device-token',
        'register:new-auth-token:device-token:android:true:tr',
      ]);
    },
  );

  test('unregisters the old device token after an fcm token refresh', () async {
    final calls = <String>[];

    await syncNotificationDevice(
      session(token: 'auth-token'),
      deviceToken: 'new-device-token',
      permissionGranted: true,
      previousDeviceToken: 'old-device-token',
      registerDevice:
          (
            String authToken, {
            required String deviceToken,
            required String platform,
            required bool notificationPermission,
            String? languageCode,
          }) async {
            calls.add('register:$authToken:$deviceToken');
          },
      unregisterDevice:
          (String authToken, {required String deviceToken}) async {
            calls.add('unregister:$authToken:$deviceToken');
          },
    );

    expect(calls, [
      'unregister:auth-token:old-device-token',
      'register:auth-token:new-device-token',
    ]);
  });

  test(
    'registers permission as false when the user disabled notifications',
    () async {
      final calls = <String>[];

      await syncNotificationDevice(
        session(token: 'auth-token', notificationsEnabled: false),
        deviceToken: 'device-token',
        permissionGranted: true,
        registerDevice:
            (
              String authToken, {
              required String deviceToken,
              required String platform,
              required bool notificationPermission,
              String? languageCode,
            }) async {
              calls.add('register:$authToken:$notificationPermission');
            },
        unregisterDevice:
            (String authToken, {required String deviceToken}) async {},
      );

      expect(calls, ['register:auth-token:false']);
    },
  );
}

Future<void> _clearHiveBoxes() async {
  const boxes = [
    'app_session',
    'app_preferences',
    'app_content',
    'app_public_settings',
    'ai_prompt',
    'ai_characters',
    'ai_memory',
    'ai_pending_turns',
    'chat_messages',
    'chat_previews',
    'chat_outbox',
    'media_cache_index',
  ];

  for (final box in boxes) {
    try {
      if (Hive.isBoxOpen(box)) {
        await Hive.box<dynamic>(box).clear();
      } else {
        await Hive.deleteBoxFromDisk(box);
      }
    } catch (_) {}
  }
}
