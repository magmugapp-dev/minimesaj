import 'package:magmug/app_core.dart';

@immutable
class ChatPreview {
  final AppConversationPreview? conversation;
  final String name;
  final String? avatarUrl;
  final String? lastMessage;
  final String? statusText;
  final String time;
  final int unread;
  final bool myMessageRead;
  final bool online;
  final bool forceInitials;

  const ChatPreview({
    this.conversation,
    required this.name,
    required this.time,
    this.avatarUrl,
    this.lastMessage,
    this.statusText,
    this.unread = 0,
    this.myMessageRead = false,
    this.online = false,
    this.forceInitials = false,
  });
}
