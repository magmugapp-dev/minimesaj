import 'dart:async';

import 'package:magmug/app_core.dart';
import 'package:magmug/features/chat/chat_local_store.dart';
import 'package:magmug/features/home/models/chat_preview.dart';

const Duration _homeChatsRefreshCooldown = Duration(seconds: 20);
DateTime? _lastHomeChatsRefreshAt;
bool _homeChatsRefreshInFlight = false;

final homeChatsProvider = FutureProvider.autoDispose<List<ChatPreview>>((
  ref,
) async {
  ref.watch(conversationFeedRefreshProvider);
  final session = await ref.watch(appAuthProvider.future);
  final token = session?.token;
  final userId = session?.user?.id;
  if (token == null || token.trim().isEmpty || userId == null) {
    return const [];
  }

  final localStore = ChatLocalStore.instance;
  final cachedConversations = await localStore.getConversationPreviews();
  if (cachedConversations.isNotEmpty) {
    if (_shouldRefreshHomeChats()) {
      unawaited(
        _refreshHomeChatsFromApi(
          ref,
          token: token,
          userId: userId,
          previous: cachedConversations,
        ),
      );
    }
    return cachedConversations.map(_chatPreviewFromConversation).toList();
  }

  final api = AppAuthApi();
  try {
    final conversations = await api.fetchConversations(
      token,
      currentUserId: userId,
    );
    await localStore.upsertConversationPreviews(conversations);
    return conversations.map(_chatPreviewFromConversation).toList();
  } finally {
    api.close();
  }
});

bool _shouldRefreshHomeChats() {
  if (_homeChatsRefreshInFlight) {
    return false;
  }

  final lastRefresh = _lastHomeChatsRefreshAt;
  if (lastRefresh == null) {
    return true;
  }

  return DateTime.now().difference(lastRefresh) >= _homeChatsRefreshCooldown;
}

Future<void> _refreshHomeChatsFromApi(
  Ref ref, {
  required String token,
  required int userId,
  required List<AppConversationPreview> previous,
}) async {
  _homeChatsRefreshInFlight = true;
  final api = AppAuthApi();
  try {
    final conversations = await api.fetchConversations(
      token,
      currentUserId: userId,
    );
    await ChatLocalStore.instance.upsertConversationPreviews(conversations);
    _lastHomeChatsRefreshAt = DateTime.now();
    if (!_sameConversationList(previous, conversations)) {
      ref.read(conversationFeedRefreshProvider.notifier).state++;
    }
  } catch (_) {
    // Keep cached data visible when refresh fails.
  } finally {
    _homeChatsRefreshInFlight = false;
    api.close();
  }
}

bool _sameConversationList(
  List<AppConversationPreview> left,
  List<AppConversationPreview> right,
) {
  if (left.length != right.length) {
    return false;
  }

  for (var index = 0; index < left.length; index++) {
    final a = left[index];
    final b = right[index];
    if (a.id != b.id ||
        a.peerName != b.peerName ||
        a.peerUsername != b.peerUsername ||
        a.online != b.online ||
        a.lastMessage != b.lastMessage ||
        a.lastMessageType != b.lastMessageType ||
        !_sameMoment(a.lastMessageAt, b.lastMessageAt) ||
        a.unreadCount != b.unreadCount ||
        a.myMessageRead != b.myMessageRead ||
        a.aiStatus != b.aiStatus ||
        a.aiStatusText != b.aiStatusText ||
        !_sameMoment(a.aiPlannedAt, b.aiPlannedAt)) {
      return false;
    }
  }

  return true;
}

bool _sameMoment(DateTime? left, DateTime? right) {
  if (left == null || right == null) {
    return left == right;
  }

  return left.millisecondsSinceEpoch == right.millisecondsSinceEpoch;
}

ChatPreview _chatPreviewFromConversation(AppConversationPreview conversation) {
  return ChatPreview(
    conversation: conversation,
    name: conversation.peerName,
    avatarUrl: conversation.peerProfileImageUrl,
    lastMessage: conversation.lastMessage,
    statusText: conversation.statusText,
    time: _formatConversationTime(conversation.lastMessageAt),
    unread: conversation.unreadCount,
    myMessageRead: conversation.myMessageRead,
    online: conversation.online,
  );
}

String _formatConversationTime(DateTime? value) {
  if (value == null) {
    return '';
  }

  final local = value.toLocal();
  final hour = local.hour.toString().padLeft(2, '0');
  final minute = local.minute.toString().padLeft(2, '0');
  return '$hour:$minute';
}
