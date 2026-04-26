import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('new direct visible strings use runtime translations', () {
    final libDir = Directory('lib');
    final violations = <String>[];
    final patterns = <RegExp>[
      RegExp(r'''Text\(\s*['"][^'"]+[\'"]'''),
      RegExp(r'''placeholder:\s*['"][^'"]+[\'"]'''),
      RegExp(r'''ApiException\(\s*['"][^'"]+[\'"]'''),
      RegExp(r'''label:\s*['"][^'"]+[\'"]'''),
      RegExp(r'''title:\s*['"][^'"]+[\'"]'''),
      RegExp(r'''message:\s*['"][^'"]+[\'"]'''),
      RegExp(r'''subtitle:\s*['"][^'"]+[\'"]'''),
      RegExp(r'''badge:\s*['"][^'"]+[\'"]'''),
      RegExp(r'''emptyMessage:\s*['"][^'"]+[\'"]'''),
      RegExp(r'''loadingErrorMessage:\s*['"][^'"]+[\'"]'''),
    ];

    for (final entity in libDir.listSync(recursive: true)) {
      if (entity is! File || !entity.path.endsWith('.dart')) {
        continue;
      }
      if (entity.path.contains(
        '${Platform.pathSeparator}l10n${Platform.pathSeparator}',
      )) {
        continue;
      }

      final lines = entity.readAsLinesSync();
      for (var index = 0; index < lines.length; index++) {
        final line = lines[index];
        if (line.contains('AppRuntimeText.instance.t') ||
            line.contains('_text(') ||
            line.contains('_t(') ||
            line.contains("Text('')") ||
            line.contains('Text("")') ||
            line.contains('fallbackLabel:') ||
            line.contains('AppLanguage(code:') ||
            line.contains("label: '\$") ||
            line.contains('galleryCountLabel:') ||
            line.contains('errorMessage:')) {
          continue;
        }
        if (patterns.any((pattern) => pattern.hasMatch(line))) {
          violations.add('${entity.path}:${index + 1}: ${line.trim()}');
        }
      }
    }

    expect(violations, isEmpty, reason: violations.join('\n'));
  });
}
