import 'dart:io';

import 'package:flutter_cache_manager/flutter_cache_manager.dart';
import 'package:magmug/core/storage/app_storage.dart';
import 'package:magmug/features/chat/chat_local_store.dart';
import 'package:path/path.dart' as path;
import 'package:path_provider/path_provider.dart';

class AppUserCacheCleaner {
  AppUserCacheCleaner._();

  static Future<void> clearUserScopedData(int? ownerUserId) async {
    if (ownerUserId == null || ownerUserId <= 0) {
      return;
    }

    await ChatLocalStore.instance.clearUserData(ownerUserId);
    await _clearChatBinaryCache(ownerUserId);
    await _clearPendingMedia(ownerUserId);

    try {
      await DefaultCacheManager().emptyCache();
    } catch (_) {}
  }

  static Future<void> _clearChatBinaryCache(int ownerUserId) async {
    try {
      final supportDir = await getApplicationSupportDirectory();
      final namespace = await AppSessionStorage.cacheNamespaceForUser(
        ownerUserId,
      );
      final rootDir = Directory(
        path.join(supportDir.path, 'chat_binary_cache', namespace),
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
