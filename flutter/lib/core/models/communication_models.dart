import 'package:flutter/cupertino.dart';

@immutable
class AppConversationPreview {
  final int id;
  final int matchId;
  final int peerId;
  final String peerName;
  final String peerUsername;
  final String? peerProfileImageUrl;
  final String? peerAccountType;
  final String? peerLanguageCode;
  final String? peerLanguageName;
  final bool online;
  final String? lastMessage;
  final String? lastMessageType;
  final DateTime? lastMessageAt;
  final int unreadCount;
  final bool myMessageRead;
  final String? aiStatus;
  final String? aiStatusText;
  final DateTime? aiPlannedAt;

  const AppConversationPreview({
    required this.id,
    required this.matchId,
    required this.peerId,
    required this.peerName,
    required this.peerUsername,
    this.peerProfileImageUrl,
    this.peerAccountType,
    this.peerLanguageCode,
    this.peerLanguageName,
    required this.online,
    this.lastMessage,
    this.lastMessageType,
    this.lastMessageAt,
    required this.unreadCount,
    required this.myMessageRead,
    this.aiStatus,
    this.aiStatusText,
    this.aiPlannedAt,
  });

  String? get statusText {
    if (aiStatusText != null && aiStatusText!.trim().isNotEmpty) {
      return aiStatusText;
    }
    if (aiStatus == 'typing') {
      return 'Yaziyor...';
    }
    return null;
  }

  bool get isPeerAi => peerAccountType == 'ai';
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
  final String? languageCode;
  final String? languageName;
  final String? translatedText;
  final String? translationTargetLanguageCode;
  final String? translationTargetLanguageName;
  final String? clientMessageId;
  final String deliveryStatus;
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
    this.languageCode,
    this.languageName,
    this.translatedText,
    this.translationTargetLanguageCode,
    this.translationTargetLanguageName,
    this.clientMessageId,
    this.deliveryStatus = 'sent',
    this.createdAt,
  });

  bool isFromUser(int userId) => senderId == userId;

  AppConversationMessage copyWith({
    String? translatedText,
    String? translationTargetLanguageCode,
    String? translationTargetLanguageName,
  }) {
    return AppConversationMessage(
      id: id,
      conversationId: conversationId,
      senderId: senderId,
      senderName: senderName,
      senderProfileImageUrl: senderProfileImageUrl,
      type: type,
      text: text,
      fileUrl: fileUrl,
      fileDuration: fileDuration,
      isRead: isRead,
      isAiGenerated: isAiGenerated,
      languageCode: languageCode,
      languageName: languageName,
      translatedText: translatedText ?? this.translatedText,
      translationTargetLanguageCode:
          translationTargetLanguageCode ?? this.translationTargetLanguageCode,
      translationTargetLanguageName:
          translationTargetLanguageName ?? this.translationTargetLanguageName,
      clientMessageId: clientMessageId,
      deliveryStatus: deliveryStatus,
      createdAt: createdAt,
    );
  }
}

@immutable
class AppConversationMessagePage {
  final List<AppConversationMessage> messages;
  final int currentPage;
  final int? nextPage;
  final int? total;
  final String? aiStatus;
  final String? aiStatusText;
  final DateTime? aiPlannedAt;

  const AppConversationMessagePage({
    required this.messages,
    required this.currentPage,
    required this.nextPage,
    this.total,
    this.aiStatus,
    this.aiStatusText,
    this.aiPlannedAt,
  });

  bool get hasMore => nextPage != null;
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
