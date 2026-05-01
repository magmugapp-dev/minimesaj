import 'dart:async';

import 'package:magmug/app_core.dart';
import 'package:magmug/core/ai/flutter_ai_turn_processor.dart';
import 'package:magmug/features/chat/chat_local_store.dart';
import 'package:magmug/features/chat/chat_realtime.dart';

class ConversationRealtimeBootstrap extends ConsumerStatefulWidget {
  final Widget child;

  const ConversationRealtimeBootstrap({super.key, required this.child});

  @override
  ConsumerState<ConversationRealtimeBootstrap> createState() =>
      _ConversationRealtimeBootstrapState();
}

class _ConversationRealtimeBootstrapState
    extends ConsumerState<ConversationRealtimeBootstrap> {
  ChatRealtimeSubscription? _subscription;
  Future<void>? _subscriptionSyncInFlight;
  String? _desiredToken;
  int? _desiredUserId;
  String? _subscribedToken;
  int? _currentUserId;
  DateTime? _subscriptionRetryAfter;
  Timer? _subscriptionRetryTimer;
  final Map<int, Timer> _aiTurnTimers = <int, Timer>{};
  final Map<int, Timer> _aiTypingTimers = <int, Timer>{};
  int _eventSequence = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }
      unawaited(
        _syncSubscription(session: ref.read(appAuthProvider).asData?.value),
      );
    });
  }

  @override
  void dispose() {
    final subscription = _subscription;
    if (subscription != null) {
      unawaited(subscription.dispose());
    }
    _subscription = null;
    _subscriptionSyncInFlight = null;
    _desiredToken = null;
    _desiredUserId = null;
    _subscriptionRetryAfter = null;
    _subscriptionRetryTimer?.cancel();
    _subscriptionRetryTimer = null;
    for (final timer in _aiTurnTimers.values) {
      timer.cancel();
    }
    _aiTurnTimers.clear();
    for (final timer in _aiTypingTimers.values) {
      timer.cancel();
    }
    _aiTypingTimers.clear();
    super.dispose();
  }

  Future<void> _syncSubscription({required AppAuthState? session}) async {
    final sessionToken = session?.token.trim();
    final token = sessionToken == null || sessionToken.isEmpty
        ? null
        : sessionToken;
    final userId = session?.user?.id;

    if (_desiredToken != token || _desiredUserId != userId) {
      _subscriptionRetryAfter = null;
      _subscriptionRetryTimer?.cancel();
      _subscriptionRetryTimer = null;
    }

    _desiredToken = token;
    _desiredUserId = userId;

    final existing = _subscriptionSyncInFlight;
    if (existing != null) {
      return existing;
    }

    final future = _drainSubscriptionSync();
    _subscriptionSyncInFlight = future;
    try {
      await future;
    } finally {
      if (identical(_subscriptionSyncInFlight, future)) {
        _subscriptionSyncInFlight = null;
      }
    }
  }

  Future<void> _drainSubscriptionSync() async {
    while (mounted) {
      final token = _desiredToken;
      final currentUserId = _desiredUserId;
      final hasValidTarget =
          token != null && token.trim().isNotEmpty && currentUserId != null;

      if (!hasValidTarget) {
        if (_subscription == null &&
            _subscribedToken == null &&
            _currentUserId == null) {
          return;
        }
        await _disposeCurrentSubscription();
        if (_desiredToken == token && _desiredUserId == currentUserId) {
          return;
        }
        continue;
      }

      if (_subscribedToken == token && _currentUserId == currentUserId) {
        return;
      }

      final retryAfter = _subscriptionRetryAfter;
      if (retryAfter != null) {
        if (DateTime.now().isBefore(retryAfter)) {
          return;
        }
        _subscriptionRetryAfter = null;
      }

      await _replaceSubscription(token: token, userId: currentUserId);
      if (_desiredToken == token && _desiredUserId == currentUserId) {
        return;
      }
    }
  }

  Future<void> _replaceSubscription({
    required String token,
    required int userId,
  }) async {
    await _disposeCurrentSubscription();

    try {
      final subscription = await ChatRealtimeService.instance.subscribeToUser(
        token: token,
        userId: userId,
        onEvent: _handleRealtimeEvent,
        onReconnect: () {
          AppCacheSyncCoordinator.instance.scheduleDebounced(
            token: token,
            ownerUserId: userId,
            force: true,
            delay: Duration.zero,
            onComplete: (didSync) {
              if (!mounted || !didSync) {
                return;
              }
              ref.read(conversationFeedRefreshProvider.notifier).state++;
            },
          );
        },
      );
      if (subscription == null &&
          mounted &&
          _desiredToken == token &&
          _desiredUserId == userId) {
        _subscribedToken = token;
        _currentUserId = userId;
        _subscriptionRetryAfter = null;
        _subscriptionRetryTimer?.cancel();
        _subscriptionRetryTimer = null;
        return;
      }
      if (!mounted ||
          _desiredToken != token ||
          _desiredUserId != userId ||
          subscription == null) {
        await subscription?.dispose();
        return;
      }

      _subscription = subscription;
      _subscribedToken = token;
      _currentUserId = userId;
      _subscriptionRetryAfter = null;
      _subscriptionRetryTimer?.cancel();
      _subscriptionRetryTimer = null;
    } catch (error) {
      if (_desiredToken == token && _desiredUserId == userId) {
        _subscription = null;
        _subscribedToken = null;
        _currentUserId = null;
        if (_looksLikeAuthRateLimit(error)) {
          final retryAfter = DateTime.now().add(const Duration(seconds: 30));
          _subscriptionRetryAfter = retryAfter;
          _scheduleSubscriptionRetry(retryAfter, token: token, userId: userId);
        }
      }
      debugPrint('User realtime subscribe error: $error');
    }
  }

  void _scheduleSubscriptionRetry(
    DateTime retryAfter, {
    required String token,
    required int userId,
  }) {
    _subscriptionRetryTimer?.cancel();
    final delay = retryAfter.difference(DateTime.now());
    _subscriptionRetryTimer = Timer(
      delay.isNegative ? Duration.zero : delay,
      () {
        if (!mounted || _desiredToken != token || _desiredUserId != userId) {
          return;
        }
        _subscriptionRetryAfter = null;
        unawaited(
          _syncSubscription(session: ref.read(appAuthProvider).asData?.value),
        );
      },
    );
  }

  Future<void> _disposeCurrentSubscription() async {
    final subscription = _subscription;
    _subscription = null;
    _subscribedToken = null;
    _currentUserId = null;
    await subscription?.dispose();
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
          ownerUserId: currentUserId,
          aiStatus: typing ? 'typing' : null,
          aiStatusText: typing ? (statusText ?? 'Yaziyor...') : null,
          aiPlannedAt: null,
        );
        break;
      case ChatRealtimeEventType.aiStatus:
        final plannedAt = DateTime.tryParse(
          event.payload['planned_at']?.toString() ?? '',
        );
        final aiStatus = _nullableString(event.payload['status']?.toString());
        await store.updateConversationPreviewRuntimeStatus(
          event.conversationId,
          ownerUserId: currentUserId,
          aiStatus: aiStatus,
          aiStatusText: _nullableString(
            event.payload['status_text']?.toString(),
          ),
          aiPlannedAt: plannedAt,
        );
        final token = _desiredToken;
        if (token != null &&
            token.trim().isNotEmpty &&
            (aiStatus == 'pending' || aiStatus == 'deferred')) {
          unawaited(
            FlutterAiTurnProcessor.instance.cacheRealtimeTurn(
              payload: event.payload,
              conversationId: event.conversationId,
              token: token,
              ownerUserId: currentUserId,
            ),
          );
        }
        _scheduleAiTurnRun(
          conversationId: event.conversationId,
          plannedAt: plannedAt,
          ownerUserId: currentUserId,
        );
        if (aiStatus == 'typing') {
          _aiTypingTimers[event.conversationId]?.cancel();
          _aiTypingTimers[event.conversationId] = Timer(
            const Duration(seconds: 30),
            () => _clearTypingStatus(event.conversationId, currentUserId),
          );
        } else {
          _aiTypingTimers.remove(event.conversationId)?.cancel();
        }
        break;
      case ChatRealtimeEventType.messageSent:
        var localPatchApplied = false;
        final message = AppAuthApi.conversationMessageFromJson(event.payload);
        if (message != null && message.conversationId == event.conversationId) {
          localPatchApplied = await store.applyRealtimeMessageEvent(
            ownerUserId: currentUserId,
            currentUserId: currentUserId,
            message: message,
          );
        }

        final senderId =
            message?.senderId ?? _payloadInt(event.payload['gonderen_user_id']);
        if (senderId == null) {
          return;
        }
        if (senderId != currentUserId) {
          await store.updateConversationPreviewRuntimeStatus(
            event.conversationId,
            ownerUserId: currentUserId,
            aiStatus: null,
            aiStatusText: null,
            aiPlannedAt: null,
          );
          _aiTypingTimers.remove(event.conversationId)?.cancel();
          final messageSoundsEnabled =
              ref
                  .read(appAuthProvider)
                  .asData
                  ?.value
                  ?.user
                  ?.messageSoundsEnabled ??
              true;
          unawaited(
            AppMessageSoundService.instance.playReceive(
              enabled: messageSoundsEnabled,
            ),
          );
        }

        if (!localPatchApplied) {
          localPatchApplied = await store.applyConversationMessageEvent(
            ownerUserId: currentUserId,
            conversationId: event.conversationId,
            senderId: senderId,
            currentUserId: currentUserId,
            messageType: event.payload['mesaj_tipi']?.toString(),
            messageText: event.payload['mesaj_metni']?.toString(),
            createdAt: DateTime.tryParse(
              event.payload['created_at']?.toString() ?? '',
            ),
          );
        }
        if (!localPatchApplied) {
          final token = _desiredToken;
          if (token != null) {
            AppCacheSyncCoordinator.instance.scheduleDebounced(
              token: token,
              ownerUserId: currentUserId,
              force: true,
              onComplete: (didSync) {
                if (!mounted || !didSync) {
                  return;
                }
                ref.read(conversationFeedRefreshProvider.notifier).state++;
              },
            );
          }
        }
        break;
      case ChatRealtimeEventType.messagesRead:
        final readerUserId = _payloadInt(event.payload['okuyan_user_id']);
        if (readerUserId == null) {
          return;
        }

        await store.applyConversationReadEvent(
          event.conversationId,
          ownerUserId: currentUserId,
          readerUserId: readerUserId,
          currentUserId: currentUserId,
        );
        break;
      case ChatRealtimeEventType.conversationCleared:
        await store.clearConversation(
          ownerUserId: currentUserId,
          conversationId: event.conversationId,
        );
        break;
      case ChatRealtimeEventType.onlineStatus:
        final userId = _payloadInt(event.payload['user_id']);
        if (userId == null) {
          return;
        }
        await store.updatePeerOnlineStatus(
          ownerUserId: currentUserId,
          peerId: userId,
          online: _payloadBool(event.payload['is_online']),
        );
        break;
    }

    if (!mounted) {
      return;
    }

    ref.read(chatRealtimeEventBusProvider.notifier).state =
        ChatRealtimeEventSignal(sequence: ++_eventSequence, event: event);

    if (ChatRealtimeEventRefreshDeduper.instance.shouldRefresh(event)) {
      ref.read(conversationFeedRefreshProvider.notifier).state++;
    }
  }

  void _clearTypingStatus(int conversationId, int ownerUserId) {
    _aiTypingTimers.remove(conversationId);
    if (!mounted) return;
    unawaited(
      ChatLocalStore.instance.updateConversationPreviewRuntimeStatus(
        conversationId,
        ownerUserId: ownerUserId,
        aiStatus: null,
        aiStatusText: null,
        aiPlannedAt: null,
      ),
    );
  }

  void _scheduleAiTurnRun({
    required int conversationId,
    required DateTime? plannedAt,
    required int ownerUserId,
  }) {
    _aiTurnTimers.remove(conversationId)?.cancel();
    final token = _desiredToken;
    if (token == null || token.trim().isEmpty || plannedAt == null) {
      return;
    }

    final delay = plannedAt.difference(DateTime.now());
    _aiTurnTimers[conversationId] = Timer(
      delay.isNegative ? Duration.zero : delay,
      () {
        _aiTurnTimers.remove(conversationId);
        if (!mounted ||
            _desiredToken != token ||
            _desiredUserId != ownerUserId) {
          return;
        }
        unawaited(
          FlutterAiTurnProcessor.instance.run(
            token: token,
            ownerUserId: ownerUserId,
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<AsyncValue<AppAuthState?>>(appAuthProvider, (previous, next) {
      if (!mounted) {
        return;
      }
      unawaited(_syncSubscription(session: next.asData?.value));
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

bool _looksLikeAuthRateLimit(Object error) {
  final text = error.toString().toLowerCase();
  return text.contains('429') ||
      text.contains('too many requests') ||
      text.contains('authenticationexception') ||
      text.contains('authentication failed');
}
