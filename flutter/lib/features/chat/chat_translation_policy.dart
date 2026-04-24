import 'package:magmug/core/models/communication_models.dart';

String? normalizeChatLanguageCode(String? value) {
  final normalized = value?.trim().toLowerCase();
  if (normalized == null || normalized.isEmpty) {
    return null;
  }

  final primary = normalized.replaceAll('_', '-').split('-').first.trim();
  if (primary.isEmpty) {
    return null;
  }

  final valid = RegExp(r'^[a-z]{2,3}$');
  return valid.hasMatch(primary) ? primary : null;
}

bool shouldShowInlineTranslateAction({
  required AppConversationMessage message,
  required int currentUserId,
  String? viewerLanguageCode,
  String? peerLanguageCode,
}) {
  if (message.isFromUser(currentUserId)) {
    return false;
  }

  if (message.type != 'metin') {
    return false;
  }

  if ((message.text?.trim().isEmpty ?? true)) {
    return false;
  }

  final viewerCode = normalizeChatLanguageCode(viewerLanguageCode) ?? 'tr';
  final sourceCode =
      normalizeChatLanguageCode(message.languageCode) ??
      normalizeChatLanguageCode(peerLanguageCode);

  if (sourceCode == null) {
    return false;
  }

  return sourceCode != viewerCode;
}
