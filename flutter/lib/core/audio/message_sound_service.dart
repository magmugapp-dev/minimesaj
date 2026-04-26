import 'dart:async';

import 'package:just_audio/just_audio.dart';

enum AppMessageSoundKind { send, receive }

class AppMessageSoundService {
  AppMessageSoundService._();

  static final AppMessageSoundService instance = AppMessageSoundService._();

  final AudioPlayer _sendPlayer = AudioPlayer();
  final AudioPlayer _receivePlayer = AudioPlayer();
  final Map<AppMessageSoundKind, DateTime> _lastPlayedAt =
      <AppMessageSoundKind, DateTime>{};

  static const Duration _dedupeWindow = Duration(milliseconds: 450);
  static const String _sendAsset = 'assets/sounds/send.mp3';
  static const String _receiveAsset = 'assets/sounds/receive.mp3';

  Future<void> playSend({required bool enabled}) {
    return _play(AppMessageSoundKind.send, enabled: enabled);
  }

  Future<void> playReceive({required bool enabled}) {
    return _play(AppMessageSoundKind.receive, enabled: enabled);
  }

  Future<void> _play(AppMessageSoundKind kind, {required bool enabled}) async {
    if (!enabled) {
      return;
    }

    final now = DateTime.now();
    final lastPlayedAt = _lastPlayedAt[kind];
    if (lastPlayedAt != null && now.difference(lastPlayedAt) < _dedupeWindow) {
      return;
    }
    _lastPlayedAt[kind] = now;

    final player = switch (kind) {
      AppMessageSoundKind.send => _sendPlayer,
      AppMessageSoundKind.receive => _receivePlayer,
    };
    final asset = switch (kind) {
      AppMessageSoundKind.send => _sendAsset,
      AppMessageSoundKind.receive => _receiveAsset,
    };

    try {
      await player.stop();
      await player.setAsset(asset);
      unawaited(player.play());
    } catch (_) {
      // Sounds are non-critical UI feedback; failures should never block chat.
    }
  }
}
