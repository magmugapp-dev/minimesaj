import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:magmug/app_core.dart';
import 'package:magmug/features/chat/chat_local_store.dart';
import 'package:magmug/features/chat/chat_realtime.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
  });

  test('syncMobile parses has_more from the mobile sync payload', () async {
    final api = AppAuthApi(
      client: MockClient((request) async {
        expect(request.url.path, endsWith('/api/mobile/sync'));
        return http.Response(
          jsonEncode({
            'sync_token': 'mobile-sync:v1:cursor',
            'has_more': true,
            'match_summary': {},
            'messages': [],
            'notifications': {'unread_count': 0, 'items': []},
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    final result = await api.syncMobile('token-1');
    api.close();

    expect(result.syncToken, 'mobile-sync:v1:cursor');
    expect(result.hasMore, isTrue);
  });

  test('uploadMobileMedia sends m4a files as audio/mp4', () async {
    final directory = await Directory.systemTemp.createTemp('magmug_voice_');
    addTearDown(() => directory.delete(recursive: true));
    final file = File('${directory.path}/voice.m4a');
    await file.writeAsBytes(<int>[0, 1, 2, 3, 4, 5]);

    final api = AppAuthApi(
      client: MockClient((request) async {
        expect(request.url.path, endsWith('/api/mobile/uploads'));
        final body = latin1.decode(request.bodyBytes).toLowerCase();
        expect(body, contains('content-type: audio/mp4'));
        expect(body, contains('name="mesaj_tipi"'));
        expect(body, contains('ses'));

        return http.Response(
          jsonEncode({
            'client_upload_id': 'upload-voice-1',
            'dosya_yolu': 'mesajlar/7/ses/voice.m4a',
            'dosya_url': 'http://localhost/storage/mesajlar/7/ses/voice.m4a',
            'mime_tipi': 'audio/mp4',
            'boyut': 6,
          }),
          201,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    final result = await api.uploadMobileMedia(
      'token-1',
      filePath: file.path,
      clientUploadId: 'upload-voice-1',
      messageType: 'ses',
    );
    api.close();

    expect(result.filePath, 'mesajlar/7/ses/voice.m4a');
    expect(result.mimeType, 'audio/mp4');
  });

  test('realtime message payload parses full mobile message fields', () {
    final message = AppAuthApi.conversationMessageFromJson({
      'id': 91,
      'sohbet_id': 12,
      'gonderen_user_id': 7,
      'gonderen': {
        'id': 7,
        'ad': 'Ada',
        'profil_resmi': 'https://example.test/ada.jpg',
      },
      'mesaj_tipi': 'ses',
      'mesaj_metni': null,
      'dosya_yolu': 'https://example.test/voice.m4a',
      'dosya_suresi': 4,
      'okundu_mu': 1,
      'ai_tarafindan_uretildi_mi': false,
      'dil_kodu': 'tr',
      'dil_adi': 'Turkce',
      'client_message_id': 'client-voice-1',
      'created_at': '2026-04-25T12:00:00Z',
    });

    expect(message, isNotNull);
    expect(message!.id, 91);
    expect(message.conversationId, 12);
    expect(message.senderId, 7);
    expect(message.senderName, 'Ada');
    expect(message.type, 'ses');
    expect(message.fileUrl, 'https://example.test/voice.m4a');
    expect(message.fileDuration, const Duration(seconds: 4));
    expect(message.isRead, isTrue);
    expect(message.clientMessageId, 'client-voice-1');
  });

  test('syncDelta continues while the server reports more pages', () async {
    await AppSessionStorage.saveSession(
      const AuthenticatedSession(
        token: 'token-1',
        user: AppUser(
          id: 7,
          firstName: 'Test',
          surname: 'User',
          username: 'tu',
        ),
      ),
    );
    await AppSessionStorage.saveMobileSyncToken('start-token');

    final requestBodies = <Map<String, dynamic>>[];
    final engine = AppSyncEngine.testing(
      apiFactory: () => AppAuthApi(
        client: MockClient((request) async {
          requestBodies.add(jsonDecode(request.body) as Map<String, dynamic>);
          final isFirstPage = requestBodies.length == 1;

          return http.Response(
            jsonEncode({
              'sync_token': isFirstPage ? 'cursor-token' : 'final-token',
              'has_more': isFirstPage,
              'user': {
                'id': 7,
                'ad': 'Test',
                'soyad': 'User',
                'kullanici_adi': 'tu',
              },
              'match_summary': {},
              'conversations': [],
              'messages': [],
              'notifications': {'unread_count': 0, 'items': []},
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }),
      ),
    );

    await engine.syncDelta(token: 'token-1', ownerUserId: 7);

    expect(requestBodies, [
      {'sync_token': 'start-token'},
      {'sync_token': 'cursor-token'},
    ]);
    expect(await AppSessionStorage.readMobileSyncToken(), 'final-token');
  });

  test(
    'flushOutbox uploads voice media and sends it as a chat message',
    () async {
      const ownerUserId = 701;
      const conversationId = 42;
      await AppSessionStorage.saveSession(
        const AuthenticatedSession(
          token: 'token-voice',
          user: AppUser(
            id: ownerUserId,
            firstName: 'Voice',
            surname: 'Tester',
            username: 'voice_tester',
          ),
        ),
      );

      final directory = await Directory.systemTemp.createTemp('magmug_voice_');
      addTearDown(() => directory.delete(recursive: true));
      final file = File('${directory.path}/voice.m4a');
      await file.writeAsBytes(<int>[1, 2, 3, 4]);

      final outboxStore = _FakeOutboxStore([
        _voiceOutboxItem(
          ownerUserId: ownerUserId,
          conversationId: conversationId,
          filePath: file.path,
          clientMessageId: 'voice-message-1',
          clientUploadId: 'voice-upload-1',
          duration: const Duration(seconds: 3),
        ),
      ]);

      final sentBodies = <Map<String, dynamic>>[];
      final engine = AppSyncEngine.testing(
        outboxStoreFactory: () => outboxStore,
        apiFactory: () => AppAuthApi(
          client: MockClient((request) async {
            if (request.url.path.endsWith('/api/mobile/uploads')) {
              return http.Response(
                jsonEncode({
                  'client_upload_id': 'voice-upload-1',
                  'dosya_yolu': 'mesajlar/$ownerUserId/ses/voice.m4a',
                  'mime_tipi': 'audio/mp4',
                  'boyut': 4,
                }),
                201,
                headers: {'content-type': 'application/json'},
              );
            }

            expect(
              request.url.path,
              endsWith('/api/mobile/conversations/$conversationId/messages'),
            );
            final body = jsonDecode(request.body) as Map<String, dynamic>;
            sentBodies.add(body);
            return http.Response(
              jsonEncode({
                'data': {
                  'id': 9001,
                  'sohbet_id': conversationId,
                  'gonderen': {'id': ownerUserId, 'ad': 'Voice Tester'},
                  'mesaj_tipi': 'ses',
                  'dosya_yolu': body['dosya_yolu'],
                  'dosya_suresi': body['dosya_suresi'],
                  'okundu_mu': false,
                  'created_at': '2026-04-26T12:00:00Z',
                  'client_message_id': body['client_message_id'],
                },
              }),
              201,
              headers: {'content-type': 'application/json'},
            );
          }),
        ),
      );

      await engine.flushOutbox(token: 'token-voice', ownerUserId: ownerUserId);

      expect(sentBodies, hasLength(1));
      expect(sentBodies.single['mesaj_tipi'], 'ses');
      expect(
        sentBodies.single['dosya_yolu'],
        'mesajlar/$ownerUserId/ses/voice.m4a',
      );
      expect(sentBodies.single['dosya_suresi'], 3);

      expect(outboxStore.sentMessages.single.id, 9001);
      expect(outboxStore.sentMessages.single.type, 'ses');
      expect(outboxStore.pendingClientMessageIds, isEmpty);
    },
  );

  test(
    'flushOutbox drains media queued while another flush is running',
    () async {
      const ownerUserId = 702;
      const conversationId = 43;
      await AppSessionStorage.saveSession(
        const AuthenticatedSession(
          token: 'token-drain',
          user: AppUser(
            id: ownerUserId,
            firstName: 'Drain',
            surname: 'Tester',
            username: 'drain_tester',
          ),
        ),
      );

      final directory = await Directory.systemTemp.createTemp('magmug_voice_');
      addTearDown(() => directory.delete(recursive: true));
      final file = File('${directory.path}/voice.m4a');
      await file.writeAsBytes(<int>[1, 2, 3, 4]);

      final outboxStore = _FakeOutboxStore([]);

      void enqueueVoice(String id) {
        outboxStore.add(
          _voiceOutboxItem(
            ownerUserId: ownerUserId,
            conversationId: conversationId,
            filePath: file.path,
            clientMessageId: 'voice-message-$id',
            clientUploadId: 'voice-upload-$id',
            duration: const Duration(seconds: 2),
          ),
        );
      }

      enqueueVoice('1');

      final firstUploadStarted = Completer<void>();
      final releaseFirstUpload = Completer<void>();
      var uploadCount = 0;
      final sentBodies = <Map<String, dynamic>>[];
      final engine = AppSyncEngine.testing(
        outboxStoreFactory: () => outboxStore,
        apiFactory: () => AppAuthApi(
          client: MockClient((request) async {
            if (request.url.path.endsWith('/api/mobile/uploads')) {
              uploadCount++;
              if (uploadCount == 1) {
                firstUploadStarted.complete();
                await releaseFirstUpload.future;
              }
              return http.Response(
                jsonEncode({
                  'client_upload_id': 'voice-upload-$uploadCount',
                  'dosya_yolu':
                      'mesajlar/$ownerUserId/ses/voice_$uploadCount.m4a',
                  'mime_tipi': 'audio/mp4',
                  'boyut': 4,
                }),
                201,
                headers: {'content-type': 'application/json'},
              );
            }

            final body = jsonDecode(request.body) as Map<String, dynamic>;
            sentBodies.add(body);
            return http.Response(
              jsonEncode({
                'data': {
                  'id': 9100 + sentBodies.length,
                  'sohbet_id': conversationId,
                  'gonderen': {'id': ownerUserId, 'ad': 'Drain Tester'},
                  'mesaj_tipi': 'ses',
                  'dosya_yolu': body['dosya_yolu'],
                  'dosya_suresi': body['dosya_suresi'],
                  'okundu_mu': false,
                  'created_at': '2026-04-26T12:00:00Z',
                  'client_message_id': body['client_message_id'],
                },
              }),
              201,
              headers: {'content-type': 'application/json'},
            );
          }),
        ),
      );

      final firstFlush = engine.flushOutbox(
        token: 'token-drain',
        ownerUserId: ownerUserId,
      );
      await firstUploadStarted.future;
      enqueueVoice('2');
      final secondFlush = engine.flushOutbox(
        token: 'token-drain',
        ownerUserId: ownerUserId,
      );
      releaseFirstUpload.complete();

      await Future.wait([firstFlush, secondFlush]);

      expect(sentBodies, hasLength(2));
      expect(sentBodies.map((body) => body['mesaj_tipi']), everyElement('ses'));
    },
  );

  test('realtime refresh deduper suppresses duplicate event refreshes', () {
    final event = ChatRealtimeEvent(
      type: ChatRealtimeEventType.messageSent,
      conversationId: 10,
      payload: const {'id': 99, 'gonderen_user_id': 7},
    );

    expect(
      ChatRealtimeEventRefreshDeduper.instance.shouldRefresh(event),
      isTrue,
    );
    expect(
      ChatRealtimeEventRefreshDeduper.instance.shouldRefresh(event),
      isFalse,
    );
  });

  test('realtime event bus publishes global user-channel events', () {
    final container = ProviderContainer();
    addTearDown(container.dispose);

    final event = ChatRealtimeEvent(
      type: ChatRealtimeEventType.conversationTyping,
      conversationId: 42,
      payload: const {'user_id': 9, 'typing': true},
    );

    container.read(chatRealtimeEventBusProvider.notifier).state =
        ChatRealtimeEventSignal(sequence: 1, event: event);

    final signal = container.read(chatRealtimeEventBusProvider);
    expect(signal?.sequence, 1);
    expect(signal?.event.conversationId, 42);
    expect(signal?.event.type, ChatRealtimeEventType.conversationTyping);
  });

  test('realtime auth gate coalesces parallel channel subscribes', () async {
    final gate = ChatRealtimeAuthGate(cooldown: const Duration(seconds: 1));
    final completer = Completer<void>();
    var attempts = 0;

    final first = gate.run('private-kullanici.7', () {
      attempts++;
      return completer.future;
    });
    final second = gate.run('private-kullanici.7', () {
      attempts++;
      return Future<void>.value();
    });

    await Future<void>.delayed(Duration.zero);
    expect(attempts, 1);

    completer.complete();
    await Future.wait([first, second]);
  });

  test('realtime auth gate cools down after 429 auth failure', () async {
    final gate = ChatRealtimeAuthGate(cooldown: const Duration(seconds: 1));
    var attempts = 0;

    await expectLater(
      gate.run('private-kullanici.7', () {
        attempts++;
        throw const ApiException('Authentication failed with status 429');
      }),
      throwsA(isA<ApiException>()),
    );

    expect(gate.isCoolingDown('private-kullanici.7'), isTrue);

    await expectLater(
      gate.run('private-kullanici.7', () {
        attempts++;
        return Future<void>.value();
      }),
      throwsA(isA<ApiException>()),
    );
    expect(attempts, 1);
  });

  test('runtime cache registry clears registered user-scoped caches', () {
    var cleared = false;
    AppRuntimeCacheRegistry.register(() {
      cleared = true;
    });

    AppRuntimeCacheRegistry.clearUserScopedCaches();

    expect(cleared, isTrue);
  });
}

class _FakeOutboxStore implements ChatOutboxStore {
  final List<ChatOutboxItem> _items;
  final List<AppConversationMessage> sentMessages = [];
  final List<String> failedClientMessageIds = [];

  _FakeOutboxStore(List<ChatOutboxItem> items) : _items = List.of(items);

  List<String> get pendingClientMessageIds =>
      _items.map((item) => item.clientMessageId).toList(growable: false);

  void add(ChatOutboxItem item) => _items.add(item);

  @override
  Future<List<ChatOutboxItem>> getPendingOutboxItems({
    required int ownerUserId,
    int limit = 20,
  }) async {
    return _items
        .where((item) => item.ownerUserId == ownerUserId)
        .take(limit)
        .toList(growable: false);
  }

  @override
  Future<void> markOutboxSending({
    required int ownerUserId,
    required String clientMessageId,
  }) async {}

  @override
  Future<void> markOutboxSent({
    required int ownerUserId,
    required String clientMessageId,
    required AppConversationMessage sentMessage,
  }) async {
    _items.removeWhere(
      (item) =>
          item.ownerUserId == ownerUserId &&
          item.clientMessageId == clientMessageId,
    );
    sentMessages.add(sentMessage);
  }

  @override
  Future<void> markOutboxFailed({
    required int ownerUserId,
    required String clientMessageId,
    required String errorMessage,
  }) async {
    failedClientMessageIds.add(clientMessageId);
  }

  @override
  Future<void> updateOutboxRemoteFilePath({
    required int ownerUserId,
    required String clientMessageId,
    required String remoteFilePath,
  }) async {
    final index = _items.indexWhere(
      (item) =>
          item.ownerUserId == ownerUserId &&
          item.clientMessageId == clientMessageId,
    );
    if (index < 0) {
      return;
    }

    final item = _items[index];
    _items[index] = ChatOutboxItem(
      localId: item.localId,
      ownerUserId: item.ownerUserId,
      conversationId: item.conversationId,
      clientMessageId: item.clientMessageId,
      clientUploadId: item.clientUploadId,
      type: item.type,
      text: item.text,
      localFilePath: item.localFilePath,
      remoteFilePath: remoteFilePath,
      fileDuration: item.fileDuration,
    );
  }
}

ChatOutboxItem _voiceOutboxItem({
  required int ownerUserId,
  required int conversationId,
  required String filePath,
  required String clientMessageId,
  required String clientUploadId,
  required Duration duration,
}) {
  return ChatOutboxItem(
    localId: clientMessageId,
    ownerUserId: ownerUserId,
    conversationId: conversationId,
    clientMessageId: clientMessageId,
    clientUploadId: clientUploadId,
    type: 'ses',
    localFilePath: filePath,
    fileDuration: duration,
  );
}
