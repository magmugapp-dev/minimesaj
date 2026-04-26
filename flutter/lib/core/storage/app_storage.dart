import 'dart:convert';
import 'dart:math';

import 'package:flutter/cupertino.dart';
import 'package:crypto/crypto.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:magmug/core/models/app_content_models.dart';
import 'package:magmug/core/models/auth_models.dart';
import 'package:magmug/core/models/user_models.dart';
import 'package:shared_preferences/shared_preferences.dart';

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

  static Future<void> saveSession(AuthenticatedSession session) async {
    final prefs = await SharedPreferences.getInstance();
    await _secureStorage.write(key: _secureTokenKey, value: session.token);
    await prefs.remove(_legacyTokenKey);
    if (session.user != null) {
      await prefs.setString(_userKey, jsonEncode(session.user!.toJson()));
      await prefs.setInt(_ownerUserIdKey, session.user!.id);
    } else {
      await prefs.remove(_userKey);
      await prefs.remove(_ownerUserIdKey);
    }
  }

  static Future<AuthenticatedSession?> readSession() async {
    final prefs = await SharedPreferences.getInstance();
    var token = await _secureStorage.read(key: _secureTokenKey);
    final legacyToken = prefs.getString(_legacyTokenKey);
    if ((token == null || token.trim().isEmpty) &&
        legacyToken != null &&
        legacyToken.trim().isNotEmpty) {
      token = legacyToken;
      await _secureStorage.write(key: _secureTokenKey, value: legacyToken);
      await prefs.remove(_legacyTokenKey);
    }
    if (token == null || token.trim().isEmpty) {
      return null;
    }

    final rawUser = prefs.getString(_userKey);
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
    final prefs = await SharedPreferences.getInstance();
    return prefs.getInt(_ownerUserIdKey);
  }

  static Future<void> saveMobileSyncToken(String? syncToken) async {
    final prefs = await SharedPreferences.getInstance();
    final normalized = syncToken?.trim();
    if (normalized == null || normalized.isEmpty) {
      await prefs.remove(_mobileSyncTokenKey);
      return;
    }

    await prefs.setString(_mobileSyncTokenKey, normalized);
  }

  static Future<String?> readMobileSyncToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_mobileSyncTokenKey);
  }

  static Future<void> saveMobileLastSyncAt(
    int ownerUserId,
    DateTime value,
  ) async {
    if (ownerUserId <= 0) {
      return;
    }

    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(
      '$_mobileLastSyncAtPrefix$ownerUserId',
      value.millisecondsSinceEpoch,
    );
  }

  static Future<DateTime?> readMobileLastSyncAt(int ownerUserId) async {
    if (ownerUserId <= 0) {
      return null;
    }

    final prefs = await SharedPreferences.getInstance();
    final millis = prefs.getInt('$_mobileLastSyncAtPrefix$ownerUserId');
    if (millis == null || millis <= 0) {
      return null;
    }

    return DateTime.fromMillisecondsSinceEpoch(millis);
  }

  static Future<String> cacheNamespaceForUser(int userId) async {
    final prefs = await SharedPreferences.getInstance();
    var salt = prefs.getString(_installSaltKey);
    if (salt == null || salt.trim().isEmpty) {
      final random = Random.secure();
      final bytes = List<int>.generate(32, (_) => random.nextInt(256));
      salt = base64UrlEncode(bytes);
      await prefs.setString(_installSaltKey, salt);
    }

    return sha256.convert(utf8.encode('$salt:$userId')).toString();
  }

  static Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await _secureStorage.delete(key: _secureTokenKey);
    await prefs.remove(_legacyTokenKey);
    await prefs.remove(_userKey);
    await prefs.remove(_ownerUserIdKey);
    await prefs.remove(_mobileSyncTokenKey);
    final lastSyncKeys = prefs
        .getKeys()
        .where((key) => key.startsWith(_mobileLastSyncAtPrefix))
        .toList(growable: false);
    for (final key in lastSyncKeys) {
      await prefs.remove(key);
    }
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
    final prefs = await SharedPreferences.getInstance();
    final rawCode = prefs.getString(_languageKey);
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
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_languageKey, language.code);
  }
}

class AppContentStorage {
  AppContentStorage._();

  static const String _contentKeyPrefix = 'app.content.';
  static const String _lastLanguageKey = 'app.content.last_language';

  static Future<AppContent?> read(String languageCode) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_contentKey(languageCode));
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
      await prefs.remove(_contentKey(languageCode));
    }

    return null;
  }

  static Future<AppContent?> readLast() async {
    final prefs = await SharedPreferences.getInstance();
    final lastLanguage = prefs.getString(_lastLanguageKey);
    if (lastLanguage != null && lastLanguage.trim().isNotEmpty) {
      final lastContent = await read(lastLanguage);
      if (lastContent != null) {
        return lastContent;
      }
    }

    for (final key in prefs.getKeys()) {
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
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _contentKey(content.selectedLanguageCode),
      jsonEncode(content.toJson()),
    );
    await prefs.setString(_lastLanguageKey, content.selectedLanguageCode);
  }

  static String _contentKey(String languageCode) {
    return '$_contentKeyPrefix${languageCode.trim().toLowerCase()}';
  }
}
