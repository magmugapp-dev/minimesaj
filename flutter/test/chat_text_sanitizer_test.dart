import 'package:flutter_test/flutter_test.dart';
import 'package:magmug/core/chat/chat_text_sanitizer.dart';

void main() {
  test('unwraps structured reply envelopes', () {
    expect(ChatTextSanitizer.sanitize('{"reply":"Selam"}'), 'Selam');
  });

  test('unwraps fenced json envelopes', () {
    expect(
      ChatTextSanitizer.sanitize('```json\n{"reply":"Selam"}\n```'),
      'Selam',
    );
  });

  test('drops malformed json envelopes instead of leaking raw payloads', () {
    expect(ChatTextSanitizer.sanitize('{"reply":"S der'), isNull);
  });

  test('keeps plain text untouched', () {
    expect(ChatTextSanitizer.sanitize('Merhaba'), 'Merhaba');
  });
}
