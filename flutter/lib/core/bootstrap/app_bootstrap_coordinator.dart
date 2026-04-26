import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:magmug/core/i18n/app_runtime_text.dart';
import 'package:magmug/core/models/auth_models.dart';
import 'package:magmug/core/models/communication_models.dart';
import 'package:magmug/core/models/match_models.dart';
import 'package:magmug/core/models/public_settings_models.dart';
import 'package:magmug/core/models/user_models.dart';
import 'package:magmug/core/network/app_auth_api.dart';
import 'package:magmug/core/storage/app_storage.dart';
import 'package:magmug/core/sync/app_sync_engine.dart';
import 'package:magmug/features/chat/chat_local_store.dart';

@immutable
class AppBootstrapSnapshot {
  final String token;
  final String? syncToken;
  final AppUser user;
  final AppPublicSettings publicSettings;
  final AppMatchCenterSummary matchSummary;
  final List<AppConversationPreview> conversations;
  final List<AppMatchCandidate> discoverProfiles;
  final int unreadNotificationCount;

  const AppBootstrapSnapshot({
    required this.token,
    required this.syncToken,
    required this.user,
    required this.publicSettings,
    required this.matchSummary,
    required this.conversations,
    required this.discoverProfiles,
    required this.unreadNotificationCount,
  });

  AppAuthState get authState => AppAuthState(token: token, user: user);
}

class AppBootstrapCoordinator {
  AppBootstrapCoordinator._();

  static final AppBootstrapCoordinator instance = AppBootstrapCoordinator._();

  final Map<String, Future<AppBootstrapSnapshot>> _inFlightByToken =
      <String, Future<AppBootstrapSnapshot>>{};

  Future<AppBootstrapSnapshot> bootstrap(String token) {
    final normalizedToken = token.trim();
    if (normalizedToken.isEmpty) {
      throw ApiException(
        AppRuntimeText.instance.t(
          'apiErrorActiveSessionMissing',
          'Aktif oturum bulunamadi.',
        ),
      );
    }

    final existing = _inFlightByToken[normalizedToken];
    if (existing != null) {
      return existing;
    }

    final future = _bootstrap(normalizedToken);
    _inFlightByToken[normalizedToken] = future;
    unawaited(
      future.whenComplete(() {
        if (identical(_inFlightByToken[normalizedToken], future)) {
          _inFlightByToken.remove(normalizedToken);
        }
      }),
    );
    return future;
  }

  Future<AppBootstrapSnapshot> _bootstrap(String token) async {
    final api = AppAuthApi();
    try {
      final payload = await api.fetchMobileBootstrap(token);
      final session = AuthenticatedSession(token: token, user: payload.user);
      await AppSessionStorage.saveSession(session);
      await AppSessionStorage.saveMobileSyncToken(payload.syncToken);
      await ChatLocalStore.instance.upsertConversationPreviews(
        payload.conversations,
        ownerUserId: payload.user.id,
      );
      unawaited(
        AppSyncEngine.instance.flushOutbox(
          token: token,
          ownerUserId: payload.user.id,
        ),
      );

      return AppBootstrapSnapshot(
        token: token,
        syncToken: payload.syncToken,
        user: payload.user,
        publicSettings: payload.publicSettings,
        matchSummary: payload.matchSummary,
        conversations: payload.conversations,
        discoverProfiles: payload.discoverProfiles,
        unreadNotificationCount: payload.unreadNotificationCount,
      );
    } finally {
      api.close();
    }
  }
}
