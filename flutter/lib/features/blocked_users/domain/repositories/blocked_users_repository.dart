import 'package:magmug/features/blocked_users/domain/entities/blocked_user.dart';

abstract interface class BlockedUsersRepository {
  Future<List<BlockedUser>> getBlockedUsers(String token);

  Future<void> unblockUser(String token, {required int userId});
}
