import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:magmug/app_core.dart';
import 'package:pusher_reverb_flutter/pusher_reverb_flutter.dart' as reverb;

enum ChatRealtimeEventType { messageSent, messagesRead, aiStatus }

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

class ChatRealtimeSubscription {
  final Future<void> Function() _dispose;

  ChatRealtimeSubscription._(this._dispose);

  Future<void> dispose() => _dispose();
}

class ChatRealtimeService {
  ChatRealtimeService._();

  static final ChatRealtimeService instance = ChatRealtimeService._();

  reverb.ReverbClient? _client;
  Future<void>? _connectInFlight;
  String? _authToken;

  Future<ChatRealtimeSubscription?> subscribeToConversation({
    required String token,
    required int conversationId,
    required void Function(ChatRealtimeEvent event) onEvent,
  }) async {
    if (kIsWeb) {
      return null;
    }

    _authToken = token;

    final client = _ensureClient();
    await _ensureConnected(client);

    final channelName = 'private-sohbet.$conversationId';
    final channel = client.subscribeToPrivateChannel(channelName);
    await channel.subscribe();

    final eventSubscription = channel.stream.listen(
      (event) {
        final eventType = _mapEventType(event.eventName);
        if (eventType == null) {
          return;
        }

        final payload = _normalizePayload(event.data);
        final payloadConversationId =
            _asInt(payload['sohbet_id']) ?? conversationId;
        if (payloadConversationId != conversationId) {
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
        debugPrint('Chat realtime event error: $error');
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
          const ApiException('Gercek zamanli baglanti kurulamadi.'),
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
      'ai.turn.status' ||
      '.ai.turn.status' => ChatRealtimeEventType.aiStatus,
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
