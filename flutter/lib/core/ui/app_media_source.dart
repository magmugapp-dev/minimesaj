import 'dart:io';

enum AppMediaSourceKind { empty, remote, asset, file, relative }

class AppMediaSource {
  final AppMediaSourceKind kind;
  final String value;

  const AppMediaSource._(this.kind, this.value);

  bool get isRemote => kind == AppMediaSourceKind.remote;
  bool get isAsset => kind == AppMediaSourceKind.asset;
  bool get isFile => kind == AppMediaSourceKind.file;

  File? get file => isFile ? File(value) : null;

  static AppMediaSource resolve(String? raw) {
    final source = raw?.trim();
    if (source == null || source.isEmpty) {
      return const AppMediaSource._(AppMediaSourceKind.empty, '');
    }

    final uri = Uri.tryParse(source);
    final scheme = uri?.scheme.toLowerCase();
    if (scheme == 'http' || scheme == 'https') {
      return AppMediaSource._(AppMediaSourceKind.remote, source);
    }
    if (scheme == 'file') {
      return AppMediaSource._(
        AppMediaSourceKind.file,
        uri?.toFilePath() ?? source.replaceFirst(RegExp(r'^file://'), ''),
      );
    }
    if (source.startsWith('assets/')) {
      return AppMediaSource._(AppMediaSourceKind.asset, source);
    }
    if (source.startsWith('/') ||
        RegExp(r'^[A-Za-z]:[\\/]').hasMatch(source) ||
        source.startsWith(r'\\')) {
      return AppMediaSource._(AppMediaSourceKind.file, source);
    }

    return AppMediaSource._(AppMediaSourceKind.relative, source);
  }
}
