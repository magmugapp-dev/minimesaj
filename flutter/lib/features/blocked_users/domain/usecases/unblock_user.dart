import 'package:magmug/core/usecases/use_case.dart';
import 'package:magmug/features/blocked_users/domain/repositories/blocked_users_repository.dart';

class UnblockUserParams {
  final String token;
  final int userId;

  const UnblockUserParams({required this.token, required this.userId});
}

class UnblockUser implements UseCase<void, UnblockUserParams> {
  final BlockedUsersRepository _repository;

  const UnblockUser(this._repository);

  @override
  Future<void> call(UnblockUserParams params) {
    return _repository.unblockUser(params.token, userId: params.userId);
  }
}
