import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:magmug/app_core.dart';
import 'package:pusher_reverb_flutter/pusher_reverb_flutter.dart' as reverb;

enum ChatRealtimeEventType {
  messageSent,
  messagesRead,
  aiStatus,
  conversationTyping,
}

@immutable
class ChatRealtimeEvent {
  final ChatRealtimeEventType type;
  final int conversationId;
  final Map<String, dynamic> payload;

  const ChatRealtimeEvent({
    required this.type,
    required this.conversationId,
    required this.payload,
  });
}

@immutable
class ChatRealtimeEventSignal {
  final int sequence;
  final ChatRealtimeEvent event;

  const ChatRealtimeEventSignal({required this.sequence, required this.event});
}

final chatRealtimeEventBusProvider = StateProvider<ChatRealtimeEventSignal?>(
  (ref) => null,
);

class ChatRealtimeSubscription {
  final Future<void> Function() _dispose;

  ChatRealtimeSubscription._(this._dispose);

  Future<void> dispose() => _dispose();
}

class ChatRealtimeEventRefreshDeduper {
  ChatRealtimeEventRefreshDeduper._();

  static final ChatRealtimeEventRefreshDeduper instance =
      ChatRealtimeEventRefreshDeduper._();
  static const Duration _ttl = Duration(seconds: 2);

  final Map<String, DateTime> _seenAtByKey = <String, DateTime>{};

  bool shouldRefresh(ChatRealtimeEvent event) {
    final now = DateTime.now();
    _seenAtByKey.removeWhere((_, seenAt) => now.difference(seenAt) > _ttl);

    final key = _keyFor(event);
    final seenAt = _seenAtByKey[key];
    if (seenAt != null && now.difference(seenAt) <= _ttl) {
      return false;
    }

    _seenAtByKey[key] = now;
    return true;
  }

  String _keyFor(ChatRealtimeEvent event) {
    final payload = event.payload;
    final stableId =
        payload['id'] ??
        payload['mesaj_id'] ??
        payload['client_message_id'] ??
        payload['okuyan_user_id'] ??
        payload['user_id'] ??
        payload['status'] ??
        payload['created_at'] ??
        payload['planned_at'] ??
        '';
    return '${event.type.name}:${event.conversationId}:$stableId';
  }
}

@visibleForTesting
class ChatRealtimeAuthGate {
  ChatRealtimeAuthGate({this.cooldown = const Duration(seconds: 30)});

  final Duration cooldown;
  final Map<String, Future<void>> _inFlightByChannel = <String, Future<void>>{};
  final Map<String, DateTime> _cooldownUntilByChannel = <String, DateTime>{};

  @visibleForTesting
  bool isCoolingDown(String channelName) {
    final cooldownUntil = _cooldownUntilByChannel[channelName];
    return cooldownUntil != null && DateTime.now().isBefore(cooldownUntil);
  }

  Future<void> run(
    String channelName,
    FutureOr<void> Function() subscribe,
  ) async {
    final cooldownUntil = _cooldownUntilByChannel[channelName];
    if (cooldownUntil != null && DateTime.now().isBefore(cooldownUntil)) {
      throw ApiException(
        'Realtime kanal yetkilendirmesi kisa sureligine beklemede: $channelName',
      );
    }

    final inFlight = _inFlightByChannel[channelName];
    if (inFlight != null) {
      await inFlight;
      return;
    }

    final future = Future<void>.sync(subscribe);
    _inFlightByChannel[channelName] = future;
    try {
      await future;
      _cooldownUntilByChannel.remove(channelName);
    } catch (error) {
      if (_isAuthRateLimitError(error)) {
        _cooldownUntilByChannel[channelName] = DateTime.now().add(cooldown);
      }
      rethrow;
    } finally {
      if (identical(_inFlightByChannel[channelName], future)) {
        _inFlightByChannel.remove(channelName);
      }
    }
  }

  bool _isAuthRateLimitError(Object error) {
    final text = error.toString().toLowerCase();
    return text.contains('429') ||
        text.contains('too many requests') ||
        text.contains('authenticationexception') ||
        text.contains('authentication failed');
  }
}

class ChatRealtimeService {
  ChatRealtimeService._();

  static final ChatRealtimeService instance = ChatRealtimeService._();

  reverb.ReverbClient? _client;
  Future<void>? _connectInFlight;
  String? _authToken;
  final ChatRealtimeAuthGate _authGate = ChatRealtimeAuthGate();

  Future<ChatRealtimeSubscription?> subscribeToUser({
    required String token,
    required int userId,
    required void Function(ChatRealtimeEvent event) onEvent,
  }) async {
    if (kIsWeb) {
      return null;
    }

    _authToken = token;

    final client = _ensureClient();
    await _ensureConnected(client);

    final channelName = 'private-kullanici.$userId';
    final channel = client.subscribeToPrivateChannel(channelName);
    await _authGate.run(channelName, () => channel.subscribe());

    final eventSubscription = channel.stream.listen(
      (event) {
        final eventType = _mapEventType(event.eventName);
        if (eventType == null) {
          return;
        }

        final payload = _normalizePayload(event.data);
        final payloadConversationId = _asInt(payload['sohbet_id']);
        if (payloadConversationId == null) {
          return;
        }

        onEvent(
          ChatRealtimeEvent(
            type: eventType,
            conversationId: payloadConversationId,
            payload: payload,
          ),
        );
      },
      onError: (Object error, StackTrace stackTrace) {
        debugPrint('User realtime event error: $error');
      },
    );

    return ChatRealtimeSubscription._(() async {
      await eventSubscription.cancel();
      channel.unsubscribe();
    });
  }

  reverb.ReverbClient _ensureClient() {
    final existing = _client;
    if (existing != null) {
      return existing;
    }

    final apiUri = Uri.parse(AppEnvironment.apiBaseUrl);
    final client = reverb.ReverbClient.instance(
      host: apiUri.host,
      port: AppEnvironment.reverbPort,
      appKey: AppEnvironment.reverbAppKey,
      useTLS: apiUri.scheme == 'https',
      authEndpoint: Uri.parse(
        '${AppEnvironment.apiBaseUrl}/',
      ).resolve('api/broadcasting/auth').toString(),
      authorizer: (_, _) async {
        final token = _authToken;
        if (token == null || token.trim().isEmpty) {
          throw const ApiException(
            'Gercek zamanli baglanti icin gecerli oturum bulunamadi.',
          );
        }

        return {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        };
      },
      onError: (error) {
        debugPrint('Reverb error: $error');
      },
    );

    _client = client;
    return client;
  }

  Future<void> _ensureConnected(reverb.ReverbClient client) async {
    if (client.connectionState == reverb.ConnectionState.connected &&
        client.socketId != null &&
        client.socketId!.trim().isNotEmpty) {
      return;
    }

    final pending = _connectInFlight;
    if (pending != null) {
      await pending;
      return;
    }

    final completer = Completer<void>();
    late final StreamSubscription<reverb.ConnectionState> stateSubscription;
    stateSubscription = client.onConnectionStateChange.listen((state) {
      if (!completer.isCompleted &&
          state == reverb.ConnectionState.connected &&
          client.socketId != null &&
          client.socketId!.trim().isNotEmpty) {
        completer.complete();
        return;
      }

      if (!completer.isCompleted && state == reverb.ConnectionState.error) {
        completer.completeError(
          ApiException(
            AppRuntimeText.instance.t(
              'realtimeConnectionFailed',
              'Gercek zamanli baglanti kurulamadi.',
            ),
          ),
        );
      }
    });

    final connectFuture = () async {
      try {
        await client.connect();
        if (client.connectionState == reverb.ConnectionState.connected &&
            client.socketId != null &&
            client.socketId!.trim().isNotEmpty) {
          return;
        }

        await completer.future.timeout(
          const Duration(seconds: 10),
          onTimeout: () {
            throw const ApiException(
              'Gercek zamanli baglanti zaman asimina ugradi.',
            );
          },
        );
      } finally {
        await stateSubscription.cancel();
      }
    }();

    _connectInFlight = connectFuture;

    try {
      await connectFuture;
    } finally {
      if (identical(_connectInFlight, connectFuture)) {
        _connectInFlight = null;
      }
    }
  }

  static ChatRealtimeEventType? _mapEventType(String eventName) {
    return switch (eventName) {
      'mesaj.gonderildi' ||
      '.mesaj.gonderildi' => ChatRealtimeEventType.messageSent,
      'yapay_zeka.cevap_hazir' ||
      '.yapay_zeka.cevap_hazir' => ChatRealtimeEventType.messageSent,
      'mesajlar.okundu' ||
      '.mesajlar.okundu' => ChatRealtimeEventType.messagesRead,
      'ai.turn.status' || '.ai.turn.status' => ChatRealtimeEventType.aiStatus,
      'sohbet.typing' ||
      '.sohbet.typing' => ChatRealtimeEventType.conversationTyping,
      _ => null,
    };
  }

  static Map<String, dynamic> _normalizePayload(Object? raw) {
    if (raw is Map<String, dynamic>) {
      return raw;
    }

    if (raw is Map) {
      return raw.map((key, value) => MapEntry(key.toString(), value));
    }

    if (raw is String && raw.trim().isNotEmpty) {
      try {
        final decoded = jsonDecode(raw);
        if (decoded is Map<String, dynamic>) {
          return decoded;
        }
        if (decoded is Map) {
          return decoded.map((key, value) => MapEntry(key.toString(), value));
        }
      } catch (_) {}
    }

    return const {};
  }

  static int? _asInt(Object? value) {
    return switch (value) {
      final int intValue => intValue,
      final num numValue => numValue.toInt(),
      final String stringValue => int.tryParse(stringValue),
      _ => null,
    };
  }
}
