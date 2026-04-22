import 'dart:convert';

import 'package:magmug/app_core.dart';
import 'package:magmug/features/chat/chat_flow.dart';
import 'package:magmug/features/match/match_flow.dart';
import 'package:magmug/features/notifications/notifications_flow.dart';
import 'package:magmug/features/profile/profile_flow.dart';

Future<void> handlePendingPushPayload(
  BuildContext context, {
  required Map<String, String> payload,
  required String authToken,
  required int currentUserId,
}) async {
  final route = payload['rota']?.trim().toLowerCase();
  if (route == 'chat') {
    final conversationId = extractPushConversationId(payload);
    if (conversationId == null) {
      return;
    }

    final api = AppAuthApi();
    try {
      final conversations = await api.fetchConversations(
        authToken,
        currentUserId: currentUserId,
      );
      final conversation = conversations
          .where((item) => item.id == conversationId)
          .firstOrNull;
      if (!context.mounted || conversation == null) {
        return;
      }

      await Navigator.of(context, rootNavigator: true).push(
        cupertinoRoute(
          ChatScreen(mode: ChatScreenMode.messages, conversation: conversation),
        ),
      );
    } finally {
      api.close();
    }
    return;
  }

  if (!context.mounted) {
    return;
  }

  if (route == 'matches') {
    await Navigator.of(context, rootNavigator: true).push(
      cupertinoRoute(const MatchFoundScreen(theme: MatchFoundTheme.normal)),
    );
    return;
  }

  if (route == 'wallet') {
    await showCupertinoModalPopup<void>(
      context: context,
      builder: (_) => const JetonPurchaseSheet(),
      useRootNavigator: true,
    );
    return;
  }

  if (route == 'incoming_likes') {
    await Navigator.of(
      context,
      rootNavigator: true,
    ).push(cupertinoRoute(const PaywallScreen()));
    return;
  }

  await Navigator.of(
    context,
    rootNavigator: true,
  ).push(cupertinoRoute(const NotificationsScreen()));
}

int? extractPushConversationId(Map<String, String> payload) {
  final direct = int.tryParse(payload['sohbet_id']?.trim() ?? '');
  if (direct != null) {
    return direct;
  }

  final routeParamsRaw = payload['rota_parametreleri']?.trim();
  if (routeParamsRaw == null || routeParamsRaw.isEmpty) {
    return null;
  }

  try {
    final decoded = jsonDecode(routeParamsRaw);
    if (decoded is Map<String, dynamic>) {
      return int.tryParse(decoded['sohbet_id']?.toString() ?? '');
    }
    if (decoded is Map) {
      return int.tryParse(decoded['sohbet_id']?.toString() ?? '');
    }
  } catch (_) {}

  return null;
}
