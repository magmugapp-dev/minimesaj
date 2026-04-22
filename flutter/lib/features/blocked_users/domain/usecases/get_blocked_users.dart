import 'package:magmug/core/usecases/use_case.dart';
import 'package:magmug/features/blocked_users/domain/entities/blocked_user.dart';
import 'package:magmug/features/blocked_users/domain/repositories/blocked_users_repository.dart';

class GetBlockedUsers implements UseCase<List<BlockedUser>, String> {
  final BlockedUsersRepository _repository;

  const GetBlockedUsers(this._repository);

  @override
  Future<List<BlockedUser>> call(String token) {
    return _repository.getBlockedUsers(token);
  }
}
