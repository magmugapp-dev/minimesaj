import 'dart:convert';

class ChatTextSanitizer {
  const ChatTextSanitizer._();

  static String? sanitize(String? value) {
    final normalized = _nullableString(value);
    if (normalized == null) {
      return null;
    }

    final fenceMatch = RegExp(
      r'^```(?:json)?\s*(.*?)\s*```$',
      caseSensitive: false,
      dotAll: true,
    ).firstMatch(normalized);
    final candidate = _nullableString(fenceMatch?.group(1)) ?? normalized;
    final extracted = _extractEnvelopeText(candidate);
    if (candidate.startsWith('{') || candidate.startsWith('[')) {
      return _nullableString(extracted);
    }

    return _nullableString(extracted ?? candidate);
  }

  static String? _extractEnvelopeText(String text) {
    final normalized = _nullableString(text);
    if (normalized == null) {
      return null;
    }
    if (!normalized.startsWith('{') && !normalized.startsWith('[')) {
      return normalized;
    }

    try {
      final decoded = jsonDecode(normalized);
      final extracted = _extractEnvelopeValue(decoded);
      if (extracted == null) {
        return null;
      }

      return sanitize(extracted);
    } catch (_) {
      return null;
    }
  }

  static String? _extractEnvelopeValue(Object? value) {
    if (value is String) {
      return value;
    }

    if (value is Map) {
      for (final key in const [
        'reply',
        'cevap',
        'text',
        'message',
        'content',
        'mesaj',
      ]) {
        final candidate = _extractEnvelopeValue(value[key]);
        if (candidate != null && candidate.trim().isNotEmpty) {
          return candidate;
        }
      }

      final parts = value['parts'];
      if (parts is List) {
        for (final part in parts) {
          final candidate = _extractEnvelopeValue(part);
          if (candidate != null && candidate.trim().isNotEmpty) {
            return candidate;
          }
        }
      }

      final candidates = value['candidates'];
      if (candidates is List) {
        for (final item in candidates) {
          final candidate = _extractEnvelopeValue(item);
          if (candidate != null && candidate.trim().isNotEmpty) {
            return candidate;
          }
        }
      }
    }

    if (value is List) {
      for (final item in value) {
        final candidate = _extractEnvelopeValue(item);
        if (candidate != null && candidate.trim().isNotEmpty) {
          return candidate;
        }
      }
    }

    return null;
  }

  static String? _nullableString(String? value) {
    final normalized = value?.trim();
    if (normalized == null || normalized.isEmpty) {
      return null;
    }
    return normalized;
  }
}
