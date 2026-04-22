import 'package:flutter/foundation.dart';

@immutable
class BlockedUser {
  final int id;
  final String firstName;
  final String surname;
  final String username;
  final String? profileImageUrl;
  final DateTime? blockedAt;

  const BlockedUser({
    required this.id,
    required this.firstName,
    required this.surname,
    required this.username,
    this.profileImageUrl,
    this.blockedAt,
  });

  String get displayName {
    final value = '$firstName $surname'.trim();
    return value.isEmpty ? username : value;
  }

  String get handle {
    final value = username.trim();
    if (value.isEmpty) {
      return '';
    }
    return value.startsWith('@') ? value : '@$value';
  }
}
