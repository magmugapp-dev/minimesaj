import 'package:magmug/features/blocked_users/data/datasources/blocked_users_remote_data_source.dart';
import 'package:magmug/features/blocked_users/domain/entities/blocked_user.dart';
import 'package:magmug/features/blocked_users/domain/repositories/blocked_users_repository.dart';

class BlockedUsersRepositoryImpl implements BlockedUsersRepository {
  final BlockedUsersRemoteDataSource _remoteDataSource;

  const BlockedUsersRepositoryImpl(this._remoteDataSource);

  @override
  Future<List<BlockedUser>> getBlockedUsers(String token) {
    return _remoteDataSource.getBlockedUsers(token);
  }

  @override
  Future<void> unblockUser(String token, {required int userId}) {
    return _remoteDataSource.unblockUser(token, userId: userId);
  }
}
