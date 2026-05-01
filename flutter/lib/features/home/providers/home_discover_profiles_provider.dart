import 'package:magmug/app_core.dart';

List<AppMatchCandidate> _cachedDiscoverProfiles = const <AppMatchCandidate>[];
DateTime? _cachedDiscoverProfilesAt;

final homeDiscoverProfilesProvider = FutureProvider<List<AppMatchCandidate>>((
  ref,
) async {
  final session = await ref.watch(appAuthProvider.future);
  final token = session?.token;
  if (token == null || token.trim().isEmpty) {
    return const [];
  }

  final cachedAt = _cachedDiscoverProfilesAt;
  if (cachedAt != null &&
      DateTime.now().difference(cachedAt) < const Duration(minutes: 5) &&
      _cachedDiscoverProfiles.isNotEmpty) {
    return _cachedDiscoverProfiles;
  }

  final api = AppAuthApi();
  try {
    final profiles = await api.fetchDiscoverProfiles(token, limit: 4);
    _cachedDiscoverProfiles = profiles;
    _cachedDiscoverProfilesAt = DateTime.now();
    return profiles;
  } catch (_) {
    return _cachedDiscoverProfiles;
  } finally {
    api.close();
  }
});
