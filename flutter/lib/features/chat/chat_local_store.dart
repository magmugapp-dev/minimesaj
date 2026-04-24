import 'dart:io';
import 'dart:typed_data';

import 'package:magmug/app_core.dart';
import 'package:path/path.dart' as path;
import 'package:sqflite/sqflite.dart';

class ChatLocalStore {
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
      version: 6,
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
      },
    );
    _database = db;
    return db;
  }

  Future<void> _createSchema(Database db) async {
    await db.execute('''
      CREATE TABLE conversation_messages(
        id INTEGER PRIMARY KEY,
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
        created_at_ms INTEGER
      )
    ''');
    await db.execute(
      'CREATE INDEX idx_conversation_messages_conversation_id ON conversation_messages(conversation_id)',
    );
    await db.execute('''
      CREATE TABLE conversation_previews(
        id INTEGER PRIMARY KEY,
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
  }

  Future<List<AppConversationPreview>> getConversationPreviews() async {
    final db = await _db;
    final rows = await db.query(
      'conversation_previews',
      orderBy: 'last_message_at_ms DESC, id DESC',
    );

    return rows.map(_previewFromRow).toList(growable: false);
  }

  Future<void> upsertConversationPreviews(
    List<AppConversationPreview> conversations,
  ) async {
    if (conversations.isEmpty) {
      return;
    }

    final db = await _db;
    final batch = db.batch();
    for (final conversation in conversations) {
      final cachedAvatarPath = await _cacheRemoteFileIfNeeded(
        conversation.peerProfileImageUrl,
        category: 'avatars',
        fileName: 'peer_${conversation.peerId}',
      );
      batch.insert('conversation_previews', {
        'id': conversation.id,
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
    await cleanupStaleCache();
  }

  Future<List<AppConversationMessage>> getConversationMessages(
    int conversationId, {
    int? limit,
  }) async {
    final db = await _db;
    final useRecentWindow = limit != null && limit > 0;
    final rows = await db.query(
      'conversation_messages',
      where: 'conversation_id = ?',
      whereArgs: [conversationId],
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

  Future<void> upsertConversationMessages(
    List<AppConversationMessage> messages,
  ) async {
    if (messages.isEmpty) {
      return;
    }

    final db = await _db;
    final batch = db.batch();
    for (final message in messages) {
      final row = await _rowFromMessage(message);
      batch.insert(
        'conversation_messages',
        row,
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    }
    await batch.commit(noResult: true);
    await cleanupStaleCache();
  }

  Future<void> upsertConversationMessage(AppConversationMessage message) async {
    final db = await _db;
    await db.insert(
      'conversation_messages',
      await _rowFromMessage(message),
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
    await cleanupStaleCache();
  }

  Future<void> updateConversationPreviewRuntimeStatus(
    int conversationId, {
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
      where: 'id = ?',
      whereArgs: [conversationId],
    );
  }

  Future<void> applyConversationMessageEvent({
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
      where: 'id = ?',
      whereArgs: [conversationId],
      limit: 1,
    );
    if (rows.isEmpty) {
      return;
    }

    final row = rows.first;
    final currentUnreadCount = (row['unread_count'] as num?)?.toInt() ?? 0;
    final isMine = senderId == currentUserId;
    await db.update(
      'conversation_previews',
      <String, Object?>{
        'last_message': _messagePreviewText(messageType, messageText),
        'last_message_type': messageType,
        'last_message_at_ms': createdAt?.millisecondsSinceEpoch,
        'unread_count': isMine ? currentUnreadCount : currentUnreadCount + 1,
        'my_message_read': isMine
            ? 0
            : ((row['my_message_read'] as num?)?.toInt() ?? 0),
        'ai_status': null,
        'ai_status_text': null,
        'ai_planned_at_ms': null,
      },
      where: 'id = ?',
      whereArgs: [conversationId],
    );
  }

  Future<void> applyConversationReadEvent(
    int conversationId, {
    required int readerUserId,
    required int currentUserId,
  }) async {
    final db = await _db;
    await db.update(
      'conversation_previews',
      <String, Object?>{
        if (readerUserId == currentUserId) 'unread_count': 0,
        if (readerUserId != currentUserId) 'my_message_read': 1,
      },
      where: 'id = ?',
      whereArgs: [conversationId],
    );
  }

  Future<Map<String, Object?>> _rowFromMessage(
    AppConversationMessage message,
  ) async {
    final cachedFilePath = await _cacheMediaFileIfNeeded(message);
    final cachedSenderAvatarPath = await _cacheRemoteFileIfNeeded(
      message.senderProfileImageUrl,
      category: 'avatars',
      fileName: 'sender_${message.senderId ?? message.id}',
    );

    return {
      'id': message.id,
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
      'created_at_ms': message.createdAt?.millisecondsSinceEpoch,
    };
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
    AppConversationMessage message,
  ) async {
    final fileUrl = message.fileUrl?.trim();
    final type = message.type.trim().toLowerCase();
    if (fileUrl == null ||
        fileUrl.isEmpty ||
        !fileUrl.startsWith('http') ||
        !(type == 'foto' || type == 'gorsel' || type == 'ses')) {
      return null;
    }

    final category = type == 'ses' ? 'audio' : 'media';
    return _cacheRemoteFileIfNeeded(
      fileUrl,
      category: category,
      fileName: '${message.conversationId}_${message.id}',
    );
  }

  String? _messagePreviewText(String? messageType, String? messageText) {
    final normalizedText = messageText?.trim();
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
    required String category,
    required String fileName,
  }) async {
    final fileUrl = rawUrl?.trim();
    if (fileUrl == null || fileUrl.isEmpty || !fileUrl.startsWith('http')) {
      return null;
    }

    try {
      final databasesPath = await getDatabasesPath();
      final cacheDir = Directory(
        path.join(databasesPath, 'chat_binary_cache', category),
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

  Future<void> cleanupStaleCache() async {
    try {
      final databasesPath = await getDatabasesPath();
      final rootDir = Directory(path.join(databasesPath, 'chat_binary_cache'));
      if (!await rootDir.exists()) {
        return;
      }

      final referencedPaths = <String>{};
      final db = await _db;
      final messageRows = await db.query(
        'conversation_messages',
        columns: ['cached_file_path', 'cached_sender_profile_image_path'],
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
}
