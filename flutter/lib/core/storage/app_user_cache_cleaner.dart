import 'dart:io';

import 'package:flutter_cache_manager/flutter_cache_manager.dart';
import 'package:magmug/core/storage/app_storage.dart';
import 'package:path/path.dart' as path;
import 'package:path_provider/path_provider.dart';
import 'package:sqflite/sqflite.dart';

class AppUserCacheCleaner {
  AppUserCacheCleaner._();

  static Future<void> clearUserScopedData(int? ownerUserId) async {
    if (ownerUserId == null || ownerUserId <= 0) {
      return;
    }

    await _clearChatDatabase(ownerUserId);
    await _clearChatBinaryCache(ownerUserId);
    await _clearPendingMedia(ownerUserId);

    try {
      await DefaultCacheManager().emptyCache();
    } catch (_) {}
  }

  static Future<void> _clearChatDatabase(int ownerUserId) async {
    try {
      final databasesPath = await getDatabasesPath();
      final dbPath = path.join(databasesPath, 'magmug_chat_cache.db');
      if (!await File(dbPath).exists()) {
        return;
      }

      final db = await openDatabase(dbPath);
      try {
        for (final table in const [
          'conversation_messages',
          'conversation_previews',
          'message_outbox',
        ]) {
          await db.delete(
            table,
            where: 'owner_user_id = ?',
            whereArgs: [ownerUserId],
          );
        }
      } finally {
        await db.close();
      }
    } catch (_) {}
  }

  static Future<void> _clearChatBinaryCache(int ownerUserId) async {
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
  }

  static Future<void> _clearPendingMedia(int ownerUserId) async {
    try {
      final namespace = await AppSessionStorage.cacheNamespaceForUser(
        ownerUserId,
      );
      final supportDir = await getApplicationSupportDirectory();
      final rootDir = Directory(
        path.join(supportDir.path, 'pending_media', namespace),
      );
      if (await rootDir.exists()) {
        await rootDir.delete(recursive: true);
      }
    } catch (_) {}
  }
}
