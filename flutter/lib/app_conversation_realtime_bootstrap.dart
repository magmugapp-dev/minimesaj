import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:magmug/app_core.dart';
import 'package:magmug/features/chat/chat_local_store.dart';
import 'package:magmug/features/chat/chat_realtime.dart';
import 'package:magmug/features/home/models/chat_preview.dart';
import 'package:magmug/features/home/providers/home_chats_provider.dart';

class ConversationRealtimeBootstrap extends ConsumerStatefulWidget {
  final Widget child;

  const ConversationRealtimeBootstrap({super.key, required this.child});

  @override
  ConsumerState<ConversationRealtimeBootstrap> createState() =>
      _ConversationRealtimeBootstrapState();
}

class _ConversationRealtimeBootstrapState
    extends ConsumerState<ConversationRealtimeBootstrap> {
  final Map<int, ChatRealtimeSubscription> _subscriptions =
      <int, ChatRealtimeSubscription>{};
  Set<int> _subscribedConversationIds = <int>{};
  String? _subscribedToken;
  int? _currentUserId;

  @override
  void dispose() {
    for (final subscription in _subscriptions.values) {
      unawaited(subscription.dispose());
    }
    _subscriptions.clear();
    super.dispose();
  }

  Future<void> _syncSubscriptions({
    required AppAuthState? session,
    required List<ChatPreview> chats,
  }) async {
    final token = session?.token;
    final currentUserId = session?.user?.id;

    if (token == null || token.trim().isEmpty || currentUserId == null) {
      if (_subscriptions.isEmpty) {
        return;
      }

      for (final subscription in _subscriptions.values) {
        await subscription.dispose();
      }
      _subscriptions.clear();
      _subscribedConversationIds = <int>{};
      _subscribedToken = null;
      _currentUserId = null;
      return;
    }

    final nextConversationIds = chats
        .map((chat) => chat.conversation?.id)
        .whereType<int>()
        .toSet();

    final authContextChanged =
        _subscribedToken != token || _currentUserId != currentUserId;
    if (authContextChanged && _subscriptions.isNotEmpty) {
      for (final subscription in _subscriptions.values) {
        await subscription.dispose();
      }
      _subscriptions.clear();
      _subscribedConversationIds = <int>{};
    }

    if (_subscribedToken == token &&
        _currentUserId == currentUserId &&
        setEquals(_subscribedConversationIds, nextConversationIds)) {
      return;
    }

    final removedConversationIds = _subscribedConversationIds.difference(
      nextConversationIds,
    );
    for (final conversationId in removedConversationIds) {
      final subscription = _subscriptions.remove(conversationId);
      await subscription?.dispose();
    }

    _subscribedToken = token;
    _currentUserId = currentUserId;
    _subscribedConversationIds = nextConversationIds;

    final newConversationIds = nextConversationIds.difference(
      _subscriptions.keys.toSet(),
    );
    for (final conversationId in newConversationIds) {
      try {
        final subscription = await ChatRealtimeService.instance
            .subscribeToConversation(
              token: token,
              conversationId: conversationId,
              onEvent: _handleRealtimeEvent,
            );
        if (subscription != null) {
          _subscriptions[conversationId] = subscription;
        }
      } catch (error) {
        debugPrint(
          'Conversation realtime subscribe error for $conversationId: $error',
        );
      }
    }
  }

  void _handleRealtimeEvent(ChatRealtimeEvent event) {
    unawaited(_applyRealtimeEvent(event));
  }

  Future<void> _applyRealtimeEvent(ChatRealtimeEvent event) async {
    final currentUserId = _currentUserId;
    if (currentUserId == null) {
      return;
    }

    final store = ChatLocalStore.instance;

    switch (event.type) {
      case ChatRealtimeEventType.conversationTyping:
        final actorId = _payloadInt(event.payload['user_id']);
        if (actorId == null || actorId == currentUserId) {
          return;
        }

        final typing = _payloadBool(event.payload['typing']);
        final statusText = _nullableString(
          event.payload['status_text']?.toString(),
        );
        await store.updateConversationPreviewRuntimeStatus(
          event.conversationId,
          aiStatus: typing ? 'typing' : null,
          aiStatusText: typing ? (statusText ?? 'Yaziyor...') : null,
          aiPlannedAt: null,
        );
        break;
      case ChatRealtimeEventType.aiStatus:
        await store.updateConversationPreviewRuntimeStatus(
          event.conversationId,
          aiStatus: _nullableString(event.payload['status']?.toString()),
          aiStatusText: _nullableString(
            event.payload['status_text']?.toString(),
          ),
          aiPlannedAt: DateTime.tryParse(
            event.payload['planned_at']?.toString() ?? '',
          ),
        );
        break;
      case ChatRealtimeEventType.messageSent:
        final senderId = _payloadInt(event.payload['gonderen_user_id']);
        if (senderId == null) {
          return;
        }

        await store.applyConversationMessageEvent(
          conversationId: event.conversationId,
          senderId: senderId,
          currentUserId: currentUserId,
          messageType: event.payload['mesaj_tipi']?.toString(),
          messageText: event.payload['mesaj_metni']?.toString(),
          createdAt: DateTime.tryParse(
            event.payload['created_at']?.toString() ?? '',
          ),
        );
        break;
      case ChatRealtimeEventType.messagesRead:
        final readerUserId = _payloadInt(event.payload['okuyan_user_id']);
        if (readerUserId == null) {
          return;
        }

        await store.applyConversationReadEvent(
          event.conversationId,
          readerUserId: readerUserId,
          currentUserId: currentUserId,
        );
        break;
    }

    if (!mounted) {
      return;
    }

    ref.read(conversationFeedRefreshProvider.notifier).state++;
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(appAuthProvider).asData?.value;
    final chats = ref.watch(homeChatsProvider).asData?.value ?? const [];

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }
      unawaited(_syncSubscriptions(session: session, chats: chats));
    });

    return widget.child;
  }
}

int? _payloadInt(Object? value) {
  return switch (value) {
    final int intValue => intValue,
    final num numValue => numValue.toInt(),
    final String stringValue => int.tryParse(stringValue),
    _ => null,
  };
}

bool _payloadBool(Object? value) {
  return switch (value) {
    final bool boolValue => boolValue,
    final num numValue => numValue != 0,
    final String stringValue =>
      stringValue == '1' || stringValue.toLowerCase() == 'true',
    _ => false,
  };
}

String? _nullableString(String? value) {
  final normalized = value?.trim();
  if (normalized == null || normalized.isEmpty) {
    return null;
  }
  return normalized;
}
