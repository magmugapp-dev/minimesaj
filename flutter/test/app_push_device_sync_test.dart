import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:magmug/app_core.dart';
import 'package:magmug/app_push_device_sync.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
  });

  tearDown(() {
    debugDefaultTargetPlatformOverride = null;
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
