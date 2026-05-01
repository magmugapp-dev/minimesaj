import 'dart:convert';
import 'dart:io' show Platform;
import 'dart:math';

import 'package:flutter/cupertino.dart';
import 'package:crypto/crypto.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:hive/hive.dart';
import 'package:magmug/core/models/app_content_models.dart';
import 'package:magmug/core/models/auth_models.dart';
import 'package:magmug/core/models/user_models.dart';

class AppHiveBoxes {
  AppHiveBoxes._();

  static Future<Box<dynamic>> session() => Hive.openBox<dynamic>('app_session');
  static Future<Box<dynamic>> preferences() =>
      Hive.openBox<dynamic>('app_preferences');
  static Future<Box<dynamic>> content() => Hive.openBox<dynamic>('app_content');
  static Future<Box<dynamic>> publicSettings() =>
      Hive.openBox<dynamic>('app_public_settings');
  static Future<Box<dynamic>> notifications() =>
      Hive.openBox<dynamic>('app_notifications');
  static Future<Box<dynamic>> discoverProfiles() =>
      Hive.openBox<dynamic>('discover_profiles');
  static Future<Box<dynamic>> peerProfiles() =>
      Hive.openBox<dynamic>('peer_profiles');
  static Future<Box<dynamic>> aiPrompt() => Hive.openBox<dynamic>('ai_prompt');
  static Future<Box<dynamic>> aiCharacters() =>
      Hive.openBox<dynamic>('ai_characters');
  static Future<Box<dynamic>> aiMemory() => Hive.openBox<dynamic>('ai_memory');
  static Future<Box<dynamic>> pendingAiTurns() =>
      Hive.openBox<dynamic>('ai_pending_turns');
}

class AppSessionStorage {
  AppSessionStorage._();

  static const FlutterSecureStorage _secureStorage = FlutterSecureStorage();
  static const String _secureTokenKey = 'auth.token.secure';
  static const String _legacyTokenKey = 'auth.token';
  static const String _userKey = 'auth.user';
  static const String _ownerUserIdKey = 'auth.owner_user_id';
  static const String _installSaltKey = 'app.install_salt';
  static const String _mobileSyncTokenKey = 'mobile.sync_token';
  static const String _mobileLastSyncAtPrefix = 'mobile.last_sync_at.';
  static const String _matchSummaryCachePrefix = 'match.summary.';

  static Future<void> saveSession(AuthenticatedSession session) async {
    final box = await AppHiveBoxes.session();
    await _secureStorage.write(key: _secureTokenKey, value: session.token);
    await box.delete(_legacyTokenKey);
    if (session.user != null) {
      await box.put(_userKey, jsonEncode(session.user!.toJson()));
      await box.put(_ownerUserIdKey, session.user!.id);
    } else {
      await box.delete(_userKey);
      await box.delete(_ownerUserIdKey);
    }
  }

  static Future<AuthenticatedSession?> readSession() async {
    final box = await AppHiveBoxes.session();
    var token = await _secureStorage.read(key: _secureTokenKey);
    final legacyToken = box.get(_legacyTokenKey)?.toString();
    if ((token == null || token.trim().isEmpty) &&
        legacyToken != null &&
        legacyToken.trim().isNotEmpty) {
      token = legacyToken;
      await _secureStorage.write(key: _secureTokenKey, value: legacyToken);
      await box.delete(_legacyTokenKey);
    }
    if (token == null || token.trim().isEmpty) {
      return null;
    }

    final rawUser = box.get(_userKey)?.toString();
    AppUser? user;
    if (rawUser != null && rawUser.trim().isNotEmpty) {
      final decoded = jsonDecode(rawUser);
      if (decoded is Map<String, dynamic>) {
        user = AppUser.fromJson(decoded);
      } else if (decoded is Map) {
        user = AppUser.fromJson(
          decoded.map((key, value) => MapEntry(key.toString(), value)),
        );
      }
    }

    return AuthenticatedSession(token: token, user: user);
  }

  static Future<String?> readToken() async {
    return _secureStorage.read(key: _secureTokenKey);
  }

  static Future<int?> readOwnerUserId() async {
    final box = await AppHiveBoxes.session();
    final value = box.get(_ownerUserIdKey);
    return value is num ? value.toInt() : null;
  }

  static Future<void> saveMobileSyncToken(String? syncToken) async {
    final box = await AppHiveBoxes.session();
    final normalized = syncToken?.trim();
    if (normalized == null || normalized.isEmpty) {
      await box.delete(_mobileSyncTokenKey);
      return;
    }

    await box.put(_mobileSyncTokenKey, normalized);
  }

  static Future<String?> readMobileSyncToken() async {
    final box = await AppHiveBoxes.session();
    return box.get(_mobileSyncTokenKey)?.toString();
  }

  static Future<void> saveMobileLastSyncAt(
    int ownerUserId,
    DateTime value,
  ) async {
    if (ownerUserId <= 0) {
      return;
    }

    final box = await AppHiveBoxes.session();
    await box.put(
      '$_mobileLastSyncAtPrefix$ownerUserId',
      value.millisecondsSinceEpoch,
    );
  }

  static Future<DateTime?> readMobileLastSyncAt(int ownerUserId) async {
    if (ownerUserId <= 0) {
      return null;
    }

    final box = await AppHiveBoxes.session();
    final millis = box.get('$_mobileLastSyncAtPrefix$ownerUserId');
    if (millis is! num) {
      return null;
    }
    if (millis <= 0) {
      return null;
    }

    return DateTime.fromMillisecondsSinceEpoch(millis.toInt());
  }

  static Future<String> cacheNamespaceForUser(int userId) async {
    final box = await AppHiveBoxes.session();
    final salt = await _installSalt(box);

    return sha256.convert(utf8.encode('$salt:$userId')).toString();
  }

  static Future<Map<String, dynamic>?> readFreshMatchSummaryCache(
    int ownerUserId,
    Duration ttl,
  ) async {
    if (ownerUserId <= 0) {
      return null;
    }

    final box = await AppHiveBoxes.session();
    final raw = box.get('$_matchSummaryCachePrefix$ownerUserId')?.toString();
    if (raw == null || raw.trim().isEmpty) {
      return null;
    }

    try {
      final decoded = jsonDecode(raw);
      final wrapper = decoded is Map<String, dynamic>
          ? decoded
          : decoded is Map
          ? decoded.map((key, value) => MapEntry(key.toString(), value))
          : null;
      final cachedAt = wrapper?['cached_at'];
      final data = wrapper?['data'];
      if (cachedAt is! num || data is! Map) {
        await box.delete('$_matchSummaryCachePrefix$ownerUserId');
        return null;
      }

      final age = DateTime.now().difference(
        DateTime.fromMillisecondsSinceEpoch(cachedAt.toInt()),
      );
      if (age > ttl) {
        return null;
      }

      return data.map((key, value) => MapEntry(key.toString(), value));
    } catch (_) {
      await box.delete('$_matchSummaryCachePrefix$ownerUserId');
      return null;
    }
  }

  static Future<void> saveMatchSummaryCache(
    int ownerUserId,
    Map<String, dynamic> data,
  ) async {
    if (ownerUserId <= 0) {
      return;
    }

    final box = await AppHiveBoxes.session();
    await box.put(
      '$_matchSummaryCachePrefix$ownerUserId',
      jsonEncode({
        'cached_at': DateTime.now().millisecondsSinceEpoch,
        'data': data,
      }),
    );
  }

  static Future<void> clearMatchSummaryCache({int? ownerUserId}) async {
    final box = await AppHiveBoxes.session();
    if (ownerUserId != null && ownerUserId > 0) {
      await box.delete('$_matchSummaryCachePrefix$ownerUserId');
      return;
    }

    final keys = box.keys
        .map((key) => key.toString())
        .where((key) => key.startsWith(_matchSummaryCachePrefix))
        .toList(growable: false);
    for (final key in keys) {
      await box.delete(key);
    }
  }

  static Future<String> deviceFingerprint() async {
    final box = await AppHiveBoxes.session();
    final deviceId = await _deviceIdentifier();
    if (deviceId != null && deviceId.trim().isNotEmpty) {
      return sha256
          .convert(
            utf8.encode(
              'device:${Platform.operatingSystem}:${deviceId.trim()}',
            ),
          )
          .toString();
    }

    final salt = await _installSalt(box);
    return sha256
        .convert(utf8.encode('device:${Platform.operatingSystem}:$salt'))
        .toString();
  }

  static String devicePlatform() => Platform.operatingSystem;

  static Future<String?> _deviceIdentifier() async {
    try {
      final plugin = DeviceInfoPlugin();
      final data = Platform.isAndroid
          ? (await plugin.androidInfo).data
          : Platform.isIOS
          ? (await plugin.iosInfo).data
          : Platform.isMacOS
          ? (await plugin.macOsInfo).data
          : Platform.isWindows
          ? (await plugin.windowsInfo).data
          : Platform.isLinux
          ? (await plugin.linuxInfo).data
          : const <String, dynamic>{};

      return _firstDeviceInfoValue(data, [
        'androidId',
        'android_id',
        'identifierForVendor',
        'computerName',
        'machineId',
        'deviceId',
        'id',
      ]);
    } catch (_) {
      return null;
    }
  }

  static String? _firstDeviceInfoValue(
    Map<String, dynamic> data,
    List<String> keys,
  ) {
    for (final key in keys) {
      final value = data[key]?.toString().trim();
      if (value != null && value.isNotEmpty && value != 'unknown') {
        return value;
      }
    }

    return null;
  }

  static Future<String> _installSalt(Box<dynamic> box) async {
    var salt = box.get(_installSaltKey)?.toString();
    if (salt == null || salt.trim().isEmpty) {
      final random = Random.secure();
      final bytes = List<int>.generate(32, (_) => random.nextInt(256));
      salt = base64UrlEncode(bytes);
      await box.put(_installSaltKey, salt);
    }

    return salt;
  }

  static Future<void> clear() async {
    final box = await AppHiveBoxes.session();
    await _secureStorage.delete(key: _secureTokenKey);
    await box.delete(_legacyTokenKey);
    await box.delete(_userKey);
    await box.delete(_ownerUserIdKey);
    await box.delete(_mobileSyncTokenKey);
    final lastSyncKeys = box.keys
        .map((key) => key.toString())
        .where((key) => key.startsWith(_mobileLastSyncAtPrefix))
        .toList(growable: false);
    for (final key in lastSyncKeys) {
      await box.delete(key);
    }
    await clearMatchSummaryCache();
  }
}

class AppPreferencesStorage {
  AppPreferencesStorage._();

  static const String _languageKey = 'app.language';

  static AppLanguage fallbackLanguage() {
    final systemLocale = WidgetsBinding.instance.platformDispatcher.locale;
    return appLanguageFromCode(systemLocale.languageCode);
  }

  static Future<AppLanguage> readAppLanguage() async {
    final box = await AppHiveBoxes.preferences();
    final rawCode = box.get(_languageKey)?.toString();
    if (rawCode != null && rawCode.trim().isNotEmpty) {
      return appLanguageFromCode(rawCode);
    }

    final session = await AppSessionStorage.readSession();
    final sessionLanguage = session?.user?.languageCode;
    if (sessionLanguage != null && sessionLanguage.trim().isNotEmpty) {
      return appLanguageFromCode(sessionLanguage);
    }

    return fallbackLanguage();
  }

  static Future<void> saveAppLanguage(AppLanguage language) async {
    final box = await AppHiveBoxes.preferences();
    await box.put(_languageKey, language.code);
  }
}

class AppContentStorage {
  AppContentStorage._();

  static const String _contentKeyPrefix = 'app.content.';
  static const String _lastLanguageKey = 'app.content.last_language';

  static Future<AppContent?> read(String languageCode) async {
    final box = await AppHiveBoxes.content();
    final raw = box.get(_contentKey(languageCode))?.toString();
    if (raw == null || raw.trim().isEmpty) {
      return null;
    }

    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map<String, dynamic>) {
        return AppContent.fromJson(decoded).copyWith(fromCache: true);
      }
      if (decoded is Map) {
        return AppContent.fromJson(
          decoded.map((key, value) => MapEntry(key.toString(), value)),
        ).copyWith(fromCache: true);
      }
    } catch (_) {
      await box.delete(_contentKey(languageCode));
    }

    return null;
  }

  static Future<AppContent?> readLast() async {
    final box = await AppHiveBoxes.content();
    final lastLanguage = box.get(_lastLanguageKey)?.toString();
    if (lastLanguage != null && lastLanguage.trim().isNotEmpty) {
      final lastContent = await read(lastLanguage);
      if (lastContent != null) {
        return lastContent;
      }
    }

    for (final rawKey in box.keys) {
      final key = rawKey.toString();
      if (!key.startsWith(_contentKeyPrefix) || key == _lastLanguageKey) {
        continue;
      }

      final languageCode = key.substring(_contentKeyPrefix.length);
      final content = await read(languageCode);
      if (content != null) {
        return content;
      }
    }

    return null;
  }

  static Future<void> save(AppContent content) async {
    final box = await AppHiveBoxes.content();
    await box.put(
      _contentKey(content.selectedLanguageCode),
      jsonEncode(content.toJson()),
    );
    await box.put(_lastLanguageKey, content.selectedLanguageCode);
  }

  static String _contentKey(String languageCode) {
    return '$_contentKeyPrefix${languageCode.trim().toLowerCase()}';
  }
}
