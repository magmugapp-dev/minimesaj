import 'package:flutter/cupertino.dart';

@immutable
class AppConversationPreview {
  final int id;
  final int matchId;
  final int peerId;
  final String peerName;
  final String peerUsername;
  final String? peerProfileImageUrl;
  final bool online;
  final String? lastMessage;
  final String? lastMessageType;
  final DateTime? lastMessageAt;
  final int unreadCount;
  final bool myMessageRead;

  const AppConversationPreview({
    required this.id,
    required this.matchId,
    required this.peerId,
    required this.peerName,
    required this.peerUsername,
    this.peerProfileImageUrl,
    required this.online,
    this.lastMessage,
    this.lastMessageType,
    this.lastMessageAt,
    required this.unreadCount,
    required this.myMessageRead,
  });

  String? get statusText {
    if (lastMessageType == 'typing') {
      return 'Yaziyor...';
    }
    return null;
  }
}

@immutable
class AppConversationMessage {
  final int id;
  final int conversationId;
  final int? senderId;
  final String senderName;
  final String? senderProfileImageUrl;
  final String type;
  final String? text;
  final String? fileUrl;
  final Duration? fileDuration;
  final bool isRead;
  final bool isAiGenerated;
  final DateTime? createdAt;

  const AppConversationMessage({
    required this.id,
    required this.conversationId,
    required this.senderId,
    required this.senderName,
    this.senderProfileImageUrl,
    required this.type,
    this.text,
    this.fileUrl,
    this.fileDuration,
    required this.isRead,
    required this.isAiGenerated,
    this.createdAt,
  });

  bool isFromUser(int userId) => senderId == userId;
}

@immutable
class AppNotification {
  final String id;
  final String? type;
  final String title;
  final String message;
  final String? route;
  final Map<String, String> routeParameters;
  final Map<String, dynamic> payload;
  final bool isRead;
  final DateTime? createdAt;

  const AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.message,
    required this.route,
    required this.routeParameters,
    required this.payload,
    required this.isRead,
    required this.createdAt,
  });

  String? get avatarUrl => payload['profil_resmi']?.toString();

  String? get avatarName {
    final senderName = payload['gonderen_adi']?.toString();
    if (senderName != null && senderName.trim().isNotEmpty) {
      return senderName.trim();
    }

    final titleValue = title.trim();
    return titleValue.isEmpty ? null : titleValue;
  }

  int? get conversationId {
    final direct = switch (payload['sohbet_id']) {
      final int intValue => intValue,
      final num numValue => numValue.toInt(),
      final String stringValue => int.tryParse(stringValue),
      _ => null,
    };
    if (direct != null) {
      return direct;
    }

    return int.tryParse(routeParameters['sohbet_id'] ?? '');
  }
}
