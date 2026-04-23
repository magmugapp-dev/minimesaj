import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:magmug/core/models/auth_models.dart';
import 'package:magmug/core/models/user_models.dart';
import 'package:magmug/core/network/app_auth_api.dart';
import 'package:magmug/core/storage/app_storage.dart';

class AppAuthController extends AsyncNotifier<AppAuthState?> {
  late final AppAuthApi _authApi = AppAuthApi();

  @override
  Future<AppAuthState?> build() async {
    final savedSession = await AppSessionStorage.readSession();
    if (savedSession == null) {
      return null;
    }

    try {
      final user = await _authApi.fetchCurrentUser(savedSession.token);
      final next = AppAuthState(token: savedSession.token, user: user);
      await AppSessionStorage.saveSession(next.toSession());
      return next;
    } on UnauthorizedApiException {
      await AppSessionStorage.clear();
      return null;
    } on SocketException {
      return AppAuthState(token: savedSession.token, user: savedSession.user);
    } on HandshakeException {
      return AppAuthState(token: savedSession.token, user: savedSession.user);
    } catch (_) {
      return AppAuthState(token: savedSession.token, user: savedSession.user);
    }
  }

  Future<void> setAuthenticatedSession(AuthenticatedSession session) async {
    final next = AppAuthState(token: session.token, user: session.user);
    state = AsyncData(next);
    await AppSessionStorage.saveSession(session);
  }

  Future<void> updateProfile({
    required String firstName,
    String? surname,
    String? bio,
  }) async {
    final current = state.asData?.value;
    if (current == null || current.token.trim().isEmpty) {
      throw const ApiException('Aktif oturum bulunamadi.');
    }

    final user = await _authApi.updateProfile(
      current.token,
      firstName: firstName,
      surname: surname,
      bio: bio,
    );
    final next = AppAuthState(token: current.token, user: user);
    state = AsyncData(next);
    await AppSessionStorage.saveSession(next.toSession());
  }

  Future<void> updatePreferredLanguage(AppLanguage language) async {
    final current = state.asData?.value;
    if (current == null || current.token.trim().isEmpty) {
      throw const ApiException('Aktif oturum bulunamadi.');
    }

    final user = await _authApi.updateProfile(
      current.token,
      languageCode: language.code,
    );
    final next = AppAuthState(token: current.token, user: user);
    state = AsyncData(next);
    await AppSessionStorage.saveSession(next.toSession());
  }

  Future<void> updateNotificationPreferences({
    required bool notificationsEnabled,
    required bool vibrationEnabled,
  }) async {
    final current = state.asData?.value;
    if (current == null || current.token.trim().isEmpty) {
      throw const ApiException('Aktif oturum bulunamadi.');
    }

    final user = await _authApi.updateNotificationPreferences(
      current.token,
      notificationsEnabled: notificationsEnabled,
      vibrationEnabled: vibrationEnabled,
    );
    final next = AppAuthState(token: current.token, user: user);
    state = AsyncData(next);
    await AppSessionStorage.saveSession(next.toSession());
  }

  Future<void> refreshCurrentUser() async {
    final current = state.asData?.value;
    if (current == null) return;

    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      final user = await _authApi.fetchCurrentUser(current.token);
      final next = AppAuthState(token: current.token, user: user);
      await AppSessionStorage.saveSession(next.toSession());
      return next;
    });
  }

  Future<void> setGemBalance(int gemBalance) async {
    final current = state.asData?.value;
    final user = current?.user;
    if (current == null || user == null) {
      return;
    }

    final next = AppAuthState(
      token: current.token,
      user: user.copyWith(gemBalance: gemBalance),
    );
    state = AsyncData(next);
    await AppSessionStorage.saveSession(next.toSession());
  }

  Future<void> signOut() async {
    final current = state.asData?.value;

    if (current != null && current.token.trim().isNotEmpty) {
      try {
        await _authApi.logout(current.token);
      } on UnauthorizedApiException {
      } on SocketException {
      } on HandshakeException {
      } on ApiException {}
    }

    await AppSessionStorage.clear();
    state = const AsyncData(null);
  }

  Future<void> deleteAccount() async {
    final current = state.asData?.value;
    if (current == null || current.token.trim().isEmpty) {
      throw const ApiException('Aktif oturum bulunamadi.');
    }

    try {
      await _authApi.deleteAccount(current.token);
    } on UnauthorizedApiException {}

    await AppSessionStorage.clear();
    state = const AsyncData(null);
  }
}

class AppLanguageController extends AsyncNotifier<AppLanguage> {
  @override
  Future<AppLanguage> build() {
    return AppPreferencesStorage.readAppLanguage();
  }

  Future<void> setLanguage(AppLanguage language) async {
    final current = state.asData?.value;
    if (current == language) {
      return;
    }

    final previous = current ?? await build();
    state = AsyncData(language);
    await AppPreferencesStorage.saveAppLanguage(language);

    final authState = ref.read(appAuthProvider).asData?.value;
    if (authState == null) {
      return;
    }

    try {
      await ref
          .read(appAuthProvider.notifier)
          .updatePreferredLanguage(language);
    } catch (_) {
      state = AsyncData(previous);
      await AppPreferencesStorage.saveAppLanguage(previous);
      rethrow;
    }
  }
}

final appLanguageProvider =
    AsyncNotifierProvider<AppLanguageController, AppLanguage>(
      AppLanguageController.new,
    );

final appAuthProvider = AsyncNotifierProvider<AppAuthController, AppAuthState?>(
  AppAuthController.new,
);
