import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:magmug/app_core.dart';

Future<String?> ensurePushDeviceToken(String? currentToken) async {
  if (!AppPushSupport.isSupported ||
      (currentToken != null && currentToken.trim().isNotEmpty)) {
    return currentToken;
  }

  Object? lastError;
  for (var attempt = 0; attempt < 3; attempt++) {
    try {
      final nextToken = await FirebaseMessaging.instance.getToken();
      if (nextToken != null && nextToken.trim().isNotEmpty) {
        return nextToken;
      }
    } catch (error) {
      lastError = error;
    }

    if (attempt < 2) {
      await Future<void>.delayed(Duration(seconds: attempt + 1));
    }
  }

  if (lastError != null) {
    debugPrint('FCM token alinamadi: $lastError');
  }

  return currentToken;
}

Future<bool> requestPushNotificationPermission() async {
  final settings = await FirebaseMessaging.instance.requestPermission(
    alert: true,
    badge: true,
    sound: true,
    provisional: false,
  );

  return settings.authorizationStatus == AuthorizationStatus.authorized ||
      settings.authorizationStatus == AuthorizationStatus.provisional;
}
