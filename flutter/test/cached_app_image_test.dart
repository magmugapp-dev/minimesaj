import 'package:flutter_test/flutter_test.dart';
import 'package:magmug/app_core.dart';

void main() {
  test(
    'cached app image decode keeps aspect ratio when both dimensions exist',
    () {
      final square = resolveCachedAppImageDecodeSize(
        cacheWidth: 240,
        cacheHeight: 240,
      );
      expect(square.width, 240);
      expect(square.height, isNull);

      final landscape = resolveCachedAppImageDecodeSize(
        cacheWidth: 480,
        cacheHeight: 320,
      );
      expect(landscape.width, 480);
      expect(landscape.height, isNull);

      final portrait = resolveCachedAppImageDecodeSize(
        cacheWidth: 320,
        cacheHeight: 480,
      );
      expect(portrait.width, isNull);
      expect(portrait.height, 480);
    },
  );

  test('cached app image decode keeps single-axis cache hints unchanged', () {
    expect(
      resolveCachedAppImageDecodeSize(cacheWidth: 160, cacheHeight: null),
      (width: 160, height: null),
    );
    expect(
      resolveCachedAppImageDecodeSize(cacheWidth: null, cacheHeight: 160),
      (width: null, height: 160),
    );
    expect(resolveCachedAppImageDecodeSize(cacheWidth: 0, cacheHeight: -1), (
      width: null,
      height: null,
    ));
  });
}
