typedef AppRuntimeCacheCleaner = void Function();

class AppRuntimeCacheRegistry {
  AppRuntimeCacheRegistry._();

  static final List<AppRuntimeCacheCleaner> _cleaners =
      <AppRuntimeCacheCleaner>[];

  static void register(AppRuntimeCacheCleaner cleaner) {
    if (_cleaners.contains(cleaner)) {
      return;
    }
    _cleaners.add(cleaner);
  }

  static void clearUserScopedCaches() {
    for (final cleaner in List<AppRuntimeCacheCleaner>.from(_cleaners)) {
      try {
        cleaner();
      } catch (_) {}
    }
  }
}
