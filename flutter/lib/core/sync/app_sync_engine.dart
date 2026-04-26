import 'dart:async';
import 'dart:io';

import 'package:magmug/core/models/auth_models.dart';
import 'package:magmug/core/models/user_models.dart';
import 'package:magmug/core/network/app_auth_api.dart';
import 'package:magmug/core/storage/app_storage.dart';
import 'package:magmug/features/chat/chat_local_store.dart';

class AppSyncEngine {
  AppSyncEngine._({
    AppAuthApi Function()? apiFactory,
    ChatOutboxStore Function()? outboxStoreFactory,
  }) : _apiFactory = apiFactory ?? AppAuthApi.new,
       _outboxStoreFactory =
           outboxStoreFactory ?? (() => ChatLocalStore.instance);

  AppSyncEngine.testing({
    required AppAuthApi Function() apiFactory,
    ChatOutboxStore Function()? outboxStoreFactory,
  }) : _apiFactory = apiFactory,
       _outboxStoreFactory =
           outboxStoreFactory ?? (() => ChatLocalStore.instance);

  static final AppSyncEngine instance = AppSyncEngine._();
  static const int _maxDeltaSyncPages = 6;

  final AppAuthApi Function() _apiFactory;
  final ChatOutboxStore Function() _outboxStoreFactory;
  bool _flushInFlight = false;
  bool _flushAgainRequested = false;
  Future<void>? _flushFuture;
  bool _deltaSyncInFlight = false;

  Future<void> syncDelta({
    required String token,
    required int ownerUserId,
  }) async {
    if (_deltaSyncInFlight || token.trim().isEmpty || ownerUserId <= 0) {
      return;
    }

    final session = await AppSessionStorage.readSession();
    if (session?.token != token || session?.user?.id != ownerUserId) {
      return;
    }

    _deltaSyncInFlight = true;
    final api = _apiFactory();
    try {
      for (var page = 0; page < _maxDeltaSyncPages; page++) {
        final latestSession = await AppSessionStorage.readSession();
        if (latestSession?.token != token ||
            latestSession?.user?.id != ownerUserId) {
          return;
        }

        final syncToken = await AppSessionStorage.readMobileSyncToken();
        final result = await api.syncMobile(token, syncToken: syncToken);
        final user = result.user;
        if (user != null && user.id == ownerUserId) {
          await AppSessionStorage.saveSession(
            AuthenticatedSession(token: token, user: user),
          );
        }
        await AppSessionStorage.saveMobileSyncToken(result.syncToken);
        await ChatLocalStore.instance.upsertConversationPreviews(
          result.conversations,
          ownerUserId: ownerUserId,
        );
        await ChatLocalStore.instance.upsertConversationMessages(
          result.messages,
          ownerUserId: ownerUserId,
        );
        if (!result.hasMore ||
            result.syncToken == null ||
            result.syncToken!.trim().isEmpty) {
          return;
        }
      }
    } on UnauthorizedApiException {
      return;
    } on SocketException {
      return;
    } on HandshakeException {
      return;
    } finally {
      _deltaSyncInFlight = false;
      api.close();
    }
  }

  Future<void> flushOutbox({
    required String token,
    required int ownerUserId,
  }) async {
    if (token.trim().isEmpty || ownerUserId <= 0) {
      return;
    }

    if (_flushInFlight) {
      _flushAgainRequested = true;
      final inFlight = _flushFuture;
      if (inFlight != null) {
        await inFlight;
      }
      return;
    }

    final session = await AppSessionStorage.readSession();
    if (session?.token != token || session?.user?.id != ownerUserId) {
      return;
    }
    if (_flushInFlight) {
      _flushAgainRequested = true;
      final inFlight = _flushFuture;
      if (inFlight != null) {
        await inFlight;
      }
      return;
    }

    _flushInFlight = true;
    final future = _drainOutbox(token: token, ownerUserId: ownerUserId);
    _flushFuture = future;
    try {
      await future;
    } finally {
      if (identical(_flushFuture, future)) {
        _flushFuture = null;
      }
    }
  }

  Future<void> _drainOutbox({
    required String token,
    required int ownerUserId,
  }) async {
    final api = _apiFactory();
    try {
      while (true) {
        _flushAgainRequested = false;
        final completed = await _flushOutboxBatch(
          api: api,
          token: token,
          ownerUserId: ownerUserId,
        );
        if (!completed || !_flushAgainRequested) {
          return;
        }
      }
    } finally {
      _flushInFlight = false;
      api.close();
    }
  }

  Future<bool> _flushOutboxBatch({
    required AppAuthApi api,
    required String token,
    required int ownerUserId,
  }) async {
    final session = await AppSessionStorage.readSession();
    if (session?.token != token || session?.user?.id != ownerUserId) {
      return false;
    }

    final store = _outboxStoreFactory();
    final items = await store.getPendingOutboxItems(ownerUserId: ownerUserId);
    for (final item in items) {
      final latestSession = await AppSessionStorage.readSession();
      if (latestSession?.token != token ||
          latestSession?.user?.id != ownerUserId ||
          item.ownerUserId != ownerUserId) {
        return false;
      }

      await store.markOutboxSending(
        ownerUserId: ownerUserId,
        clientMessageId: item.clientMessageId,
      );

      try {
        String? remoteFilePath = item.remoteFilePath?.trim();
        if (item.type != 'metin') {
          if (remoteFilePath == null || remoteFilePath.isEmpty) {
            final localFilePath = item.localFilePath?.trim();
            final clientUploadId = item.clientUploadId?.trim();
            if (localFilePath == null ||
                localFilePath.isEmpty ||
                clientUploadId == null ||
                clientUploadId.isEmpty) {
              await store.markOutboxFailed(
                ownerUserId: ownerUserId,
                clientMessageId: item.clientMessageId,
                errorMessage: 'missing_media',
              );
              continue;
            }

            final localFile = File(localFilePath);
            if (!await localFile.exists()) {
              await store.markOutboxFailed(
                ownerUserId: ownerUserId,
                clientMessageId: item.clientMessageId,
                errorMessage: 'missing_media_file',
              );
              continue;
            }
            final localFileSize = await localFile.length();
            if (localFileSize <= 0) {
              await store.markOutboxFailed(
                ownerUserId: ownerUserId,
                clientMessageId: item.clientMessageId,
                errorMessage: 'empty_media_file',
              );
              continue;
            }
            if (item.type == 'ses' && localFileSize > 20 * 1024 * 1024) {
              await store.markOutboxFailed(
                ownerUserId: ownerUserId,
                clientMessageId: item.clientMessageId,
                errorMessage: 'voice_file_too_large',
              );
              continue;
            }

            final upload = await api.uploadMobileMedia(
              token,
              filePath: localFilePath,
              clientUploadId: clientUploadId,
              messageType: item.type,
            );
            remoteFilePath = upload.filePath.trim();
            if (remoteFilePath.isEmpty) {
              await store.markOutboxFailed(
                ownerUserId: ownerUserId,
                clientMessageId: item.clientMessageId,
                errorMessage: 'missing_remote_media',
              );
              continue;
            }

            await store.updateOutboxRemoteFilePath(
              ownerUserId: ownerUserId,
              clientMessageId: item.clientMessageId,
              remoteFilePath: remoteFilePath,
            );
          }
        }

        final sentMessage = await api.sendMobileConversationMessage(
          token,
          conversationId: item.conversationId,
          clientMessageId: item.clientMessageId,
          messageType: item.type,
          text: item.type == 'metin' ? item.text : null,
          filePath: item.type == 'metin' ? null : remoteFilePath,
          fileDuration: item.fileDuration,
        );
        await store.markOutboxSent(
          ownerUserId: ownerUserId,
          clientMessageId: item.clientMessageId,
          sentMessage: sentMessage,
        );
      } on UnauthorizedApiException {
        return false;
      } on SocketException {
        await store.markOutboxFailed(
          ownerUserId: ownerUserId,
          clientMessageId: item.clientMessageId,
          errorMessage: 'network',
        );
        return false;
      } on HandshakeException {
        await store.markOutboxFailed(
          ownerUserId: ownerUserId,
          clientMessageId: item.clientMessageId,
          errorMessage: 'network',
        );
        return false;
      } on TimeoutException {
        await store.markOutboxFailed(
          ownerUserId: ownerUserId,
          clientMessageId: item.clientMessageId,
          errorMessage: 'network',
        );
        return false;
      } on FileSystemException catch (error) {
        await store.markOutboxFailed(
          ownerUserId: ownerUserId,
          clientMessageId: item.clientMessageId,
          errorMessage: error.message,
        );
      } on ApiException catch (error) {
        await store.markOutboxFailed(
          ownerUserId: ownerUserId,
          clientMessageId: item.clientMessageId,
          errorMessage: error.message,
        );
      }
    }

    return true;
  }
}
