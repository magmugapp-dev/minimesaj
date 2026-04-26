import 'dart:async';

import 'package:magmug/core/storage/app_storage.dart';
import 'package:magmug/core/sync/app_sync_engine.dart';

class AppCacheSyncCoordinator {
  AppCacheSyncCoordinator._();

  static final AppCacheSyncCoordinator instance = AppCacheSyncCoordinator._();
  static const Duration defaultStaleWindow = Duration(minutes: 2);

  final Set<int> _syncInFlight = <int>{};
  Timer? _debounceTimer;
  String? _debouncedToken;
  int? _debouncedOwnerUserId;
  bool _debouncedForce = false;
  void Function(bool didSync)? _debouncedOnComplete;

  Future<bool> reconcile({
    required String token,
    required int ownerUserId,
    bool force = false,
    Duration staleWindow = defaultStaleWindow,
  }) async {
    final normalizedToken = token.trim();
    if (normalizedToken.isEmpty || ownerUserId <= 0) {
      return false;
    }

    if (!force) {
      final lastSync = await AppSessionStorage.readMobileLastSyncAt(
        ownerUserId,
      );
      if (lastSync != null &&
          DateTime.now().difference(lastSync) < staleWindow) {
        return false;
      }
    }

    if (_syncInFlight.contains(ownerUserId)) {
      return false;
    }

    _syncInFlight.add(ownerUserId);
    try {
      await AppSyncEngine.instance.syncDelta(
        token: normalizedToken,
        ownerUserId: ownerUserId,
      );
      await AppSessionStorage.saveMobileLastSyncAt(ownerUserId, DateTime.now());
      return true;
    } finally {
      _syncInFlight.remove(ownerUserId);
    }
  }

  void scheduleDebounced({
    required String token,
    required int ownerUserId,
    bool force = false,
    Duration delay = const Duration(seconds: 2),
    void Function(bool didSync)? onComplete,
  }) {
    final normalizedToken = token.trim();
    if (normalizedToken.isEmpty || ownerUserId <= 0) {
      return;
    }

    _debouncedToken = normalizedToken;
    _debouncedOwnerUserId = ownerUserId;
    _debouncedForce = _debouncedForce || force;
    _debouncedOnComplete = onComplete ?? _debouncedOnComplete;
    _debounceTimer?.cancel();
    _debounceTimer = Timer(delay, () {
      final nextToken = _debouncedToken;
      final nextOwnerUserId = _debouncedOwnerUserId;
      final nextForce = _debouncedForce;
      final nextOnComplete = _debouncedOnComplete;
      _debouncedToken = null;
      _debouncedOwnerUserId = null;
      _debouncedForce = false;
      _debouncedOnComplete = null;
      if (nextToken == null || nextOwnerUserId == null) {
        return;
      }

      unawaited(
        reconcile(
              token: nextToken,
              ownerUserId: nextOwnerUserId,
              force: nextForce,
            )
            .then((didSync) => nextOnComplete?.call(didSync))
            .catchError((_) => nextOnComplete?.call(false)),
      );
    });
  }
}
