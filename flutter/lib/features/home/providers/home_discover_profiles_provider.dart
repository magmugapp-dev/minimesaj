import 'package:magmug/app_core.dart';

final homeDiscoverProfilesProvider =
    FutureProvider.autoDispose<List<AppMatchCandidate>>((ref) async {
      final session = await ref.watch(appAuthProvider.future);
      final token = session?.token;
      if (token == null || token.trim().isEmpty) {
        return const [];
      }

      final api = AppAuthApi();
      try {
        return api.fetchDiscoverProfiles(token, limit: 4);
      } finally {
        api.close();
      }
    });
