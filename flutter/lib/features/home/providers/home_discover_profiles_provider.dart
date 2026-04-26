import 'package:magmug/app_core.dart';

final homeDiscoverProfilesProvider = FutureProvider<List<AppMatchCandidate>>((
  ref,
) async {
  final session = await ref.watch(appAuthProvider.future);
  final token = session?.token;
  if (token == null || token.trim().isEmpty) {
    return const [];
  }

  try {
    final bootstrap = await AppBootstrapCoordinator.instance.bootstrap(token);
    return bootstrap.discoverProfiles.take(4).toList(growable: false);
  } catch (_) {
    return const <AppMatchCandidate>[];
  }
});
