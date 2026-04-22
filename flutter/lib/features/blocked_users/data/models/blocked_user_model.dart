import 'package:magmug/app_core.dart';
import 'package:magmug/features/blocked_users/domain/entities/blocked_user.dart';

class BlockedUserModel extends BlockedUser {
  const BlockedUserModel({
    required super.id,
    required super.firstName,
    required super.surname,
    required super.username,
    super.profileImageUrl,
    super.blockedAt,
  });

  factory BlockedUserModel.fromAppBlockedUser(AppBlockedUser user) {
    return BlockedUserModel(
      id: user.id,
      firstName: user.firstName,
      surname: user.surname,
      username: user.username,
      profileImageUrl: user.profileImageUrl,
      blockedAt: user.blockedAt,
    );
  }
}
