import 'package:magmug/app_core.dart';
import 'package:magmug/features/blocked_users/data/models/blocked_user_model.dart';

abstract interface class BlockedUsersRemoteDataSource {
  Future<List<BlockedUserModel>> getBlockedUsers(String token);

  Future<void> unblockUser(String token, {required int userId});
}

class AppAuthBlockedUsersRemoteDataSource
    implements BlockedUsersRemoteDataSource {
  final AppAuthApi _api;

  const AppAuthBlockedUsersRemoteDataSource(this._api);

  @override
  Future<List<BlockedUserModel>> getBlockedUsers(String token) async {
    final users = await _api.fetchBlockedUsers(token);
    return users
        .map(BlockedUserModel.fromAppBlockedUser)
        .toList(growable: false);
  }

  @override
  Future<void> unblockUser(String token, {required int userId}) {
    return _api.unblockUser(token, userId: userId);
  }
}
