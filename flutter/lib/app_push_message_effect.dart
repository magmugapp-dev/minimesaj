import 'package:firebase_messaging/firebase_messaging.dart';

class PushMessageEffect {
  final bool refreshConversationFeed;
  final bool refreshNotificationsFeed;
  final Map<String, String>? payload;

  const PushMessageEffect({
    required this.refreshConversationFeed,
    required this.refreshNotificationsFeed,
    this.payload,
  });
}

PushMessageEffect resolvePushMessageEffect(
  RemoteMessage message, {
  bool includePayload = false,
}) {
  final type = message.data['tip']?.toString();

  return PushMessageEffect(
    refreshConversationFeed: type == 'yeni_mesaj',
    refreshNotificationsFeed: true,
    payload: includePayload
        ? <String, String>{
            for (final entry in message.data.entries)
              entry.key: entry.value.toString(),
          }
        : null,
  );
}
