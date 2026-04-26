import 'package:magmug/app_core.dart';
import 'package:magmug/features/chat/chat_local_store.dart';
import 'package:magmug/features/home/models/chat_preview.dart';

final homeChatsProvider = FutureProvider<List<ChatPreview>>((ref) async {
  ref.watch(conversationFeedRefreshProvider);
  final session = await ref.watch(appAuthProvider.future);
  final token = session?.token;
  final userId = session?.user?.id;
  if (token == null || token.trim().isEmpty || userId == null) {
    return const [];
  }

  final localStore = ChatLocalStore.instance;
  final cachedConversations = await localStore.getConversationPreviews(
    ownerUserId: userId,
  );
  if (cachedConversations.isNotEmpty) {
    return cachedConversations.map(_chatPreviewFromConversation).toList();
  }

  final bootstrap = await AppBootstrapCoordinator.instance.bootstrap(token);
  if (bootstrap.user.id != userId) {
    return const <ChatPreview>[];
  }
  final conversations = bootstrap.conversations;
  await localStore.upsertConversationPreviews(
    conversations,
    ownerUserId: userId,
  );
  return conversations.map(_chatPreviewFromConversation).toList();
});

ChatPreview _chatPreviewFromConversation(AppConversationPreview conversation) {
  return ChatPreview(
    conversation: conversation,
    name: conversation.peerName,
    avatarUrl: conversation.peerProfileImageUrl,
    lastMessage: conversation.lastMessage,
    statusText: conversation.statusText,
    time: _formatConversationTime(conversation.lastMessageAt),
    unread: conversation.unreadCount,
    myMessageRead: conversation.myMessageRead,
    online: conversation.online,
  );
}

String _formatConversationTime(DateTime? value) {
  if (value == null) {
    return '';
  }

  final local = value.toLocal();
  final hour = local.hour.toString().padLeft(2, '0');
  final minute = local.minute.toString().padLeft(2, '0');
  return '$hour:$minute';
}
