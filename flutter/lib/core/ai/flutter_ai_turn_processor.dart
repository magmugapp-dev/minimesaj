import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:magmug/core/models/communication_models.dart';
import 'package:magmug/core/network/app_api.dart';
import 'package:magmug/core/network/app_auth_api.dart';
import 'package:magmug/core/storage/app_storage.dart';
import 'package:magmug/features/chat/chat_local_store.dart';

class FlutterAiLocalStatusEvent {
  final int conversationId;
  final String? status;
  final String? statusText;

  const FlutterAiLocalStatusEvent({
    required this.conversationId,
    required this.status,
    this.statusText,
  });
}

class FlutterAiTurnProcessor {
  FlutterAiTurnProcessor._();

  static final FlutterAiTurnProcessor instance = FlutterAiTurnProcessor._();
  static const Duration _pendingFetchCooldown = Duration(seconds: 12);
  static const Duration _bootstrapCooldown = Duration(minutes: 5);
  static final StreamController<FlutterAiLocalStatusEvent> _statusController =
      StreamController<FlutterAiLocalStatusEvent>.broadcast();

  bool _running = false;
  bool _pauseProcessing = false;
  Timer? _deferredTimer;
  String? _pendingToken;
  int? _pendingUserId;
  bool _pendingForceFetch = false;
  int _pendingLookaheadSeconds = 0;
  DateTime? _pendingFetchAllowedAt;
  DateTime? _bootstrapAllowedAt;

  Stream<FlutterAiLocalStatusEvent> get statusEvents =>
      _statusController.stream;

  @visibleForTesting
  Future<Map<String, dynamic>> buildGeminiPayloadForTest(
    http.Client client,
    Map<String, dynamic> context, {
    String? token,
  }) {
    return _geminiPayload(client, context, token: token);
  }

  @visibleForTesting
  Duration typingHoldDurationForTest(List<String> parts) {
    return _typingHoldDuration(parts);
  }

  void cancel() {
    _deferredTimer?.cancel();
    _deferredTimer = null;
    _pendingToken = null;
    _pendingUserId = null;
    _pendingForceFetch = false;
    _pendingLookaheadSeconds = 0;
  }

  void onAppPaused() {
    _pauseProcessing = true;
    _deferredTimer?.cancel();
    _deferredTimer = null;
  }

  void onAppResumed({
    required String token,
    required int ownerUserId,
  }) {
    _pauseProcessing = false;
    unawaited(run(token: token, ownerUserId: ownerUserId, forceFetch: true));
  }

  Future<void> run({
    required String token,
    required int ownerUserId,
    bool forceFetch = false,
    int lookaheadSeconds = 0,
  }) async {
    if (token.trim().isEmpty || ownerUserId <= 0) return;
    if (_pauseProcessing) return;
    if (_running) {
      _pendingToken = token;
      _pendingUserId = ownerUserId;
      _pendingForceFetch = _pendingForceFetch || forceFetch;
      _pendingLookaheadSeconds = math.max(
        _pendingLookaheadSeconds,
        lookaheadSeconds,
      );
      return;
    }

    _running = true;
    _pendingToken = null;
    _pendingUserId = null;
    _pendingForceFetch = false;
    _pendingLookaheadSeconds = 0;
    final client = AppHttpClientFactory.createForApi();
    try {
      await _syncBootstrap(client, token, force: forceFetch);
      await _processDueLocalTurns(
        client,
        token: token,
        ownerUserId: ownerUserId,
      );
      if (forceFetch || _canFetchPendingTurns()) {
        final turns = await _fetchPendingTurns(
          client,
          token,
          lookaheadSeconds: forceFetch ? lookaheadSeconds : 0,
        );
        _pendingFetchAllowedAt = DateTime.now().add(_pendingFetchCooldown);
        for (final turn in turns) {
          if ((turn['ai_user_id'] as num?)?.toInt() == ownerUserId) {
            continue;
          }
          await _storePendingTurn(turn, ownerUserId: ownerUserId);
        }
        await _processDueLocalTurns(
          client,
          token: token,
          ownerUserId: ownerUserId,
        );
      }
      await _scheduleNextLocalDeferred(token: token, ownerUserId: ownerUserId);
    } catch (_) {
      // AI background work is intentionally silent.
    } finally {
      _running = false;
      client.close();
      final pToken = _pendingToken;
      final pUserId = _pendingUserId;
      final pForceFetch = _pendingForceFetch;
      final pLookaheadSeconds = _pendingLookaheadSeconds;
      if (pToken != null && pUserId != null) {
        _pendingToken = null;
        _pendingUserId = null;
        _pendingForceFetch = false;
        _pendingLookaheadSeconds = 0;
        unawaited(
          run(
            token: pToken,
            ownerUserId: pUserId,
            forceFetch: pForceFetch,
            lookaheadSeconds: pLookaheadSeconds,
          ),
        );
      }
    }
  }

  Future<void> cacheRealtimeTurn({
    required Map<String, dynamic> payload,
    required int conversationId,
    required String token,
    required int ownerUserId,
  }) async {
    final turnId =
        (payload['turn_id'] as num?)?.toInt() ??
        (payload['id'] as num?)?.toInt();
    if (turnId == null || turnId <= 0) {
      return;
    }

    await _storePendingTurn({
      'id': turnId,
      'conversation_id': conversationId,
      'source_message_id': (payload['source_message_id'] as num?)?.toInt(),
      'ai_user_id': (payload['ai_user_id'] as num?)?.toInt(),
      'status': payload['status']?.toString(),
      'planned_at': payload['planned_at']?.toString(),
      'retry_after': payload['retry_after']?.toString(),
      'attempt_count': (payload['attempt_count'] as num?)?.toInt() ?? 0,
      'max_attempts': (payload['max_attempts'] as num?)?.toInt() ?? 5,
    }, ownerUserId: ownerUserId);
    await _scheduleNextLocalDeferred(token: token, ownerUserId: ownerUserId);
  }

  bool _canFetchPendingTurns() {
    final allowedAt = _pendingFetchAllowedAt;
    return allowedAt == null || !DateTime.now().isBefore(allowedAt);
  }

  Future<void> _syncBootstrap(
    http.Client client,
    String token, {
    bool force = false,
  }) async {
    final allowedAt = _bootstrapAllowedAt;
    if (!force && allowedAt != null && DateTime.now().isBefore(allowedAt)) {
      return;
    }

    final response = await client.get(
      AppApi.uri(AppApi.mobileAiBootstrapPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );
    _bootstrapAllowedAt = DateTime.now().add(_bootstrapCooldown);
    if (response.statusCode >= 400 || response.bodyBytes.isEmpty) {
      return;
    }

    final payload = _decodeMap(response.bodyBytes);
    final prompt = _asMap(payload['prompt']);
    if (prompt != null) {
      await (await AppHiveBoxes.aiPrompt()).put('active', prompt);
    }
  }

  Future<List<Map<String, dynamic>>> _fetchPendingTurns(
    http.Client client,
    String token, {
    int lookaheadSeconds = 0,
  }) async {
    final uri = AppApi.uri(AppApi.mobileAiPendingTurnsPath).replace(
      queryParameters: lookaheadSeconds > 0
          ? {'lookahead_seconds': '$lookaheadSeconds'}
          : null,
    );
    final response = await client.get(
      uri,
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );
    if (response.statusCode >= 400 || response.bodyBytes.isEmpty) {
      return const [];
    }

    final payload = _decodeMap(response.bodyBytes);
    return _mapsFromValue(payload['data']);
  }

  Future<void> _processDueLocalTurns(
    http.Client client, {
    required String token,
    required int ownerUserId,
  }) async {
    final box = await AppHiveBoxes.pendingAiTurns();
    final rows =
        box.values
            .map(_asMap)
            .whereType<Map<String, dynamic>>()
            .where(
              (row) => (row['owner_user_id'] as num?)?.toInt() == ownerUserId,
            )
            .where(_isTurnDue)
            .toList(growable: false)
          ..sort((a, b) {
            final aAt =
                _dueAtForTurn(a) ?? DateTime.fromMillisecondsSinceEpoch(0);
            final bAt =
                _dueAtForTurn(b) ?? DateTime.fromMillisecondsSinceEpoch(0);
            return aAt.compareTo(bAt);
          });

    for (final turn in rows) {
      if (_pauseProcessing) {
        return;
      }
      if ((turn['ai_user_id'] as num?)?.toInt() == ownerUserId) {
        continue;
      }
      await _processTurn(
        client,
        token: token,
        ownerUserId: ownerUserId,
        turn: turn,
      );
    }
  }

  Future<void> _processTurn(
    http.Client client, {
    required String token,
    required int ownerUserId,
    required Map<String, dynamic> turn,
  }) async {
    final turnId = (turn['id'] as num?)?.toInt();
    final conversationId = (turn['conversation_id'] as num?)?.toInt();
    if (turnId == null || conversationId == null) {
      return;
    }
    if (_pauseProcessing) {
      return;
    }

    final context = await _fetchTurnContext(
      client,
      token: token,
      conversationId: conversationId,
      turnId: turnId,
    );
    if (context == null) {
      return;
    }

    final aiUserId =
        (_asMap(context['turn'])?['ai_user_id'] as num?)?.toInt() ??
        (context['ai_user_id'] as num?)?.toInt();
    if (aiUserId == null) {
      return;
    }

    _emitLocalStatus(conversationId, 'typing', 'Yaziyor...');
    final typingStartedAt = DateTime.now();
    try {
      final text = await _generateWithSilentRetries(
        client,
        token: token,
        turnId: turnId,
        context: context,
      );
      if (text == null || text.trim().isEmpty) {
        await _reportTurnFailure(
          client,
          token: token,
          turnId: turnId,
          error: 'empty_generated_text',
        );
        return;
      }

      final cleaned = await _handleBlockTags(
        client,
        token: token,
        aiUserId: aiUserId,
        text: text,
      ).then(_stripConversationEndingTags);
      final parts = cleaned
          .split(RegExp(r'\n\s*\n+'))
          .map((part) => part.trim())
          .where((part) => part.isNotEmpty)
          .toList(growable: false);
      if (parts.isEmpty) {
        await _reportTurnFailure(
          client,
          token: token,
          turnId: turnId,
          error: 'empty_sanitized_text',
        );
        return;
      }

      await _holdTypingBeforeReply(parts, startedAt: typingStartedAt);

      final messages = await _persistReply(
        client,
        token: token,
        conversationId: conversationId,
        turnId: turnId,
        parts: parts,
      );
      if (messages.isNotEmpty) {
        await ChatLocalStore.instance.upsertConversationMessages(
          messages,
          ownerUserId: ownerUserId,
        );
        await ChatLocalStore.instance.updateConversationPreviewRuntimeStatus(
          conversationId,
          ownerUserId: ownerUserId,
          aiStatus: null,
          aiStatusText: null,
          aiPlannedAt: null,
        );
        await _removePendingTurn(ownerUserId: ownerUserId, turnId: turnId);
      } else {
        await _reportTurnFailure(
          client,
          token: token,
          turnId: turnId,
          error: 'empty_persisted_reply',
        );
      }
    } finally {
      _emitLocalStatus(conversationId, null, null);
    }
  }

  void _emitLocalStatus(
    int conversationId,
    String? status,
    String? statusText,
  ) {
    _statusController.add(
      FlutterAiLocalStatusEvent(
        conversationId: conversationId,
        status: status,
        statusText: statusText,
      ),
    );
  }

  Future<void> _holdTypingBeforeReply(
    List<String> parts, {
    required DateTime startedAt,
  }) async {
    final target = _typingHoldDuration(parts);
    final elapsed = DateTime.now().difference(startedAt);
    final remaining = target - elapsed;
    if (remaining <= Duration.zero) {
      return;
    }

    await Future<void>.delayed(remaining);
  }

  Future<void> _reportTurnFailure(
    http.Client client, {
    required String token,
    required int turnId,
    required String error,
  }) async {
    try {
      await client
          .post(
            AppApi.uri(AppApi.mobileAiTurnFailurePath),
            headers: {
              'Accept': 'application/json',
              'Authorization': 'Bearer $token',
              'Content-Type': 'application/json',
            },
            body: jsonEncode({'turn_id': turnId, 'error': error}),
          )
          .timeout(const Duration(seconds: 8));
    } catch (_) {
      // Failure reporting is best effort; local status still clears below.
    }
  }

  Duration _typingHoldDuration(List<String> parts) {
    final charCount = parts.fold<int>(
      0,
      (sum, part) => sum + part.trim().runes.length,
    );
    final milliseconds = 2000 + (charCount * 150) + (parts.length * 500);
    return Duration(milliseconds: milliseconds.clamp(2000, 8000).toInt());
  }

  Future<Map<String, dynamic>?> _fetchTurnContext(
    http.Client client, {
    required String token,
    required int conversationId,
    required int turnId,
  }) async {
    final uri = AppApi.uri(
      AppApi.mobileAiTurnContextPath(conversationId),
    ).replace(queryParameters: {'turn_id': '$turnId'});
    final response = await client.get(
      uri,
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );
    if (response.statusCode >= 400 || response.bodyBytes.isEmpty) {
      return null;
    }

    final context = _decodeMap(response.bodyBytes);
    final prompt = _asMap(context['global_prompt']);
    if (prompt != null) {
      final newHash = prompt['hash']?.toString();
      final promptBox = await AppHiveBoxes.aiPrompt();
      final cachedHash = _asMap(
        await promptBox.get('active'),
      )?['hash']?.toString();
      if (newHash == null || newHash != cachedHash) {
        await promptBox.put('active', prompt);
      }
    }
    final character = _asMap(context['character']);
    final characterId = character?['character_id']?.toString();
    if (characterId != null && characterId.trim().isNotEmpty) {
      final newVersion = character?['character_version'];
      final charBox = await AppHiveBoxes.aiCharacters();
      final cached = _asMap(await charBox.get(characterId));
      final cachedVersion = cached?['character_version'];
      if (newVersion == null || newVersion != cachedVersion) {
        await charBox.put(characterId, character);
      }
    }
    await (await AppHiveBoxes.aiMemory())
        .put('conversation:${context['conversation_id']}', {
          'messages': context['messages'],
          'updated_at_ms': DateTime.now().millisecondsSinceEpoch,
        });

    return context;
  }

  Future<String?> _generateWithSilentRetries(
    http.Client client, {
    required String token,
    required int turnId,
    required Map<String, dynamic> context,
  }) async {
    Object? lastError;
    for (var attempt = 0; attempt < 5; attempt++) {
      if (attempt > 0) {
        final delay =
            math.min(25, 1 << attempt) + math.Random().nextInt(750) / 1000.0;
        await Future<void>.delayed(
          Duration(milliseconds: (delay * 1000).round()),
        );
      }

      try {
        final generated = await _callRelay(
          client,
          token: token,
          turnId: turnId,
          context: context,
        );
        if (generated != null && generated.trim().isNotEmpty) {
          return generated;
        }
      } on _PermanentGeminiException catch (error) {
        await _deferLocalTurn(context, error: error.toString());
        return null;
      } on _RetryableGeminiException catch (error) {
        lastError = error;
      } on SocketException catch (error) {
        lastError = error;
      } on TimeoutException catch (error) {
        lastError = error;
      }
    }

    await _deferLocalTurn(context, error: lastError?.toString() ?? 'retryable');
    return null;
  }

  Future<String?> _callRelay(
    http.Client client, {
    required String token,
    required int turnId,
    required Map<String, dynamic> context,
  }) async {
    final modelConfig = _asMap(context['model_config']) ?? const {};
    final request =
        http.Request('POST', AppApi.uri(AppApi.mobileAiGeminiStreamPath))
          ..headers.addAll({
            'Accept': 'text/event-stream',
            'Authorization': 'Bearer $token',
            'Content-Type': 'application/json',
          })
          ..body = jsonEncode({
            'turn_id': turnId,
            'model':
                modelConfig['model_name']?.toString() ?? 'gemini-2.5-flash',
            'payload': await _geminiPayload(client, context, token: token),
          });

    final streamed = await client
        .send(request)
        .timeout(const Duration(seconds: 55));
    if (streamed.statusCode == 429 || streamed.statusCode == 503) {
      throw _RetryableGeminiException(streamed.statusCode);
    }
    if (streamed.statusCode >= 500) {
      throw _RetryableGeminiException(streamed.statusCode);
    }
    if (streamed.statusCode >= 400) {
      // 4xx kalıcı hata — retry etme, direkt fail
      throw _PermanentGeminiException(streamed.statusCode);
    }

    return _readGeminiSse(streamed.stream);
  }

  Future<Map<String, dynamic>> _geminiPayload(
    http.Client client,
    Map<String, dynamic> context, {
    String? token,
  }) async {
    final modelConfig = _asMap(context['model_config']) ?? const {};
    final prompt = _asMap(context['global_prompt']);
    final character = _asMap(context['character']) ?? const {};
    final runtimeContext = _asMap(context['runtime_context']) ?? const {};
    final messages = _mapsFromValue(context['messages']);

    return {
      'systemInstruction': {
        'parts': [
          {
            'text': [
              prompt?['prompt_xml']?.toString() ?? '',
              'Character JSON:',
              jsonEncode(character),
              'Runtime context:',
              jsonEncode(runtimeContext),
            ].where((part) => part.trim().isNotEmpty).join('\n\n'),
          },
        ],
      },
      'contents': [
        for (final message in messages)
          {
            'role': message['is_ai'] == true ? 'model' : 'user',
            'parts': await _geminiPartsForMessage(
              client,
              message,
              token: token,
            ),
          },
      ],
      'generationConfig': {
        'temperature': (modelConfig['temperature'] as num?)?.toDouble() ?? 0.8,
        'topP': (modelConfig['top_p'] as num?)?.toDouble() ?? 0.9,
        'maxOutputTokens':
            (modelConfig['max_output_tokens'] as num?)?.toInt() ?? 1024,
      },
    };
  }

  Future<List<Map<String, dynamic>>> _geminiPartsForMessage(
    http.Client client,
    Map<String, dynamic> message, {
    String? token,
  }) async {
    final isAi = message['is_ai'] == true;
    final text = message['text']?.toString().trim();
    final fileUrl = message['file_url']?.toString().trim();
    final parts = <Map<String, dynamic>>[];
    final textParts = <String>[if (text != null && text.isNotEmpty) text];

    if (!isAi && fileUrl != null && fileUrl.isNotEmpty) {
      if (_isImageMessage(message)) {
        final inline = await _inlineImagePart(
          client,
          message,
          fileUrl,
          token: token,
        );
        if (inline != null) {
          parts.add(inline);
        } else {
          textParts.add('[Fotoğraf mesajı]');
        }
      } else if (_isAudioMessage(message)) {
        final inline = await _inlineAudioPart(
          client,
          message,
          fileUrl,
          token: token,
        );
        if (inline != null) {
          parts.add(inline);
        }
        final dur = message['file_duration'];
        textParts.add(
          dur != null ? '[Sesli mesaj, süre: ${dur}s]' : '[Sesli mesaj]',
        );
      } else if (fileUrl.isNotEmpty) {
        textParts.add('[Medya: ${message['type']}]');
      }
    } else if (isAi && fileUrl != null && fileUrl.isNotEmpty) {
      // AI kendi gönderdiği medyalar için sadece referans
      textParts.add('[Medya: ${message['type']}]');
    }

    if (textParts.isNotEmpty) {
      parts.insert(0, {'text': textParts.join('\n')});
    }

    return parts.isEmpty
        ? [
            {'text': ''},
          ]
        : parts;
  }

  bool _isImageMessage(Map<String, dynamic> message) {
    final type = message['type']?.toString().trim().toLowerCase();
    final mime = message['file_mime']?.toString().trim().toLowerCase();
    return type == 'foto' ||
        type == 'gorsel' ||
        (mime != null && mime.startsWith('image/'));
  }

  bool _isAudioMessage(Map<String, dynamic> message) {
    final type = message['type']?.toString().trim().toLowerCase();
    final mime = message['file_mime']?.toString().trim().toLowerCase();
    return type == 'ses' || (mime != null && mime.startsWith('audio/'));
  }

  Future<Map<String, dynamic>?> _inlineImagePart(
    http.Client client,
    Map<String, dynamic> message,
    String fileUrl, {
    String? token,
  }) async {
    const maxInlineBytes = 5 * 1024 * 1024;
    try {
      final mime = _imageMimeFor(message, fileUrl);
      final bytes = await _readMediaBytes(client, fileUrl, token: token);
      if (bytes == null || bytes.isEmpty || bytes.length > maxInlineBytes) {
        return null;
      }

      return {
        'inlineData': {'mimeType': mime, 'data': base64Encode(bytes)},
      };
    } catch (_) {
      return null;
    }
  }

  Future<Map<String, dynamic>?> _inlineAudioPart(
    http.Client client,
    Map<String, dynamic> message,
    String fileUrl, {
    String? token,
  }) async {
    const maxInlineBytes = 5 * 1024 * 1024;
    try {
      final mime = _audioMimeFor(message, fileUrl);
      final bytes = await _readMediaBytes(client, fileUrl, token: token);
      if (bytes == null || bytes.isEmpty || bytes.length > maxInlineBytes) {
        return null;
      }
      return {
        'inlineData': {'mimeType': mime, 'data': base64Encode(bytes)},
      };
    } catch (_) {
      return null;
    }
  }

  String _audioMimeFor(Map<String, dynamic> message, String source) {
    final mime = message['file_mime']?.toString().trim().toLowerCase();
    if (mime != null && mime.startsWith('audio/')) return mime;
    final path = Uri.tryParse(source)?.path ?? source;
    final ext = path.split('.').last.toLowerCase();
    return switch (ext) {
      'mp3' => 'audio/mpeg',
      'ogg' => 'audio/ogg',
      'wav' => 'audio/wav',
      'webm' => 'audio/webm',
      _ => 'audio/mp4',
    };
  }

  Future<List<int>?> _readMediaBytes(
    http.Client client,
    String source, {
    String? token,
  }) async {
    final uri = Uri.tryParse(source);
    final scheme = uri?.scheme.toLowerCase();
    if (scheme == 'http' || scheme == 'https') {
      final response = await client
          .get(uri!, headers: _mediaRequestHeaders(uri, token))
          .timeout(const Duration(seconds: 10));
      if (response.statusCode < 200 || response.statusCode >= 300) {
        return null;
      }
      return response.bodyBytes;
    }
    if (scheme == 'file') {
      final file = File(uri!.toFilePath());
      if (!await file.exists()) {
        return null;
      }
      return file.readAsBytes();
    }
    final file = File(source);
    if (!await file.exists()) {
      return null;
    }
    return file.readAsBytes();
  }

  Map<String, String>? _mediaRequestHeaders(Uri uri, String? token) {
    if (token == null || token.trim().isEmpty) {
      return null;
    }

    final appUri = AppApi.uri('/');
    if (uri.host != appUri.host || uri.port != appUri.port) {
      return null;
    }

    return {'Authorization': 'Bearer $token', 'Accept': '*/*'};
  }

  String _imageMimeFor(Map<String, dynamic> message, String source) {
    final mime = message['file_mime']?.toString().trim().toLowerCase();
    if (mime != null && mime.startsWith('image/')) {
      return mime;
    }
    final path = Uri.tryParse(source)?.path ?? source;
    final extension = path.split('.').last.toLowerCase();
    return switch (extension) {
      'png' => 'image/png',
      'webp' => 'image/webp',
      'heic' => 'image/heic',
      'heif' => 'image/heif',
      _ => 'image/jpeg',
    };
  }

  Future<String> _readGeminiSse(Stream<List<int>> stream) async {
    final buffer = StringBuffer();
    var pending = '';
    await for (final chunk in stream.transform(utf8.decoder)) {
      pending += chunk;
      final lines = pending.split('\n');
      pending = lines.removeLast();
      for (final line in lines) {
        final trimmed = line.trim();
        if (!trimmed.startsWith('data:')) {
          continue;
        }
        final data = trimmed.substring(5).trim();
        if (data.isEmpty || data == '[DONE]') {
          continue;
        }
        final payload = jsonDecode(data);
        final candidates = payload is Map ? payload['candidates'] : null;
        if (candidates is! List || candidates.isEmpty) {
          continue;
        }
        final content = _asMap(_asMap(candidates.first)?['content']);
        final parts = content?['parts'];
        if (parts is! List) {
          continue;
        }
        for (final part in parts.whereType<Map>()) {
          final text = part['text']?.toString();
          if (text != null) {
            buffer.write(text);
          }
        }
      }
    }

    return buffer.toString();
  }

  Future<String> _handleBlockTags(
    http.Client client, {
    required String token,
    required int aiUserId,
    required String text,
  }) async {
    var cleaned = text;
    final matches = RegExp(r'\[BLOCK_USER:([^\]]+)\]').allMatches(text);
    for (final match in matches) {
      final category = match.group(1)?.trim();
      if (category == null || category.isEmpty) {
        continue;
      }
      unawaited(
        client.post(
          AppApi.uri(AppApi.mobileAiViolationsPath),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $token',
            'Content-Type': 'application/json',
          },
          body: jsonEncode({'ai_user_id': aiUserId, 'category': category}),
        ),
      );
    }

    cleaned = cleaned.replaceAll(RegExp(r'\[BLOCK_USER:[^\]]+\]'), '');
    return cleaned.trim();
  }

  String _stripConversationEndingTags(String text) {
    return text
        .replaceAll(
          RegExp(
            r'\[CONV_END:(sleep|work|break|general)\]',
            caseSensitive: false,
          ),
          '',
        )
        .trim();
  }

  Future<List<AppConversationMessage>> _persistReply(
    http.Client client, {
    required String token,
    required int conversationId,
    required int turnId,
    required List<String> parts,
  }) async {
    final response = await client.post(
      AppApi.uri(AppApi.mobileAiReplyPath(conversationId)),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'turn_id': turnId,
        'parts': parts,
        'client_message_id': 'ai-turn-$turnId',
      }),
    );
    if (response.statusCode >= 400 || response.bodyBytes.isEmpty) {
      return const [];
    }

    final payload = _decodeMap(response.bodyBytes);
    return _mapsFromValue(payload['data'])
        .map(AppAuthApi.conversationMessageFromJson)
        .whereType<AppConversationMessage>()
        .toList(growable: false);
  }

  Future<void> _storePendingTurn(
    Map<String, dynamic> turn, {
    required int ownerUserId,
  }) async {
    final turnId = (turn['id'] as num?)?.toInt();
    if (turnId == null) {
      return;
    }
    await (await AppHiveBoxes.pendingAiTurns()).put('$ownerUserId:$turnId', {
      ...turn,
      'owner_user_id': ownerUserId,
      'updated_at_ms': DateTime.now().millisecondsSinceEpoch,
    });
  }

  Future<void> _removePendingTurn({
    required int ownerUserId,
    required int turnId,
  }) async {
    await (await AppHiveBoxes.pendingAiTurns()).delete('$ownerUserId:$turnId');
  }

  Future<void> _deferLocalTurn(
    Map<String, dynamic> context, {
    required String error,
  }) async {
    final turn = _asMap(context['turn']);
    final turnId = (turn?['id'] as num?)?.toInt();
    if (turnId == null) {
      return;
    }
    final ownerUserId = await AppSessionStorage.readOwnerUserId();
    if (ownerUserId == null) {
      return;
    }
    final retryAfter = DateTime.now().add(const Duration(minutes: 5));
    await (await AppHiveBoxes.pendingAiTurns()).put('$ownerUserId:$turnId', {
      ...?turn,
      'owner_user_id': ownerUserId,
      'status': 'deferred',
      'retry_after': retryAfter.toIso8601String(),
      'last_error': error,
      'updated_at_ms': DateTime.now().millisecondsSinceEpoch,
    });
  }

  Future<void> _scheduleNextLocalDeferred({
    required String token,
    required int ownerUserId,
  }) async {
    _deferredTimer?.cancel();
    final box = await AppHiveBoxes.pendingAiTurns();
    DateTime? nextRetry;
    for (final row
        in box.values.map(_asMap).whereType<Map<String, dynamic>>()) {
      if ((row['owner_user_id'] as num?)?.toInt() != ownerUserId) {
        continue;
      }
      final candidate = _dueAtForTurn(row);
      if (candidate == null) continue;
      if (nextRetry == null || candidate.isBefore(nextRetry)) {
        nextRetry = candidate;
      }
    }
    if (nextRetry == null) {
      return;
    }
    final delay = nextRetry.difference(DateTime.now());
    _deferredTimer = Timer(delay.isNegative ? Duration.zero : delay, () {
      unawaited(run(token: token, ownerUserId: ownerUserId));
    });
  }

  bool _isTurnDue(Map<String, dynamic> row) {
    final dueAt = _dueAtForTurn(row);
    return dueAt == null || !DateTime.now().isBefore(dueAt);
  }

  DateTime? _dueAtForTurn(Map<String, dynamic> row) {
    final plannedAt = DateTime.tryParse(row['planned_at']?.toString() ?? '');
    final retryAt = DateTime.tryParse(row['retry_after']?.toString() ?? '');
    if (plannedAt != null && retryAt != null) {
      return plannedAt.isAfter(retryAt) ? plannedAt : retryAt;
    }

    return plannedAt ?? retryAt;
  }

  Map<String, dynamic> _decodeMap(List<int> bytes) {
    final decoded = jsonDecode(utf8.decode(bytes));
    return _asMap(decoded) ?? const <String, dynamic>{};
  }

  Map<String, dynamic>? _asMap(Object? value) {
    if (value is Map<String, dynamic>) {
      return value;
    }
    if (value is Map) {
      return value.map((key, val) => MapEntry(key.toString(), val));
    }
    return null;
  }

  List<Map<String, dynamic>> _mapsFromValue(Object? value) {
    if (value is! List) {
      return const [];
    }
    return value
        .whereType<Map>()
        .map((item) => item.map((key, val) => MapEntry(key.toString(), val)))
        .toList(growable: false);
  }
}

class _RetryableGeminiException implements Exception {
  final int statusCode;

  const _RetryableGeminiException(this.statusCode);

  @override
  String toString() => 'Gemini retryable $statusCode';
}

class _PermanentGeminiException implements Exception {
  final int statusCode;

  const _PermanentGeminiException(this.statusCode);

  @override
  String toString() => 'Gemini permanent error $statusCode';
}
