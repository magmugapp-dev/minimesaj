import 'package:flutter_test/flutter_test.dart';
import 'package:magmug/core/models/communication_models.dart';

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
}
