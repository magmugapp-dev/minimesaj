import 'dart:async';
import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:magmug/core/cache/app_runtime_cache_registry.dart';
import 'package:magmug/core/i18n/app_runtime_text.dart';
import 'package:magmug/core/models/auth_models.dart';
import 'package:magmug/core/models/user_models.dart';
import 'package:magmug/core/bootstrap/app_bootstrap_coordinator.dart';
import 'package:magmug/core/network/app_auth_api.dart';
import 'package:magmug/core/repository/app_repository.dart';
import 'package:magmug/core/storage/app_storage.dart';
import 'package:magmug/core/storage/app_user_cache_cleaner.dart';
import 'package:magmug/core/ai/flutter_ai_turn_processor.dart';
import 'package:magmug/core/sync/app_cache_sync_coordinator.dart';

class AppAuthController extends AsyncNotifier<AppAuthState?> {
  late final AppAuthApi _authApi = AppAuthApi();
  bool _bootstrapRefreshInFlight = false;

  @override
  Future<AppAuthState?> build() async {
    final savedSession = await AppSessionStorage.readSession();
    if (savedSession == null) {
      return null;
    }

    final cachedUserId = savedSession.user?.id;
    if (cachedUserId == null) {
      unawaited(
        _refreshFromBootstrap(
          savedSession.token,
          previousUserId: savedSession.user?.id,
        ),
      );
    } else {
      unawaited(
        AppCacheSyncCoordinator.instance.reconcile(
          token: savedSession.token,
          ownerUserId: cachedUserId,
        ),
      );
    }

    return AppAuthState(token: savedSession.token, user: savedSession.user);
  }

  Future<void> _refreshFromBootstrap(
    String token, {
    int? previousUserId,
  }) async {
    if (_bootstrapRefreshInFlight) {
      return;
    }

    _bootstrapRefreshInFlight = true;
    try {
      final snapshot = await AppBootstrapCoordinator.instance.bootstrap(token);
      await _clearPreviousOwnerIfNeeded(previousUserId, snapshot.user.id);

      final current = state.asData?.value;
      if (current == null || current.token == token) {
        state = AsyncData(snapshot.authState);
      }
    } on UnauthorizedApiException {
      await _clearSessionAndUserCache(previousUserId);
      state = const AsyncData(null);
    } on SocketException {
      // Sicak acilista cache'deki kullanici ekranda kalir.
    } on HandshakeException {
      // TLS/sertifika sorunu varsa cache'deki oturum korunur.
    } catch (_) {
      // Bootstrap gecici olarak basarisizsa UI mevcut cache ile devam eder.
    } finally {
      _bootstrapRefreshInFlight = false;
    }
  }

  Future<void> setAuthenticatedSession(AuthenticatedSession session) async {
    final next = AppAuthState(token: session.token, user: session.user);
    final previousOwner = await AppSessionStorage.readOwnerUserId();
    await _clearPreviousOwnerIfNeeded(previousOwner, session.user?.id);
    await AppSessionStorage.saveSession(session);
    state = AsyncData(next);
    unawaited(
      _refreshFromBootstrap(session.token, previousUserId: session.user?.id),
    );
  }

  Future<void> updateProfile({
    required String firstName,
    String? surname,
    String? bio,
  }) async {
    final current = state.asData?.value;
    if (current == null || current.token.trim().isEmpty) {
      throw ApiException(
        AppRuntimeText.instance.t(
          'apiErrorActiveSessionMissing',
          'Aktif oturum bulunamadi.',
        ),
      );
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
      throw ApiException(
        AppRuntimeText.instance.t(
          'apiErrorActiveSessionMissing',
          'Aktif oturum bulunamadi.',
        ),
      );
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
    required bool messageSoundsEnabled,
  }) async {
    final current = state.asData?.value;
    if (current == null || current.token.trim().isEmpty) {
      throw ApiException(
        AppRuntimeText.instance.t(
          'apiErrorActiveSessionMissing',
          'Aktif oturum bulunamadi.',
        ),
      );
    }

    final user = await _authApi.updateNotificationPreferences(
      current.token,
      notificationsEnabled: notificationsEnabled,
      vibrationEnabled: vibrationEnabled,
      messageSoundsEnabled: messageSoundsEnabled,
    );
    final next = AppAuthState(token: current.token, user: user);
    state = AsyncData(next);
    await AppSessionStorage.saveSession(next.toSession());
  }

  Future<void> refreshCurrentUser() async {
    final current = state.asData?.value;
    if (current == null) return;

    await _refreshFromBootstrap(
      current.token,
      previousUserId: current.user?.id,
    );
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
    final ownerUserId =
        current?.user?.id ?? await AppSessionStorage.readOwnerUserId();

    if (current != null && current.token.trim().isNotEmpty) {
      try {
        await _authApi.logout(current.token);
      } on UnauthorizedApiException {
        // Oturum sunucuda zaten gecersizse yerel cikis yeterli.
      } on SocketException {
        // Ag yokken cikis yerelde tamamlanir.
      } on HandshakeException {
        // TLS/sertifika sorununda kullaniciyi uygulamada kilitleme.
      } on ApiException {
        // Sunucu cikis hatasi yerel oturumu temizlemeyi engellemez.
      }
    }

    await AppSessionStorage.clear();
    await AppUserCacheCleaner.clearUserScopedData(ownerUserId);
    AppRuntimeCacheRegistry.clearUserScopedCaches();
    FlutterAiTurnProcessor.instance.cancel();
    if (ownerUserId != null) {
      AppRepository.instance.clearUserScopedCaches(ownerUserId);
    }
    state = const AsyncData(null);
  }

  Future<void> deleteAccount() async {
    final current = state.asData?.value;
    if (current == null || current.token.trim().isEmpty) {
      throw ApiException(
        AppRuntimeText.instance.t(
          'apiErrorActiveSessionMissing',
          'Aktif oturum bulunamadi.',
        ),
      );
    }

    try {
      await _authApi.deleteAccount(current.token);
    } on UnauthorizedApiException {
      // Oturum gecersizse yerel temizleme yine uygulanir.
    }

    await AppSessionStorage.clear();
    await AppUserCacheCleaner.clearUserScopedData(current.user?.id);
    AppRuntimeCacheRegistry.clearUserScopedCaches();
    FlutterAiTurnProcessor.instance.cancel();
    if (current.user?.id != null) {
      AppRepository.instance.clearUserScopedCaches(current.user!.id);
    }
    state = const AsyncData(null);
  }

  Future<void> _clearPreviousOwnerIfNeeded(
    int? previousOwnerId,
    int? nextOwnerId,
  ) async {
    if (previousOwnerId == null || previousOwnerId <= 0) {
      return;
    }
    if (nextOwnerId != null && previousOwnerId == nextOwnerId) {
      return;
    }

    await AppUserCacheCleaner.clearUserScopedData(previousOwnerId);
    AppRuntimeCacheRegistry.clearUserScopedCaches();
    AppRepository.instance.clearUserScopedCaches(previousOwnerId);
  }

  Future<void> _clearSessionAndUserCache(int? ownerUserId) async {
    final resolvedOwnerId =
        ownerUserId ?? await AppSessionStorage.readOwnerUserId();
    await AppSessionStorage.clear();
    await AppUserCacheCleaner.clearUserScopedData(resolvedOwnerId);
    AppRuntimeCacheRegistry.clearUserScopedCaches();
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
