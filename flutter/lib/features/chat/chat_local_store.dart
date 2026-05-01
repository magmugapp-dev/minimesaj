import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/foundation.dart';
import 'package:hive/hive.dart';
import 'package:magmug/core/chat/chat_text_sanitizer.dart';
import 'package:magmug/core/models/communication_models.dart';
import 'package:magmug/core/storage/app_storage.dart';
import 'package:path/path.dart' as path;
import 'package:path_provider/path_provider.dart';

abstract class ChatOutboxStore {
  Future<List<ChatOutboxItem>> getPendingOutboxItems({
    required int ownerUserId,
    int limit = 20,
  });

  Future<void> markOutboxSending({
    required int ownerUserId,
    required String clientMessageId,
  });

  Future<void> markOutboxSent({
    required int ownerUserId,
    required String clientMessageId,
    required AppConversationMessage sentMessage,
  });

  Future<void> markOutboxFailed({
    required int ownerUserId,
    required String clientMessageId,
    required String errorMessage,
  });

  Future<void> updateOutboxRemoteFilePath({
    required int ownerUserId,
    required String clientMessageId,
    required String remoteFilePath,
  });
}

@immutable
class ChatLocalMessageSearchResult {
  final AppConversationPreview conversation;
  final AppConversationMessage message;

  const ChatLocalMessageSearchResult({
    required this.conversation,
    required this.message,
  });
}

class ChatLocalStore implements ChatOutboxStore {
  ChatLocalStore._();

  static final ChatLocalStore instance = ChatLocalStore._();
  static const Duration _staleCacheAge = Duration(days: 14);

  Future<Box<dynamic>> get _messagesBox =>
      Hive.openBox<dynamic>('chat_messages');
  Future<Box<dynamic>> get _previewsBox =>
      Hive.openBox<dynamic>('chat_previews');
  Future<Box<dynamic>> get _outboxBox => Hive.openBox<dynamic>('chat_outbox');
  Future<Box<dynamic>> get _mediaIndexBox =>
      Hive.openBox<dynamic>('media_cache_index');

  Future<List<AppConversationPreview>> getConversationPreviews({
    required int ownerUserId,
  }) async {
    final box = await _previewsBox;
    final rows = box.values
        .map(_asRow)
        .where((row) => _rowOwner(row) == ownerUserId)
        .toList(growable: false);
    rows.sort((a, b) {
      final last = ((b['last_message_at_ms'] as num?)?.toInt() ?? 0).compareTo(
        (a['last_message_at_ms'] as num?)?.toInt() ?? 0,
      );
      if (last != 0) {
        return last;
      }
      return ((b['id'] as num?)?.toInt() ?? 0).compareTo(
        (a['id'] as num?)?.toInt() ?? 0,
      );
    });

    return rows.map(_previewFromRow).toList(growable: false);
  }

  Future<AppConversationPreview?> getConversationPreview(
    int conversationId, {
    required int ownerUserId,
  }) async {
    final box = await _previewsBox;
    final row = _asRow(box.get(_previewKey(ownerUserId, conversationId)));
    if (row.isEmpty) {
      return null;
    }

    return _previewFromRow(row);
  }

  Future<void> upsertConversationPreviews(
    List<AppConversationPreview> conversations, {
    required int ownerUserId,
  }) async {
    if (conversations.isEmpty) {
      return;
    }

    final box = await _previewsBox;
    for (final conversation in conversations) {
      final cachedAvatarPath = await _cacheRemoteFileIfNeeded(
        conversation.peerProfileImageUrl,
        ownerUserId: ownerUserId,
        category: 'avatars',
        fileName: 'peer_${conversation.peerId}',
      );
      await box.put(_previewKey(ownerUserId, conversation.id), {
        'id': conversation.id,
        'owner_user_id': ownerUserId,
        'match_id': conversation.matchId,
        'peer_id': conversation.peerId,
        'peer_name': conversation.peerName,
        'peer_username': conversation.peerUsername,
        'peer_profile_image_url': conversation.peerProfileImageUrl,
        'peer_account_type': conversation.peerAccountType,
        'cached_peer_profile_image_path': cachedAvatarPath,
        'peer_language_code': conversation.peerLanguageCode,
        'peer_language_name': conversation.peerLanguageName,
        'online': conversation.online ? 1 : 0,
        'last_message': conversation.lastMessage,
        'last_message_type': conversation.lastMessageType,
        'last_message_at_ms':
            conversation.lastMessageAt?.millisecondsSinceEpoch,
        'unread_count': conversation.unreadCount,
        'my_message_read': conversation.myMessageRead ? 1 : 0,
        'ai_status': conversation.aiStatus,
        'ai_status_text': conversation.aiStatusText,
        'ai_planned_at_ms': conversation.aiPlannedAt?.millisecondsSinceEpoch,
      });
    }
    await cleanupStaleCache(ownerUserId: ownerUserId);
  }

  Future<List<AppConversationMessage>> getConversationMessages(
    int conversationId, {
    required int ownerUserId,
    int? limit,
  }) async {
    final rows = await _conversationMessageRows(
      ownerUserId: ownerUserId,
      conversationId: conversationId,
    );
    rows.sort((a, b) {
      final byDate = ((a['created_at_ms'] as num?)?.toInt() ?? 0).compareTo(
        (b['created_at_ms'] as num?)?.toInt() ?? 0,
      );
      if (byDate != 0) {
        return byDate;
      }
      return ((a['id'] as num?)?.toInt() ?? 0).compareTo(
        (b['id'] as num?)?.toInt() ?? 0,
      );
    });

    final window = limit != null && limit > 0 && rows.length > limit
        ? rows.sublist(rows.length - limit)
        : rows;

    return window.map(_messageFromRow).toList(growable: false);
  }

  Future<List<AppConversationMessage>> getConversationMessagesBefore({
    required int ownerUserId,
    required int conversationId,
    required int beforeMessageId,
    int limit = 20,
  }) async {
    final rows =
        (await _conversationMessageRows(
              ownerUserId: ownerUserId,
              conversationId: conversationId,
            ))
            .where((row) {
              final id = (row['id'] as num?)?.toInt() ?? 0;
              return id > 0 && id < beforeMessageId;
            })
            .toList(growable: false);
    rows.sort((a, b) {
      final byId = ((b['id'] as num?)?.toInt() ?? 0).compareTo(
        (a['id'] as num?)?.toInt() ?? 0,
      );
      if (byId != 0) {
        return byId;
      }
      return ((b['created_at_ms'] as num?)?.toInt() ?? 0).compareTo(
        (a['created_at_ms'] as num?)?.toInt() ?? 0,
      );
    });

    return rows
        .take(limit)
        .map(_messageFromRow)
        .toList(growable: false)
        .reversed
        .toList(growable: false);
  }

  Future<bool> hasNoOlderMessages({
    required int ownerUserId,
    required int conversationId,
  }) async {
    final row = _asRow(
      (await _previewsBox).get(_previewKey(ownerUserId, conversationId)),
    );
    return (row['has_no_older_messages'] as num?)?.toInt() == 1;
  }

  Future<void> markNoOlderMessages({
    required int ownerUserId,
    required int conversationId,
  }) async {
    final box = await _previewsBox;
    final key = _previewKey(ownerUserId, conversationId);
    final row = _asRow(box.get(key));
    if (row.isEmpty) {
      return;
    }
    await box.put(key, {...row, 'has_no_older_messages': 1});
  }

  Future<List<ChatLocalMessageSearchResult>> searchConversationMessages({
    required int ownerUserId,
    required String query,
    int limit = 30,
  }) async {
    final normalizedQuery = query.trim().toLowerCase();
    if (normalizedQuery.isEmpty) {
      return const <ChatLocalMessageSearchResult>[];
    }

    final box = await _messagesBox;
    final rows = box.values
        .map(_asRow)
        .where((row) => _rowOwner(row) == ownerUserId)
        .where(
          (row) => (row['text']?.toString().toLowerCase() ?? '').contains(
            normalizedQuery,
          ),
        )
        .toList(growable: false);
    rows.sort((a, b) {
      final byDate = ((b['created_at_ms'] as num?)?.toInt() ?? 0).compareTo(
        (a['created_at_ms'] as num?)?.toInt() ?? 0,
      );
      if (byDate != 0) {
        return byDate;
      }
      return ((b['id'] as num?)?.toInt() ?? 0).compareTo(
        (a['id'] as num?)?.toInt() ?? 0,
      );
    });

    final results = <ChatLocalMessageSearchResult>[];
    for (final row in rows.take(limit)) {
      final message = _messageFromRow(row);
      final preview = await getConversationPreview(
        message.conversationId,
        ownerUserId: ownerUserId,
      );
      if (preview != null) {
        results.add(
          ChatLocalMessageSearchResult(conversation: preview, message: message),
        );
      }
    }

    return results;
  }

  Future<void> upsertConversationMessages(
    List<AppConversationMessage> messages, {
    required int ownerUserId,
  }) async {
    if (messages.isEmpty) {
      return;
    }

    for (final message in messages) {
      await upsertConversationMessage(message, ownerUserId: ownerUserId);
    }
  }

  Future<void> upsertConversationMessage(
    AppConversationMessage message, {
    required int ownerUserId,
  }) async {
    final box = await _messagesBox;
    await _deleteLocalOptimisticMessageIfServerAcked(
      message,
      ownerUserId: ownerUserId,
    );
    await box.put(
      _messageKey(ownerUserId, message.conversationId, message.id),
      await _rowFromMessage(message, ownerUserId: ownerUserId),
    );
    await cleanupStaleCache(ownerUserId: ownerUserId);
  }

  Future<AppConversationMessage> enqueueTextMessage({
    required int ownerUserId,
    required int conversationId,
    required int senderId,
    required String senderName,
    String? senderProfileImageUrl,
    required String text,
    required String clientMessageId,
  }) async {
    final now = DateTime.now();
    final localMessage = AppConversationMessage(
      id: -now.microsecondsSinceEpoch,
      conversationId: conversationId,
      senderId: senderId,
      senderName: senderName,
      senderProfileImageUrl: senderProfileImageUrl,
      type: 'metin',
      text: text,
      isRead: false,
      isAiGenerated: false,
      clientMessageId: clientMessageId,
      deliveryStatus: 'queued',
      createdAt: now,
    );

    await (await _messagesBox).put(
      _messageKey(ownerUserId, conversationId, localMessage.id),
      await _rowFromMessage(localMessage, ownerUserId: ownerUserId),
    );
    await (await _outboxBox)
        .put(_outboxKey(ownerUserId, clientMessageId), <String, Object?>{
          'local_id': clientMessageId,
          'owner_user_id': ownerUserId,
          'conversation_id': conversationId,
          'client_message_id': clientMessageId,
          'type': 'metin',
          'text': text,
          'status': 'queued',
          'retry_count': 0,
          'created_at_ms': now.millisecondsSinceEpoch,
          'updated_at_ms': now.millisecondsSinceEpoch,
        });
    return localMessage;
  }

  Future<AppConversationMessage> enqueueMediaMessage({
    required int ownerUserId,
    required int conversationId,
    required int senderId,
    required String senderName,
    String? senderProfileImageUrl,
    required String sourceFilePath,
    required String messageType,
    required String clientMessageId,
    required String clientUploadId,
    Duration? fileDuration,
  }) async {
    final now = DateTime.now();
    final localFilePath = await _copyPendingMediaFile(
      ownerUserId: ownerUserId,
      sourceFilePath: sourceFilePath,
      clientUploadId: clientUploadId,
      messageType: messageType,
    );
    final localMessage = AppConversationMessage(
      id: -now.microsecondsSinceEpoch,
      conversationId: conversationId,
      senderId: senderId,
      senderName: senderName,
      senderProfileImageUrl: senderProfileImageUrl,
      type: messageType,
      fileUrl: localFilePath,
      fileDuration: fileDuration,
      isRead: false,
      isAiGenerated: false,
      clientMessageId: clientMessageId,
      deliveryStatus: 'queued',
      createdAt: now,
    );

    await (await _messagesBox).put(
      _messageKey(ownerUserId, conversationId, localMessage.id),
      await _rowFromMessage(localMessage, ownerUserId: ownerUserId),
    );
    await (await _outboxBox)
        .put(_outboxKey(ownerUserId, clientMessageId), <String, Object?>{
          'local_id': clientMessageId,
          'owner_user_id': ownerUserId,
          'conversation_id': conversationId,
          'client_message_id': clientMessageId,
          'client_upload_id': clientUploadId,
          'type': messageType,
          'local_file_path': localFilePath,
          'file_duration_ms': fileDuration?.inMilliseconds,
          'status': 'queued',
          'retry_count': 0,
          'created_at_ms': now.millisecondsSinceEpoch,
          'updated_at_ms': now.millisecondsSinceEpoch,
        });
    return localMessage;
  }

  @override
  Future<List<ChatOutboxItem>> getPendingOutboxItems({
    required int ownerUserId,
    int limit = 20,
  }) async {
    final box = await _outboxBox;
    final nowMs = DateTime.now().millisecondsSinceEpoch;
    final staleSendingMs = DateTime.now()
        .subtract(const Duration(minutes: 1))
        .millisecondsSinceEpoch;
    final rows = box.values
        .map(_asRow)
        .where((row) {
          if (_rowOwner(row) != ownerUserId) {
            return false;
          }
          final status = row['status']?.toString();
          final updatedAt = (row['updated_at_ms'] as num?)?.toInt() ?? 0;
          final nextRetryAt = (row['next_retry_at_ms'] as num?)?.toInt();
          final due = nextRetryAt == null || nextRetryAt <= nowMs;
          return due &&
              (status == 'queued' ||
                  status == 'failed' ||
                  (status == 'sending' && updatedAt <= staleSendingMs));
        })
        .toList(growable: false);
    rows.sort(
      (a, b) => ((a['created_at_ms'] as num?)?.toInt() ?? 0).compareTo(
        (b['created_at_ms'] as num?)?.toInt() ?? 0,
      ),
    );

    return rows.take(limit).map(ChatOutboxItem.fromRow).toList(growable: false);
  }

  @override
  Future<void> markOutboxSending({
    required int ownerUserId,
    required String clientMessageId,
  }) async {
    await _patchOutbox(ownerUserId, clientMessageId, {
      'status': 'sending',
      'updated_at_ms': DateTime.now().millisecondsSinceEpoch,
    });
    await _patchOptimisticMessages(ownerUserId, clientMessageId, {
      'delivery_status': 'sending',
    });
  }

  @override
  Future<void> markOutboxSent({
    required int ownerUserId,
    required String clientMessageId,
    required AppConversationMessage sentMessage,
  }) async {
    final messagesBox = await _messagesBox;
    for (final key in await _messageKeysForClient(
      ownerUserId,
      clientMessageId,
    )) {
      await messagesBox.delete(key);
    }
    await messagesBox.put(
      _messageKey(ownerUserId, sentMessage.conversationId, sentMessage.id),
      await _rowFromMessage(sentMessage, ownerUserId: ownerUserId),
    );
    await (await _outboxBox).delete(_outboxKey(ownerUserId, clientMessageId));
  }

  @override
  Future<void> markOutboxFailed({
    required int ownerUserId,
    required String clientMessageId,
    required String errorMessage,
  }) async {
    final box = await _outboxBox;
    final key = _outboxKey(ownerUserId, clientMessageId);
    final row = _asRow(box.get(key));
    final retryCount = ((row['retry_count'] as num?)?.toInt() ?? 0) + 1;
    final retryDelay = Duration(seconds: retryCount < 6 ? retryCount * 8 : 60);
    final now = DateTime.now();
    await box.put(key, {
      ...row,
      'status': 'failed',
      'retry_count': retryCount,
      'next_retry_at_ms': now.add(retryDelay).millisecondsSinceEpoch,
      'last_error': errorMessage,
      'updated_at_ms': now.millisecondsSinceEpoch,
    });
    await _patchOptimisticMessages(ownerUserId, clientMessageId, {
      'delivery_status': 'failed',
    });
  }

  Future<void> queueOutboxRetry({
    required int ownerUserId,
    required String clientMessageId,
  }) async {
    await _patchOutbox(ownerUserId, clientMessageId, {
      'status': 'queued',
      'next_retry_at_ms': null,
      'updated_at_ms': DateTime.now().millisecondsSinceEpoch,
    });
    await _patchOptimisticMessages(ownerUserId, clientMessageId, {
      'delivery_status': 'queued',
    });
  }

  @override
  Future<void> updateOutboxRemoteFilePath({
    required int ownerUserId,
    required String clientMessageId,
    required String remoteFilePath,
  }) {
    return _patchOutbox(ownerUserId, clientMessageId, {
      'remote_file_path': remoteFilePath,
      'updated_at_ms': DateTime.now().millisecondsSinceEpoch,
    });
  }

  Future<void> updateConversationPreviewRuntimeStatus(
    int conversationId, {
    required int ownerUserId,
    required String? aiStatus,
    required String? aiStatusText,
    required DateTime? aiPlannedAt,
  }) async {
    final box = await _previewsBox;
    final key = _previewKey(ownerUserId, conversationId);
    final row = _asRow(box.get(key));
    if (row.isEmpty) {
      return;
    }
    await box.put(key, {
      ...row,
      'ai_status': aiStatus,
      'ai_status_text': aiStatusText,
      'ai_planned_at_ms': aiPlannedAt?.millisecondsSinceEpoch,
    });
  }

  Future<void> updatePeerOnlineStatus({
    required int ownerUserId,
    required int peerId,
    required bool online,
  }) async {
    final box = await _previewsBox;
    for (final key in box.keys.toList(growable: false)) {
      final row = _asRow(box.get(key));
      if (_rowOwner(row) != ownerUserId ||
          ((row['peer_id'] as num?)?.toInt() ?? 0) != peerId) {
        continue;
      }
      await box.put(key, {...row, 'online': online ? 1 : 0});
    }
  }

  Future<void> clearConversation({
    required int ownerUserId,
    required int conversationId,
  }) async {
    final messagesBox = await _messagesBox;
    final messageKeys = messagesBox.keys
        .where((key) {
          final row = _asRow(messagesBox.get(key));
          return _rowOwner(row) == ownerUserId &&
              ((row['conversation_id'] as num?)?.toInt() ?? 0) ==
                  conversationId;
        })
        .toList(growable: false);
    await messagesBox.deleteAll(messageKeys);
    await (await _previewsBox).delete(_previewKey(ownerUserId, conversationId));
  }

  Future<bool> applyConversationMessageEvent({
    required int ownerUserId,
    required int conversationId,
    required int senderId,
    required int currentUserId,
    required String? messageType,
    required String? messageText,
    DateTime? createdAt,
    bool isAiMessage = false,
  }) async {
    final box = await _previewsBox;
    final key = _previewKey(ownerUserId, conversationId);
    final row = _asRow(box.get(key));
    if (row.isEmpty) {
      return false;
    }

    final currentUnreadCount = (row['unread_count'] as num?)?.toInt() ?? 0;
    final isMine = senderId == currentUserId;
    // AI mesajları için unread sayacı artırılmaz
    final shouldIncrement = !isMine && !isAiMessage;
    final effectiveCreatedAt = createdAt ?? DateTime.now();
    await box.put(key, {
      ...row,
      'last_message': _messagePreviewText(messageType, messageText),
      'last_message_type': messageType,
      'last_message_at_ms': effectiveCreatedAt.millisecondsSinceEpoch,
      'unread_count': shouldIncrement
          ? currentUnreadCount + 1
          : currentUnreadCount,
      'my_message_read': isMine
          ? 0
          : ((row['my_message_read'] as num?)?.toInt() ?? 0),
      'ai_status': null,
      'ai_status_text': null,
      'ai_planned_at_ms': null,
    });
    return true;
  }

  Future<bool> applyRealtimeMessageEvent({
    required int ownerUserId,
    required int currentUserId,
    required AppConversationMessage message,
  }) async {
    if (message.conversationId <= 0) {
      return false;
    }

    await upsertConversationMessage(message, ownerUserId: ownerUserId);
    final senderId = message.senderId;
    if (senderId == null) {
      return false;
    }

    return applyConversationMessageEvent(
      ownerUserId: ownerUserId,
      conversationId: message.conversationId,
      senderId: senderId,
      currentUserId: currentUserId,
      messageType: message.type,
      messageText: message.text,
      createdAt: message.createdAt,
      isAiMessage: message.isAiGenerated,
    );
  }

  Future<void> applyConversationReadEvent(
    int conversationId, {
    required int ownerUserId,
    required int readerUserId,
    required int currentUserId,
  }) async {
    final previewPatch = conversationReadPreviewPatch(
      readerUserId: readerUserId,
      currentUserId: currentUserId,
    );
    if (previewPatch.isEmpty) {
      return;
    }

    final previewBox = await _previewsBox;
    final previewKey = _previewKey(ownerUserId, conversationId);
    final preview = _asRow(previewBox.get(previewKey));
    if (preview.isNotEmpty) {
      await previewBox.put(previewKey, {...preview, ...previewPatch});
    }

    final messagesBox = await _messagesBox;
    final rows = await _conversationMessageRows(
      ownerUserId: ownerUserId,
      conversationId: conversationId,
    );
    for (final row in rows) {
      final senderId = (row['sender_id'] as num?)?.toInt();
      final shouldMark = readerUserId == currentUserId
          ? senderId == null || senderId != currentUserId
          : senderId == currentUserId;
      if (shouldMark) {
        await messagesBox.put(
          _messageKey(
            ownerUserId,
            conversationId,
            (row['id'] as num?)?.toInt() ?? 0,
          ),
          {...row, 'is_read': 1},
        );
      }
    }
  }

  Map<String, Object?> conversationReadPreviewPatch({
    required int readerUserId,
    required int currentUserId,
  }) {
    return <String, Object?>{
      if (readerUserId == currentUserId) 'unread_count': 0,
      if (readerUserId != currentUserId) 'my_message_read': 1,
    };
  }

  Future<Map<String, Object?>> _rowFromMessage(
    AppConversationMessage message, {
    required int ownerUserId,
  }) async {
    final cachedFilePath = await _cacheMediaFileIfNeeded(
      message,
      ownerUserId: ownerUserId,
    );
    final cachedSenderAvatarPath = await _cacheRemoteFileIfNeeded(
      message.senderProfileImageUrl,
      ownerUserId: ownerUserId,
      category: 'avatars',
      fileName: 'sender_${message.senderId ?? message.id}',
    );

    return {
      'id': message.id,
      'owner_user_id': ownerUserId,
      'conversation_id': message.conversationId,
      'sender_id': message.senderId,
      'sender_name': message.senderName,
      'sender_profile_image_url': message.senderProfileImageUrl,
      'cached_sender_profile_image_path': cachedSenderAvatarPath,
      'type': message.type,
      'text': message.text,
      'file_url': message.fileUrl,
      'cached_file_path': cachedFilePath,
      'file_duration_ms': message.fileDuration?.inMilliseconds,
      'is_read': message.isRead ? 1 : 0,
      'is_ai_generated': message.isAiGenerated ? 1 : 0,
      'language_code': message.languageCode,
      'language_name': message.languageName,
      'translated_text': message.translatedText,
      'translation_target_language_code': message.translationTargetLanguageCode,
      'translation_target_language_name': message.translationTargetLanguageName,
      'client_message_id': _normalizedClientMessageId(message),
      'delivery_status': message.deliveryStatus,
      'created_at_ms': message.createdAt?.millisecondsSinceEpoch,
    };
  }

  Future<void> _deleteLocalOptimisticMessageIfServerAcked(
    AppConversationMessage message, {
    required int ownerUserId,
  }) async {
    final clientMessageId = _normalizedClientMessageId(message);
    if (message.id <= 0 || clientMessageId == null) {
      return;
    }

    final box = await _messagesBox;
    for (final key in await _messageKeysForClient(
      ownerUserId,
      clientMessageId,
    )) {
      final row = _asRow(box.get(key));
      if (((row['id'] as num?)?.toInt() ?? 0) < 0) {
        await box.delete(key);
      }
    }
  }

  String? _normalizedClientMessageId(AppConversationMessage message) {
    final clientMessageId = message.clientMessageId?.trim();
    if (clientMessageId == null || clientMessageId.isEmpty) {
      return null;
    }

    return clientMessageId;
  }

  AppConversationMessage _messageFromRow(Map<String, Object?> row) {
    final createdAtMs = (row['created_at_ms'] as num?)?.toInt();
    final fileDurationMs = (row['file_duration_ms'] as num?)?.toInt();
    final cachedFilePath = row['cached_file_path']?.toString();
    final cachedSenderAvatarPath = row['cached_sender_profile_image_path']
        ?.toString();
    final resolvedFileUrl =
        cachedFilePath != null &&
            cachedFilePath.isNotEmpty &&
            File(cachedFilePath).existsSync()
        ? cachedFilePath
        : row['file_url']?.toString();
    final resolvedSenderAvatarUrl =
        cachedSenderAvatarPath != null &&
            cachedSenderAvatarPath.isNotEmpty &&
            File(cachedSenderAvatarPath).existsSync()
        ? cachedSenderAvatarPath
        : row['sender_profile_image_url']?.toString();

    return AppConversationMessage(
      id: (row['id'] as num?)?.toInt() ?? 0,
      conversationId: (row['conversation_id'] as num?)?.toInt() ?? 0,
      senderId: (row['sender_id'] as num?)?.toInt(),
      senderName: row['sender_name']?.toString() ?? '',
      senderProfileImageUrl: resolvedSenderAvatarUrl,
      type: row['type']?.toString() ?? 'metin',
      text: row['text']?.toString(),
      fileUrl: resolvedFileUrl,
      fileDuration: fileDurationMs == null
          ? null
          : Duration(milliseconds: fileDurationMs),
      isRead: (row['is_read'] as num?)?.toInt() == 1,
      isAiGenerated: (row['is_ai_generated'] as num?)?.toInt() == 1,
      languageCode: row['language_code']?.toString(),
      languageName: row['language_name']?.toString(),
      translatedText: row['translated_text']?.toString(),
      translationTargetLanguageCode: row['translation_target_language_code']
          ?.toString(),
      translationTargetLanguageName: row['translation_target_language_name']
          ?.toString(),
      clientMessageId: row['client_message_id']?.toString(),
      deliveryStatus: row['delivery_status']?.toString() ?? 'sent',
      createdAt: createdAtMs == null
          ? null
          : DateTime.fromMillisecondsSinceEpoch(createdAtMs),
    );
  }

  AppConversationPreview _previewFromRow(Map<String, Object?> row) {
    final lastMessageAtMs = (row['last_message_at_ms'] as num?)?.toInt();
    final cachedPeerAvatarPath = row['cached_peer_profile_image_path']
        ?.toString();
    final resolvedPeerAvatarUrl =
        cachedPeerAvatarPath != null &&
            cachedPeerAvatarPath.isNotEmpty &&
            File(cachedPeerAvatarPath).existsSync()
        ? cachedPeerAvatarPath
        : row['peer_profile_image_url']?.toString();

    return AppConversationPreview(
      id: (row['id'] as num?)?.toInt() ?? 0,
      matchId: (row['match_id'] as num?)?.toInt() ?? 0,
      peerId: (row['peer_id'] as num?)?.toInt() ?? 0,
      peerName: row['peer_name']?.toString() ?? '',
      peerUsername: row['peer_username']?.toString() ?? '',
      peerProfileImageUrl: resolvedPeerAvatarUrl,
      peerAccountType: row['peer_account_type']?.toString(),
      peerLanguageCode: row['peer_language_code']?.toString(),
      peerLanguageName: row['peer_language_name']?.toString(),
      online: (row['online'] as num?)?.toInt() == 1,
      lastMessage: row['last_message']?.toString(),
      lastMessageType: row['last_message_type']?.toString(),
      lastMessageAt: lastMessageAtMs == null
          ? null
          : DateTime.fromMillisecondsSinceEpoch(lastMessageAtMs),
      unreadCount: (row['unread_count'] as num?)?.toInt() ?? 0,
      myMessageRead: (row['my_message_read'] as num?)?.toInt() == 1,
      aiStatus: row['ai_status']?.toString(),
      aiStatusText: row['ai_status_text']?.toString(),
      aiPlannedAt: (row['ai_planned_at_ms'] as num?) == null
          ? null
          : DateTime.fromMillisecondsSinceEpoch(
              (row['ai_planned_at_ms'] as num).toInt(),
            ),
    );
  }

  Future<String?> _cacheMediaFileIfNeeded(
    AppConversationMessage message, {
    required int ownerUserId,
  }) async {
    final fileUrl = message.fileUrl?.trim();
    final type = message.type.trim().toLowerCase();
    if (fileUrl == null ||
        fileUrl.isEmpty ||
        !(type == 'foto' || type == 'gorsel' || type == 'ses')) {
      return null;
    }

    if (!fileUrl.startsWith('http')) {
      return fileUrl;
    }

    final category = type == 'ses' ? 'audio' : 'media';
    return _cacheRemoteFileIfNeeded(
      fileUrl,
      ownerUserId: ownerUserId,
      category: category,
      fileName: '${message.conversationId}_${message.id}',
    );
  }

  Future<String> _copyPendingMediaFile({
    required int ownerUserId,
    required String sourceFilePath,
    required String clientUploadId,
    required String messageType,
  }) async {
    final source = File(sourceFilePath);
    if (!await source.exists()) {
      throw FileSystemException(
        'Pending media source not found',
        sourceFilePath,
      );
    }

    final namespace = await AppSessionStorage.cacheNamespaceForUser(
      ownerUserId,
    );
    final supportDir = await getApplicationSupportDirectory();
    final pendingDir = Directory(
      path.join(supportDir.path, 'pending_media', namespace, messageType),
    );
    if (!await pendingDir.exists()) {
      await pendingDir.create(recursive: true);
    }

    final extension = path.extension(source.path);
    final safeExtension = extension.isEmpty
        ? (messageType == 'ses' ? '.m4a' : '.jpg')
        : extension;
    final target = File(
      path.join(pendingDir.path, '$clientUploadId$safeExtension'),
    );
    if (await target.exists()) {
      return target.path;
    }

    await source.copy(target.path);
    await _indexMediaPath(
      ownerUserId: ownerUserId,
      pathValue: target.path,
      category: 'pending_$messageType',
      remoteUrl: null,
    );
    return target.path;
  }

  String? _messagePreviewText(String? messageType, String? messageText) {
    final normalizedText = ChatTextSanitizer.sanitize(messageText);
    if (normalizedText != null && normalizedText.isNotEmpty) {
      return normalizedText;
    }

    return switch (messageType?.trim().toLowerCase()) {
      'foto' => 'Fotograf gonderildi',
      'gorsel' => 'Fotograf gonderildi',
      'ses' => 'Sesli mesaj gonderildi',
      'video' => 'Video gonderildi',
      _ => null,
    };
  }

  Future<String?> _cacheRemoteFileIfNeeded(
    String? rawUrl, {
    required int ownerUserId,
    required String category,
    required String fileName,
  }) async {
    final fileUrl = rawUrl?.trim();
    if (fileUrl == null || fileUrl.isEmpty || !fileUrl.startsWith('http')) {
      return null;
    }

    try {
      final namespace = await AppSessionStorage.cacheNamespaceForUser(
        ownerUserId,
      );
      final supportDir = await getApplicationSupportDirectory();
      final cacheDir = Directory(
        path.join(supportDir.path, 'chat_binary_cache', namespace, category),
      );
      if (!await cacheDir.exists()) {
        await cacheDir.create(recursive: true);
      }

      final uri = Uri.parse(fileUrl);
      final fileExtension = path.extension(uri.path);
      final safeExtension = fileExtension.isEmpty ? '.img' : fileExtension;
      final localFile = File(
        path.join(cacheDir.path, '$fileName$safeExtension'),
      );
      if (await localFile.exists()) {
        await _indexMediaPath(
          ownerUserId: ownerUserId,
          pathValue: localFile.path,
          category: category,
          remoteUrl: fileUrl,
        );
        return localFile.path;
      }

      final client = HttpClient();
      try {
        final request = await client.getUrl(uri);
        final response = await request.close();
        if (response.statusCode < 200 || response.statusCode >= 300) {
          return null;
        }

        final bytesBuilder = BytesBuilder(copy: false);
        await for (final chunk in response) {
          bytesBuilder.add(chunk);
        }
        await localFile.writeAsBytes(bytesBuilder.takeBytes(), flush: true);
        await _indexMediaPath(
          ownerUserId: ownerUserId,
          pathValue: localFile.path,
          category: category,
          remoteUrl: fileUrl,
        );
        return localFile.path;
      } finally {
        client.close(force: true);
      }
    } catch (_) {
      return null;
    }
  }

  Future<void> cleanupStaleCache({required int ownerUserId}) async {
    try {
      final namespace = await AppSessionStorage.cacheNamespaceForUser(
        ownerUserId,
      );
      final supportDir = await getApplicationSupportDirectory();
      final rootDir = Directory(
        path.join(supportDir.path, 'chat_binary_cache', namespace),
      );
      if (!await rootDir.exists()) {
        return;
      }

      final referencedPaths = <String>{};
      for (final row in (await _messagesBox).values.map(_asRow)) {
        if (_rowOwner(row) != ownerUserId) {
          continue;
        }
        for (final field in [
          'cached_file_path',
          'cached_sender_profile_image_path',
        ]) {
          final value = row[field]?.toString();
          if (value != null && value.isNotEmpty) {
            referencedPaths.add(value);
          }
        }
      }
      for (final row in (await _previewsBox).values.map(_asRow)) {
        if (_rowOwner(row) != ownerUserId) {
          continue;
        }
        final value = row['cached_peer_profile_image_path']?.toString();
        if (value != null && value.isNotEmpty) {
          referencedPaths.add(value);
        }
      }

      final now = DateTime.now();
      await for (final entity in rootDir.list(recursive: true)) {
        if (entity is! File) {
          continue;
        }

        final stat = await entity.stat();
        final isStale = now.difference(stat.modified) > _staleCacheAge;
        if (isStale || !referencedPaths.contains(entity.path)) {
          await entity.delete();
          await (await _mediaIndexBox).delete(
            _mediaKey(ownerUserId, entity.path),
          );
        }
      }
    } catch (_) {
      // Cleanup should never break chat rendering.
    }
  }

  Future<void> clearUserData(int ownerUserId) async {
    await _deleteRowsForOwner(await _messagesBox, ownerUserId);
    await _deleteRowsForOwner(await _previewsBox, ownerUserId);
    await _deleteRowsForOwner(await _outboxBox, ownerUserId);
    await _deleteRowsForOwner(await _mediaIndexBox, ownerUserId);

    try {
      final namespace = await AppSessionStorage.cacheNamespaceForUser(
        ownerUserId,
      );
      final supportDir = await getApplicationSupportDirectory();
      final rootDir = Directory(
        path.join(supportDir.path, 'chat_binary_cache', namespace),
      );
      if (await rootDir.exists()) {
        await rootDir.delete(recursive: true);
      }
    } catch (_) {}

    try {
      final namespace = await AppSessionStorage.cacheNamespaceForUser(
        ownerUserId,
      );
      final supportDir = await getApplicationSupportDirectory();
      final pendingDir = Directory(
        path.join(supportDir.path, 'pending_media', namespace),
      );
      if (await pendingDir.exists()) {
        await pendingDir.delete(recursive: true);
      }
    } catch (_) {}
  }

  Future<List<Map<String, Object?>>> _conversationMessageRows({
    required int ownerUserId,
    required int conversationId,
  }) async {
    final box = await _messagesBox;
    return box.values
        .map(_asRow)
        .where(
          (row) =>
              _rowOwner(row) == ownerUserId &&
              ((row['conversation_id'] as num?)?.toInt() ?? 0) ==
                  conversationId,
        )
        .toList(growable: false);
  }

  Future<List<dynamic>> _messageKeysForClient(
    int ownerUserId,
    String clientMessageId,
  ) async {
    final box = await _messagesBox;
    return box.keys
        .where((key) {
          final row = _asRow(box.get(key));
          return _rowOwner(row) == ownerUserId &&
              row['client_message_id']?.toString() == clientMessageId;
        })
        .toList(growable: false);
  }

  Future<void> _patchOutbox(
    int ownerUserId,
    String clientMessageId,
    Map<String, Object?> patch,
  ) async {
    final box = await _outboxBox;
    final key = _outboxKey(ownerUserId, clientMessageId);
    final row = _asRow(box.get(key));
    if (row.isEmpty) {
      return;
    }
    await box.put(key, {...row, ...patch});
  }

  Future<void> _patchOptimisticMessages(
    int ownerUserId,
    String clientMessageId,
    Map<String, Object?> patch,
  ) async {
    final box = await _messagesBox;
    for (final key in await _messageKeysForClient(
      ownerUserId,
      clientMessageId,
    )) {
      final row = _asRow(box.get(key));
      if (((row['id'] as num?)?.toInt() ?? 0) < 0) {
        await box.put(key, {...row, ...patch});
      }
    }
  }

  Future<void> _indexMediaPath({
    required int ownerUserId,
    required String pathValue,
    required String category,
    required String? remoteUrl,
  }) async {
    await (await _mediaIndexBox).put(_mediaKey(ownerUserId, pathValue), {
      'owner_user_id': ownerUserId,
      'path': pathValue,
      'category': category,
      'remote_url': remoteUrl,
      'updated_at_ms': DateTime.now().millisecondsSinceEpoch,
    });
  }

  Future<void> _deleteRowsForOwner(Box<dynamic> box, int ownerUserId) async {
    final keys = box.keys
        .where((key) => _rowOwner(_asRow(box.get(key))) == ownerUserId)
        .toList(growable: false);
    await box.deleteAll(keys);
  }

  Map<String, Object?> _asRow(Object? value) {
    if (value is Map<String, Object?>) {
      return Map<String, Object?>.from(value);
    }
    if (value is Map) {
      return value.map((key, value) => MapEntry(key.toString(), value));
    }
    return <String, Object?>{};
  }

  int _rowOwner(Map<String, Object?> row) {
    return (row['owner_user_id'] as num?)?.toInt() ?? 0;
  }

  String _messageKey(int ownerUserId, int conversationId, int messageId) =>
      '$ownerUserId:$conversationId:$messageId';

  String _previewKey(int ownerUserId, int conversationId) =>
      '$ownerUserId:$conversationId';

  String _outboxKey(int ownerUserId, String clientMessageId) =>
      '$ownerUserId:$clientMessageId';

  String _mediaKey(int ownerUserId, String pathValue) =>
      '$ownerUserId:$pathValue';
}

class ChatOutboxItem {
  final String localId;
  final int ownerUserId;
  final int conversationId;
  final String clientMessageId;
  final String? clientUploadId;
  final String type;
  final String? text;
  final String? localFilePath;
  final String? remoteFilePath;
  final Duration? fileDuration;

  const ChatOutboxItem({
    required this.localId,
    required this.ownerUserId,
    required this.conversationId,
    required this.clientMessageId,
    this.clientUploadId,
    required this.type,
    this.text,
    this.localFilePath,
    this.remoteFilePath,
    this.fileDuration,
  });

  factory ChatOutboxItem.fromRow(Map<String, Object?> row) {
    final fileDurationMs = (row['file_duration_ms'] as num?)?.toInt();
    return ChatOutboxItem(
      localId: row['local_id']?.toString() ?? '',
      ownerUserId: (row['owner_user_id'] as num?)?.toInt() ?? 0,
      conversationId: (row['conversation_id'] as num?)?.toInt() ?? 0,
      clientMessageId: row['client_message_id']?.toString() ?? '',
      clientUploadId: row['client_upload_id']?.toString(),
      type: row['type']?.toString() ?? 'metin',
      text: row['text']?.toString(),
      localFilePath: row['local_file_path']?.toString(),
      remoteFilePath: row['remote_file_path']?.toString(),
      fileDuration: fileDurationMs == null
          ? null
          : Duration(milliseconds: fileDurationMs),
    );
  }
}
