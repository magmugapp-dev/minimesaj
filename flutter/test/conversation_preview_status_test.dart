import 'package:flutter_test/flutter_test.dart';
import 'package:magmug/core/models/communication_models.dart';
import 'package:magmug/features/chat/chat_local_store.dart';

void main() {
  test('queued previews stay silent without status text', () {
    const preview = AppConversationPreview(
      id: 1,
      matchId: 2,
      peerId: 3,
      peerName: 'AI',
      peerUsername: 'ai_user',
      online: true,
      unreadCount: 0,
      myMessageRead: false,
      aiStatus: 'queued',
      aiStatusText: null,
    );

    expect(preview.statusText, isNull);
  });

  test('typing previews fall back to yaziyor text', () {
    const preview = AppConversationPreview(
      id: 1,
      matchId: 2,
      peerId: 3,
      peerName: 'AI',
      peerUsername: 'ai_user',
      online: true,
      unreadCount: 0,
      myMessageRead: false,
      aiStatus: 'typing',
      aiStatusText: null,
    );

    expect(preview.statusText, 'Yaziyor...');
  });

  test('explicit status text wins over fallback', () {
    const preview = AppConversationPreview(
      id: 1,
      matchId: 2,
      peerId: 3,
      peerName: 'AI',
      peerUsername: 'ai_user',
      online: true,
      unreadCount: 0,
      myMessageRead: false,
      aiStatus: 'typing',
      aiStatusText: 'Hazirlaniyor',
    );

    expect(preview.statusText, 'Hazirlaniyor');
  });

  test('read event from current user clears unread count locally', () {
    final patch = ChatLocalStore.instance.conversationReadPreviewPatch(
      readerUserId: 7,
      currentUserId: 7,
    );

    expect(patch, {'unread_count': 0});
  });

  test('read event from peer marks my latest message read locally', () {
    final patch = ChatLocalStore.instance.conversationReadPreviewPatch(
      readerUserId: 8,
      currentUserId: 7,
    );

    expect(patch, {'my_message_read': 1});
  });
}
