import 'dart:async';

import 'package:magmug/app_core.dart';
import 'package:magmug/features/blocked_users/data/datasources/blocked_users_remote_data_source.dart';
import 'package:magmug/features/blocked_users/data/repositories/blocked_users_repository_impl.dart';
import 'package:magmug/features/blocked_users/domain/entities/blocked_user.dart';
import 'package:magmug/features/blocked_users/domain/repositories/blocked_users_repository.dart';
import 'package:magmug/features/blocked_users/domain/usecases/get_blocked_users.dart';
import 'package:magmug/features/blocked_users/domain/usecases/unblock_user.dart';

final blockedUsersRemoteDataSourceProvider =
    Provider.autoDispose<BlockedUsersRemoteDataSource>((ref) {
      final api = AppAuthApi();
      ref.onDispose(api.close);
      return AppAuthBlockedUsersRemoteDataSource(api);
    });

final blockedUsersRepositoryProvider =
    Provider.autoDispose<BlockedUsersRepository>((ref) {
      return BlockedUsersRepositoryImpl(
        ref.watch(blockedUsersRemoteDataSourceProvider),
      );
    });

final getBlockedUsersUseCaseProvider = Provider.autoDispose<GetBlockedUsers>((
  ref,
) {
  return GetBlockedUsers(ref.watch(blockedUsersRepositoryProvider));
});

final unblockUserUseCaseProvider = Provider.autoDispose<UnblockUser>((ref) {
  return UnblockUser(ref.watch(blockedUsersRepositoryProvider));
});

final blockedUsersProvider = FutureProvider.autoDispose<List<BlockedUser>>((
  ref,
) async {
  final token = await _readAuthToken(ref);
  if (token == null) {
    return const [];
  }

  return ref.watch(getBlockedUsersUseCaseProvider)(token);
});

final blockedUsersActionControllerProvider =
    AsyncNotifierProvider.autoDispose<BlockedUsersActionController, void>(
      BlockedUsersActionController.new,
    );

class BlockedUsersActionController extends AsyncNotifier<void> {
  @override
  FutureOr<void> build() {}

  Future<void> unblockUser(int userId) async {
    if (state.isLoading) {
      return;
    }

    state = const AsyncLoading();
    try {
      final token = await _readAuthToken(ref);
      if (token == null) {
        throw const ApiException(
          'Bu islemi yapmak icin once giris yapmalisin.',
        );
      }

      await ref
          .read(unblockUserUseCaseProvider)
          .call(UnblockUserParams(token: token, userId: userId));
      ref.invalidate(blockedUsersProvider);
      state = const AsyncData(null);
    } catch (error, stackTrace) {
      state = AsyncError(error, stackTrace);
      Error.throwWithStackTrace(error, stackTrace);
    }
  }
}

Future<String?> _readAuthToken(Ref ref) async {
  final session = await ref.watch(appAuthProvider.future);
  final token = session?.token.trim();
  if (token == null || token.isEmpty) {
    return null;
  }
  return token;
}
