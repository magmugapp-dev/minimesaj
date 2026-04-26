import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/foundation.dart';
import 'package:magmug/core/chat/chat_text_sanitizer.dart';
import 'package:magmug/core/models/communication_models.dart';
import 'package:magmug/core/storage/app_storage.dart';
import 'package:path/path.dart' as path;
import 'package:path_provider/path_provider.dart';
import 'package:sqflite/sqflite.dart';

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
  Database? _database;

  Future<Database> get _db async {
    final existing = _database;
    if (existing != null) {
      return existing;
    }

    final databasesPath = await getDatabasesPath();
    final db = await openDatabase(
      path.join(databasesPath, 'magmug_chat_cache.db'),
      version: 8,
      onCreate: (db, version) async {
        await _createSchema(db);
      },
      onUpgrade: (db, oldVersion, newVersion) async {
        if (oldVersion < 2) {
          await db.execute(
            'ALTER TABLE conversation_messages ADD COLUMN cached_file_path TEXT',
          );
          await db.execute('''
            CREATE TABLE IF NOT EXISTS conversation_previews(
              id INTEGER PRIMARY KEY,
              match_id INTEGER NOT NULL,
              peer_id INTEGER NOT NULL,
              peer_name TEXT NOT NULL,
              peer_username TEXT NOT NULL,
              peer_profile_image_url TEXT,
              online INTEGER NOT NULL,
              last_message TEXT,
              last_message_type TEXT,
              last_message_at_ms INTEGER,
              unread_count INTEGER NOT NULL,
              my_message_read INTEGER NOT NULL
            )
          ''');
          await db.execute(
            'CREATE INDEX IF NOT EXISTS idx_conversation_previews_last_message_at ON conversation_previews(last_message_at_ms)',
          );
        }
        if (oldVersion < 3) {
          await db.execute(
            'ALTER TABLE conversation_messages ADD COLUMN cached_sender_profile_image_path TEXT',
          );
          await db.execute(
            'ALTER TABLE conversation_previews ADD COLUMN cached_peer_profile_image_path TEXT',
          );
        }
        if (oldVersion < 4) {
          await db.execute(
            'ALTER TABLE conversation_messages ADD COLUMN language_code TEXT',
          );
          await db.execute(
            'ALTER TABLE conversation_messages ADD COLUMN language_name TEXT',
          );
          await db.execute(
            'ALTER TABLE conversation_messages ADD COLUMN translated_text TEXT',
          );
          await db.execute(
            'ALTER TABLE conversation_messages ADD COLUMN translation_target_language_code TEXT',
          );
          await db.execute(
            'ALTER TABLE conversation_messages ADD COLUMN translation_target_language_name TEXT',
          );
          await db.execute(
            'ALTER TABLE conversation_previews ADD COLUMN peer_language_code TEXT',
          );
          await db.execute(
            'ALTER TABLE conversation_previews ADD COLUMN peer_language_name TEXT',
          );
        }
        if (oldVersion < 5) {
          await db.execute('''
            UPDATE conversation_messages
            SET translated_text = NULL,
                translation_target_language_code = NULL,
                translation_target_language_name = NULL
          ''');
        }
        if (oldVersion < 6) {
          await db.execute(
            'ALTER TABLE conversation_previews ADD COLUMN ai_status TEXT',
          );
          await db.execute(
            'ALTER TABLE conversation_previews ADD COLUMN ai_status_text TEXT',
          );
          await db.execute(
            'ALTER TABLE conversation_previews ADD COLUMN ai_planned_at_ms INTEGER',
          );
        }
        if (oldVersion < 7) {
          await db.execute(
            'ALTER TABLE conversation_messages ADD COLUMN owner_user_id INTEGER NOT NULL DEFAULT 0',
          );
          await db.execute(
            'ALTER TABLE conversation_previews ADD COLUMN owner_user_id INTEGER NOT NULL DEFAULT 0',
          );
          await db.execute(
            'CREATE INDEX IF NOT EXISTS idx_conversation_messages_owner_conversation ON conversation_messages(owner_user_id, conversation_id, id)',
          );
          await db.execute(
            'CREATE INDEX IF NOT EXISTS idx_conversation_previews_owner_last_message_at ON conversation_previews(owner_user_id, last_message_at_ms)',
          );
        }
        if (oldVersion < 8) {
          await db.execute(
            'ALTER TABLE conversation_messages ADD COLUMN client_message_id TEXT',
          );
          await db.execute(
            "ALTER TABLE conversation_messages ADD COLUMN delivery_status TEXT NOT NULL DEFAULT 'sent'",
          );
          await _createOutboxSchema(db);
        }
      },
    );
    _database = db;
    return db;
  }

  Future<void> _createSchema(Database db) async {
    await db.execute('''
      CREATE TABLE conversation_messages(
        id INTEGER PRIMARY KEY,
        owner_user_id INTEGER NOT NULL,
        conversation_id INTEGER NOT NULL,
        sender_id INTEGER,
        sender_name TEXT NOT NULL,
        sender_profile_image_url TEXT,
        cached_sender_profile_image_path TEXT,
        type TEXT NOT NULL,
        text TEXT,
        file_url TEXT,
        cached_file_path TEXT,
        file_duration_ms INTEGER,
        is_read INTEGER NOT NULL,
        is_ai_generated INTEGER NOT NULL,
        language_code TEXT,
        language_name TEXT,
        translated_text TEXT,
        translation_target_language_code TEXT,
        translation_target_language_name TEXT,
        client_message_id TEXT,
        delivery_status TEXT NOT NULL DEFAULT 'sent',
        created_at_ms INTEGER
      )
    ''');
    await db.execute(
      'CREATE INDEX idx_conversation_messages_conversation_id ON conversation_messages(conversation_id)',
    );
    await db.execute(
      'CREATE INDEX idx_conversation_messages_owner_conversation ON conversation_messages(owner_user_id, conversation_id, id)',
    );
    await db.execute('''
      CREATE TABLE conversation_previews(
        id INTEGER PRIMARY KEY,
        owner_user_id INTEGER NOT NULL,
        match_id INTEGER NOT NULL,
        peer_id INTEGER NOT NULL,
        peer_name TEXT NOT NULL,
        peer_username TEXT NOT NULL,
        peer_profile_image_url TEXT,
        cached_peer_profile_image_path TEXT,
        peer_language_code TEXT,
        peer_language_name TEXT,
        online INTEGER NOT NULL,
        last_message TEXT,
        last_message_type TEXT,
        last_message_at_ms INTEGER,
        unread_count INTEGER NOT NULL,
        my_message_read INTEGER NOT NULL,
        ai_status TEXT,
        ai_status_text TEXT,
        ai_planned_at_ms INTEGER
      )
    ''');
    await db.execute(
      'CREATE INDEX idx_conversation_previews_last_message_at ON conversation_previews(last_message_at_ms)',
    );
    await db.execute(
      'CREATE INDEX idx_conversation_previews_owner_last_message_at ON conversation_previews(owner_user_id, last_message_at_ms)',
    );
    await _createOutboxSchema(db);
  }

  Future<void> _createOutboxSchema(Database db) async {
    await db.execute('''
      CREATE TABLE IF NOT EXISTS message_outbox(
        local_id TEXT PRIMARY KEY,
        owner_user_id INTEGER NOT NULL,
        conversation_id INTEGER NOT NULL,
        client_message_id TEXT NOT NULL,
        client_upload_id TEXT,
        type TEXT NOT NULL,
        text TEXT,
        local_file_path TEXT,
        remote_file_path TEXT,
        file_duration_ms INTEGER,
        status TEXT NOT NULL,
        retry_count INTEGER NOT NULL DEFAULT 0,
        next_retry_at_ms INTEGER,
        last_error TEXT,
        created_at_ms INTEGER NOT NULL,
        updated_at_ms INTEGER NOT NULL
      )
    ''');
    await db.execute(
      'CREATE INDEX IF NOT EXISTS idx_message_outbox_owner_status ON message_outbox(owner_user_id, status, next_retry_at_ms)',
    );
  }

  Future<List<AppConversationPreview>> getConversationPreviews({
    required int ownerUserId,
  }) async {
    final db = await _db;
    final rows = await db.query(
      'conversation_previews',
      where: 'owner_user_id = ?',
      whereArgs: [ownerUserId],
      orderBy: 'last_message_at_ms DESC, id DESC',
    );

    return rows.map(_previewFromRow).toList(growable: false);
  }

  Future<AppConversationPreview?> getConversationPreview(
    int conversationId, {
    required int ownerUserId,
  }) async {
    final db = await _db;
    final rows = await db.query(
      'conversation_previews',
      where: 'owner_user_id = ? AND id = ?',
      whereArgs: [ownerUserId, conversationId],
      limit: 1,
    );
    if (rows.isEmpty) {
      return null;
    }

    return _previewFromRow(rows.first);
  }

  Future<void> upsertConversationPreviews(
    List<AppConversationPreview> conversations, {
    required int ownerUserId,
  }) async {
    if (conversations.isEmpty) {
      return;
    }

    final db = await _db;
    final batch = db.batch();
    for (final conversation in conversations) {
      final cachedAvatarPath = await _cacheRemoteFileIfNeeded(
        conversation.peerProfileImageUrl,
        ownerUserId: ownerUserId,
        category: 'avatars',
        fileName: 'peer_${conversation.peerId}',
      );
      batch.insert('conversation_previews', {
        'id': conversation.id,
        'owner_user_id': ownerUserId,
        'match_id': conversation.matchId,
        'peer_id': conversation.peerId,
        'peer_name': conversation.peerName,
        'peer_username': conversation.peerUsername,
        'peer_profile_image_url': conversation.peerProfileImageUrl,
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
      }, conflictAlgorithm: ConflictAlgorithm.replace);
    }
    await batch.commit(noResult: true);
    await cleanupStaleCache(ownerUserId: ownerUserId);
  }

  Future<List<AppConversationMessage>> getConversationMessages(
    int conversationId, {
    required int ownerUserId,
    int? limit,
  }) async {
    final db = await _db;
    final useRecentWindow = limit != null && limit > 0;
    final rows = await db.query(
      'conversation_messages',
      where: 'owner_user_id = ? AND conversation_id = ?',
      whereArgs: [ownerUserId, conversationId],
      orderBy: useRecentWindow
          ? 'created_at_ms DESC, id DESC'
          : 'created_at_ms ASC, id ASC',
      limit: useRecentWindow ? limit : null,
    );
    final messages = rows.map(_messageFromRow).toList(growable: false);
    if (!useRecentWindow) {
      return messages;
    }

    return messages.reversed.toList(growable: false);
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

    final db = await _db;
    final rows = await db.query(
      'conversation_messages',
      where: "owner_user_id = ? AND LOWER(COALESCE(text, '')) LIKE ?",
      whereArgs: [ownerUserId, '%$normalizedQuery%'],
      orderBy: 'created_at_ms DESC, id DESC',
      limit: limit,
    );

    final previewCache = <int, AppConversationPreview?>{};
    final results = <ChatLocalMessageSearchResult>[];
    for (final row in rows) {
      final message = _messageFromRow(row);
      final conversationId = message.conversationId;
      final preview =
          previewCache[conversationId] ??
          await getConversationPreview(
            conversationId,
            ownerUserId: ownerUserId,
          );
      previewCache[conversationId] = preview;
      if (preview == null) {
        continue;
      }
      results.add(
        ChatLocalMessageSearchResult(conversation: preview, message: message),
      );
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

    final db = await _db;
    final batch = db.batch();
    for (final message in messages) {
      _deleteLocalOptimisticMessageIfServerAcked(
        batch,
        message,
        ownerUserId: ownerUserId,
      );
      final row = await _rowFromMessage(message, ownerUserId: ownerUserId);
      batch.insert(
        'conversation_messages',
        row,
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    }
    await batch.commit(noResult: true);
    await cleanupStaleCache(ownerUserId: ownerUserId);
  }

  Future<void> upsertConversationMessage(
    AppConversationMessage message, {
    required int ownerUserId,
  }) async {
    final db = await _db;
    final batch = db.batch();
    _deleteLocalOptimisticMessageIfServerAcked(
      batch,
      message,
      ownerUserId: ownerUserId,
    );
    batch.insert(
      'conversation_messages',
      await _rowFromMessage(message, ownerUserId: ownerUserId),
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
    await batch.commit(noResult: true);
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
    final db = await _db;
    final batch = db.batch();
    batch.insert(
      'conversation_messages',
      await _rowFromMessage(localMessage, ownerUserId: ownerUserId),
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
    batch.insert('message_outbox', <String, Object?>{
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
    }, conflictAlgorithm: ConflictAlgorithm.replace);
    await batch.commit(noResult: true);
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
    final db = await _db;
    final batch = db.batch();
    batch.insert(
      'conversation_messages',
      await _rowFromMessage(localMessage, ownerUserId: ownerUserId),
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
    batch.insert('message_outbox', <String, Object?>{
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
    }, conflictAlgorithm: ConflictAlgorithm.replace);
    await batch.commit(noResult: true);
    return localMessage;
  }

  @override
  Future<List<ChatOutboxItem>> getPendingOutboxItems({
    required int ownerUserId,
    int limit = 20,
  }) async {
    final db = await _db;
    final nowMs = DateTime.now().millisecondsSinceEpoch;
    final staleSendingMs = DateTime.now()
        .subtract(const Duration(minutes: 1))
        .millisecondsSinceEpoch;
    final rows = await db.query(
      'message_outbox',
      where: '''
        owner_user_id = ?
        AND (
          status IN ('queued', 'failed')
          OR (status = 'sending' AND updated_at_ms <= ?)
        )
        AND (next_retry_at_ms IS NULL OR next_retry_at_ms <= ?)
      ''',
      whereArgs: [ownerUserId, staleSendingMs, nowMs],
      orderBy: 'created_at_ms ASC',
      limit: limit,
    );

    return rows.map(ChatOutboxItem.fromRow).toList(growable: false);
  }

  @override
  Future<void> markOutboxSending({
    required int ownerUserId,
    required String clientMessageId,
  }) async {
    final db = await _db;
    await db.update(
      'message_outbox',
      <String, Object?>{
        'status': 'sending',
        'updated_at_ms': DateTime.now().millisecondsSinceEpoch,
      },
      where: 'owner_user_id = ? AND client_message_id = ?',
      whereArgs: [ownerUserId, clientMessageId],
    );
    await db.update(
      'conversation_messages',
      <String, Object?>{'delivery_status': 'sending'},
      where: 'owner_user_id = ? AND client_message_id = ? AND id < 0',
      whereArgs: [ownerUserId, clientMessageId],
    );
  }

  @override
  Future<void> markOutboxSent({
    required int ownerUserId,
    required String clientMessageId,
    required AppConversationMessage sentMessage,
  }) async {
    final db = await _db;
    final batch = db.batch();
    batch.delete(
      'conversation_messages',
      where: 'owner_user_id = ? AND client_message_id = ? AND id < 0',
      whereArgs: [ownerUserId, clientMessageId],
    );
    batch.insert(
      'conversation_messages',
      await _rowFromMessage(sentMessage, ownerUserId: ownerUserId),
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
    batch.delete(
      'message_outbox',
      where: 'owner_user_id = ? AND client_message_id = ?',
      whereArgs: [ownerUserId, clientMessageId],
    );
    await batch.commit(noResult: true);
  }

  @override
  Future<void> markOutboxFailed({
    required int ownerUserId,
    required String clientMessageId,
    required String errorMessage,
  }) async {
    final db = await _db;
    final rows = await db.query(
      'message_outbox',
      columns: ['retry_count'],
      where: 'owner_user_id = ? AND client_message_id = ?',
      whereArgs: [ownerUserId, clientMessageId],
      limit: 1,
    );
    final currentRetryCount = rows.isEmpty
        ? 0
        : (rows.first['retry_count'] as num?)?.toInt() ?? 0;
    final retryCount = currentRetryCount + 1;
    final retryDelay = Duration(seconds: retryCount < 6 ? retryCount * 8 : 60);
    final now = DateTime.now();
    await db.update(
      'message_outbox',
      <String, Object?>{
        'status': 'failed',
        'retry_count': retryCount,
        'next_retry_at_ms': now.add(retryDelay).millisecondsSinceEpoch,
        'last_error': errorMessage,
        'updated_at_ms': now.millisecondsSinceEpoch,
      },
      where: 'owner_user_id = ? AND client_message_id = ?',
      whereArgs: [ownerUserId, clientMessageId],
    );
    await db.update(
      'conversation_messages',
      <String, Object?>{'delivery_status': 'failed'},
      where: 'owner_user_id = ? AND client_message_id = ? AND id < 0',
      whereArgs: [ownerUserId, clientMessageId],
    );
  }

  Future<void> queueOutboxRetry({
    required int ownerUserId,
    required String clientMessageId,
  }) async {
    final db = await _db;
    final nowMs = DateTime.now().millisecondsSinceEpoch;
    final batch = db.batch();
    batch.update(
      'message_outbox',
      <String, Object?>{
        'status': 'queued',
        'next_retry_at_ms': null,
        'updated_at_ms': nowMs,
      },
      where: 'owner_user_id = ? AND client_message_id = ?',
      whereArgs: [ownerUserId, clientMessageId],
    );
    batch.update(
      'conversation_messages',
      <String, Object?>{'delivery_status': 'queued'},
      where: 'owner_user_id = ? AND client_message_id = ? AND id < 0',
      whereArgs: [ownerUserId, clientMessageId],
    );
    await batch.commit(noResult: true);
  }

  @override
  Future<void> updateOutboxRemoteFilePath({
    required int ownerUserId,
    required String clientMessageId,
    required String remoteFilePath,
  }) async {
    final db = await _db;
    await db.update(
      'message_outbox',
      <String, Object?>{
        'remote_file_path': remoteFilePath,
        'updated_at_ms': DateTime.now().millisecondsSinceEpoch,
      },
      where: 'owner_user_id = ? AND client_message_id = ?',
      whereArgs: [ownerUserId, clientMessageId],
    );
  }

  Future<void> updateConversationPreviewRuntimeStatus(
    int conversationId, {
    required int ownerUserId,
    required String? aiStatus,
    required String? aiStatusText,
    required DateTime? aiPlannedAt,
  }) async {
    final db = await _db;
    await db.update(
      'conversation_previews',
      <String, Object?>{
        'ai_status': aiStatus,
        'ai_status_text': aiStatusText,
        'ai_planned_at_ms': aiPlannedAt?.millisecondsSinceEpoch,
      },
      where: 'owner_user_id = ? AND id = ?',
      whereArgs: [ownerUserId, conversationId],
    );
  }

  Future<bool> applyConversationMessageEvent({
    required int ownerUserId,
    required int conversationId,
    required int senderId,
    required int currentUserId,
    required String? messageType,
    required String? messageText,
    DateTime? createdAt,
  }) async {
    final db = await _db;
    final rows = await db.query(
      'conversation_previews',
      where: 'owner_user_id = ? AND id = ?',
      whereArgs: [ownerUserId, conversationId],
      limit: 1,
    );
    if (rows.isEmpty) {
      return false;
    }

    final row = rows.first;
    final currentUnreadCount = (row['unread_count'] as num?)?.toInt() ?? 0;
    final isMine = senderId == currentUserId;
    final effectiveCreatedAt = createdAt ?? DateTime.now();
    await db.update(
      'conversation_previews',
      <String, Object?>{
        'last_message': _messagePreviewText(messageType, messageText),
        'last_message_type': messageType,
        'last_message_at_ms': effectiveCreatedAt.millisecondsSinceEpoch,
        'unread_count': isMine ? currentUnreadCount : currentUnreadCount + 1,
        'my_message_read': isMine
            ? 0
            : ((row['my_message_read'] as num?)?.toInt() ?? 0),
        'ai_status': null,
        'ai_status_text': null,
        'ai_planned_at_ms': null,
      },
      where: 'owner_user_id = ? AND id = ?',
      whereArgs: [ownerUserId, conversationId],
    );
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
    );
  }

  Future<void> applyConversationReadEvent(
    int conversationId, {
    required int ownerUserId,
    required int readerUserId,
    required int currentUserId,
  }) async {
    final db = await _db;
    final patch = conversationReadPreviewPatch(
      readerUserId: readerUserId,
      currentUserId: currentUserId,
    );
    if (patch.isEmpty) {
      return;
    }

    final batch = db.batch();
    batch.update(
      'conversation_previews',
      patch,
      where: 'owner_user_id = ? AND id = ?',
      whereArgs: [ownerUserId, conversationId],
    );
    if (readerUserId == currentUserId) {
      batch.update(
        'conversation_messages',
        <String, Object?>{'is_read': 1},
        where:
            'owner_user_id = ? AND conversation_id = ? AND (sender_id IS NULL OR sender_id != ?)',
        whereArgs: [ownerUserId, conversationId, currentUserId],
      );
    } else {
      batch.update(
        'conversation_messages',
        <String, Object?>{'is_read': 1},
        where: 'owner_user_id = ? AND conversation_id = ? AND sender_id = ?',
        whereArgs: [ownerUserId, conversationId, currentUserId],
      );
    }
    await batch.commit(noResult: true);
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
      'translated_text': null,
      'translation_target_language_code': null,
      'translation_target_language_name': null,
      'client_message_id': _normalizedClientMessageId(message),
      'delivery_status': message.deliveryStatus,
      'created_at_ms': message.createdAt?.millisecondsSinceEpoch,
    };
  }

  void _deleteLocalOptimisticMessageIfServerAcked(
    Batch batch,
    AppConversationMessage message, {
    required int ownerUserId,
  }) {
    final clientMessageId = _normalizedClientMessageId(message);
    if (message.id <= 0 || clientMessageId == null) {
      return;
    }

    batch.delete(
      'conversation_messages',
      where: 'owner_user_id = ? AND client_message_id = ? AND id < 0',
      whereArgs: [ownerUserId, clientMessageId],
    );
  }

  String? _normalizedClientMessageId(AppConversationMessage message) {
    final clientMessageId = message.clientMessageId?.trim();
    if (clientMessageId == null || clientMessageId.isEmpty) {
      return null;
    }

    return clientMessageId;
  }

  AppConversationMessage _messageFromRow(Map<String, Object?> row) {
    final createdAtMs = row['created_at_ms'] as int?;
    final fileDurationMs = row['file_duration_ms'] as int?;
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
    final lastMessageAtMs = row['last_message_at_ms'] as int?;
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
      final databasesPath = await getDatabasesPath();
      final namespace = await AppSessionStorage.cacheNamespaceForUser(
        ownerUserId,
      );
      final cacheDir = Directory(
        path.join(databasesPath, 'chat_binary_cache', namespace, category),
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
      final databasesPath = await getDatabasesPath();
      final namespace = await AppSessionStorage.cacheNamespaceForUser(
        ownerUserId,
      );
      final rootDir = Directory(
        path.join(databasesPath, 'chat_binary_cache', namespace),
      );
      if (!await rootDir.exists()) {
        return;
      }

      final referencedPaths = <String>{};
      final db = await _db;
      final messageRows = await db.query(
        'conversation_messages',
        columns: ['cached_file_path', 'cached_sender_profile_image_path'],
        where: 'owner_user_id = ?',
        whereArgs: [ownerUserId],
      );
      for (final row in messageRows) {
        final mediaPath = row['cached_file_path']?.toString();
        final avatarPath = row['cached_sender_profile_image_path']?.toString();
        if (mediaPath != null && mediaPath.isNotEmpty) {
          referencedPaths.add(mediaPath);
        }
        if (avatarPath != null && avatarPath.isNotEmpty) {
          referencedPaths.add(avatarPath);
        }
      }
      final previewRows = await db.query(
        'conversation_previews',
        columns: ['cached_peer_profile_image_path'],
        where: 'owner_user_id = ?',
        whereArgs: [ownerUserId],
      );
      for (final row in previewRows) {
        final avatarPath = row['cached_peer_profile_image_path']?.toString();
        if (avatarPath != null && avatarPath.isNotEmpty) {
          referencedPaths.add(avatarPath);
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
        }
      }
    } catch (_) {
      // Cleanup should never break chat rendering.
    }
  }

  Future<void> clearUserData(int ownerUserId) async {
    final db = await _db;
    await db.delete(
      'conversation_messages',
      where: 'owner_user_id = ?',
      whereArgs: [ownerUserId],
    );
    await db.delete(
      'conversation_previews',
      where: 'owner_user_id = ?',
      whereArgs: [ownerUserId],
    );
    await db.delete(
      'message_outbox',
      where: 'owner_user_id = ?',
      whereArgs: [ownerUserId],
    );

    try {
      final databasesPath = await getDatabasesPath();
      final namespace = await AppSessionStorage.cacheNamespaceForUser(
        ownerUserId,
      );
      final rootDir = Directory(
        path.join(databasesPath, 'chat_binary_cache', namespace),
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
