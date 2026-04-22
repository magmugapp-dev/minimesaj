import 'dart:convert';

import 'package:flutter/cupertino.dart';
import 'package:magmug/core/models/auth_models.dart';
import 'package:magmug/core/models/user_models.dart';
import 'package:shared_preferences/shared_preferences.dart';

class AppSessionStorage {
  AppSessionStorage._();

  static const String _tokenKey = 'auth.token';
  static const String _userKey = 'auth.user';

  static Future<void> saveSession(AuthenticatedSession session) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, session.token);
    if (session.user != null) {
      await prefs.setString(_userKey, jsonEncode(session.user!.toJson()));
    } else {
      await prefs.remove(_userKey);
    }
  }

  static Future<AuthenticatedSession?> readSession() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_tokenKey);
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
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_tokenKey);
  }

  static Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_userKey);
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
