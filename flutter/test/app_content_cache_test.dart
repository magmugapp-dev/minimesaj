import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:hive/hive.dart';
import 'package:magmug/core/i18n/app_runtime_text.dart';
import 'package:magmug/core/models/app_content_models.dart';
import 'package:magmug/core/storage/app_storage.dart';

void main() {
  setUpAll(() async {
    final directory = await Directory.systemTemp.createTemp(
      'magmug_hive_test_',
    );
    Hive.init(directory.path);
  });

  setUp(() async {
    await Hive.deleteBoxFromDisk('app_content');
  });

  test(
    'app content cache saves and restores the last successful payload',
    () async {
      final content = AppContent(
        languages: const [
          AppContentLanguage(
            code: 'tr',
            name: 'Turkce',
            isActive: true,
            isDefault: true,
          ),
        ],
        defaultLanguageCode: 'tr',
        selectedLanguageCode: 'tr',
        translations: const {'profileHelp': 'Yardim'},
        legalTexts: const {
          'privacy': AppContentLegalText(
            type: 'privacy',
            title: 'Gizlilik',
            content: 'Panel metni',
          ),
        },
        faqs: const [
          AppContentFaqItem(id: 1, question: 'Soru', answer: 'Cevap'),
        ],
        version: 'v1',
      );

      await AppContentStorage.save(content);

      final restored = await AppContentStorage.read('tr');
      expect(restored, isNotNull);
      expect(restored!.fromCache, isTrue);
      expect(restored.translations['profileHelp'], 'Yardim');
      expect(restored.legalTexts['privacy']?.content, 'Panel metni');
      expect(restored.faqs.single.question, 'Soru');

      final last = await AppContentStorage.readLast();
      expect(last?.selectedLanguageCode, 'tr');
    },
  );

  test('runtime translations replace placeholders and fall back safely', () {
    final content = AppContent(
      languages: const [
        AppContentLanguage(code: 'en', name: 'English', isDefault: true),
      ],
      defaultLanguageCode: 'en',
      selectedLanguageCode: 'en',
      translations: const {'profileAppVersion': '{appName} v{version}'},
      legalTexts: const {},
      faqs: const [],
      version: 'v1',
    );

    AppRuntimeText.instance.update(content);

    expect(
      AppRuntimeText.instance.t(
        'profileAppVersion',
        '',
        args: const {'appName': 'Magmug', 'version': '2.0.0'},
      ),
      'Magmug v2.0.0',
    );
    expect(AppRuntimeText.instance.t('missingKey', 'Fallback'), 'Fallback');
  });
}
