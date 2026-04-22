import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';

class PushMessagingBindings {
  final StreamSubscription<String> tokenRefreshSubscription;
  final StreamSubscription<RemoteMessage> messageSubscription;
  final StreamSubscription<RemoteMessage> messageOpenSubscription;
  final RemoteMessage? initialMessage;

  PushMessagingBindings({
    required this.tokenRefreshSubscription,
    required this.messageSubscription,
    required this.messageOpenSubscription,
    required this.initialMessage,
  });

  void cancel() {
    unawaited(tokenRefreshSubscription.cancel());
    unawaited(messageSubscription.cancel());
    unawaited(messageOpenSubscription.cancel());
  }
}

Future<PushMessagingBindings> bindPushMessaging({
  required void Function(RemoteMessage message) onForegroundMessage,
  required void Function(RemoteMessage message) onOpenedMessage,
  required void Function(String token) onTokenRefresh,
}) async {
  final messaging = FirebaseMessaging.instance;

  return PushMessagingBindings(
    tokenRefreshSubscription: messaging.onTokenRefresh.listen(onTokenRefresh),
    messageSubscription: FirebaseMessaging.onMessage.listen(
      onForegroundMessage,
    ),
    messageOpenSubscription: FirebaseMessaging.onMessageOpenedApp.listen(
      onOpenedMessage,
    ),
    initialMessage: await messaging.getInitialMessage(),
  );
}
