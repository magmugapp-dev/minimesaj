import 'package:flutter_test/flutter_test.dart';
import 'package:magmug/core/models/communication_models.dart';
import 'package:magmug/features/chat/chat_translation_policy.dart';

void main() {
  const incomingEnglish = AppConversationMessage(
    id: 10,
    conversationId: 4,
    senderId: 22,
    senderName: 'Alex',
    type: 'metin',
    text: 'Hello there',
    isRead: false,
    isAiGenerated: false,
    languageCode: 'en',
  );

  const outgoingTurkish = AppConversationMessage(
    id: 11,
    conversationId: 4,
    senderId: 7,
    senderName: 'Ben',
    type: 'metin',
    text: 'Selam',
    isRead: true,
    isAiGenerated: false,
    languageCode: 'tr',
  );

  test(
    'shows inline translate action for incoming text when languages differ',
    () {
      final result = shouldShowInlineTranslateAction(
        message: incomingEnglish,
        currentUserId: 7,
        viewerLanguageCode: 'tr',
        peerLanguageCode: 'en',
      );

      expect(result, isTrue);
    },
  );

  test('hides inline translate action when languages are the same', () {
    final result = shouldShowInlineTranslateAction(
      message: incomingEnglish.copyWith(),
      currentUserId: 7,
      viewerLanguageCode: 'en',
      peerLanguageCode: 'en',
    );

    expect(result, isFalse);
  });

  test('hides inline translate action for outgoing messages', () {
    final result = shouldShowInlineTranslateAction(
      message: outgoingTurkish,
      currentUserId: 7,
      viewerLanguageCode: 'en',
      peerLanguageCode: 'tr',
    );

    expect(result, isFalse);
  });

  test('falls back to peer language when message language is missing', () {
    const incomingWithoutLanguage = AppConversationMessage(
      id: 12,
      conversationId: 4,
      senderId: 22,
      senderName: 'Alex',
      type: 'metin',
      text: 'Bonjour',
      isRead: false,
      isAiGenerated: false,
      languageCode: null,
    );

    final result = shouldShowInlineTranslateAction(
      message: incomingWithoutLanguage,
      currentUserId: 7,
      viewerLanguageCode: 'tr',
      peerLanguageCode: 'fr',
    );

    expect(result, isTrue);
  });
}
