import 'package:flutter_test/flutter_test.dart';
import 'package:magmug/app_core.dart';
import 'package:magmug/features/chat/chat_flow.dart';

void main() {
  testWidgets('chat composer shows microphone when text is empty', (
    tester,
  ) async {
    final controller = TextEditingController();
    addTearDown(controller.dispose);

    await tester.pumpWidget(
      CupertinoApp(
        home: CupertinoPageScaffold(
          child: Align(
            alignment: Alignment.bottomCenter,
            child: buildChatInputBarForTest(
              controller: controller,
              canSend: false,
            ),
          ),
        ),
      ),
    );

    expect(find.byKey(const ValueKey('chat-input-mic')), findsOneWidget);
    expect(find.byKey(const ValueKey('chat-input-send')), findsNothing);
  });

  testWidgets('chat composer shows send action when text is present', (
    tester,
  ) async {
    final controller = TextEditingController(text: 'Merhaba');
    addTearDown(controller.dispose);

    await tester.pumpWidget(
      CupertinoApp(
        home: CupertinoPageScaffold(
          child: Align(
            alignment: Alignment.bottomCenter,
            child: buildChatInputBarForTest(
              controller: controller,
              canSend: true,
            ),
          ),
        ),
      ),
    );

    expect(find.byKey(const ValueKey('chat-input-send')), findsOneWidget);
    expect(find.byKey(const ValueKey('chat-input-mic')), findsNothing);
  });

  testWidgets('attachment sheet only offers camera and photo actions', (
    tester,
  ) async {
    await tester.pumpWidget(
      CupertinoApp(
        home: CupertinoPageScaffold(child: buildMediaAttachmentSheetForTest()),
      ),
    );

    expect(find.text('Kamera'), findsOneWidget);
    expect(find.text('Fotograf'), findsOneWidget);
    expect(find.text('Video'), findsNothing);
    expect(find.text('Dosya'), findsNothing);
  });

  testWidgets('chat composer leading action emits attachment callback', (
    tester,
  ) async {
    final controller = TextEditingController();
    addTearDown(controller.dispose);
    var opened = false;

    await tester.pumpWidget(
      CupertinoApp(
        home: CupertinoPageScaffold(
          child: Align(
            alignment: Alignment.bottomCenter,
            child: buildChatInputBarForTest(
              controller: controller,
              canSend: false,
              onLeadingTap: () => opened = true,
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.byKey(const ValueKey('chat-input-leading')));
    await tester.pump();

    expect(opened, isTrue);
  });

  testWidgets('microphone tap emits record start callback', (tester) async {
    final controller = TextEditingController();
    addTearDown(controller.dispose);
    var started = false;

    await tester.pumpWidget(
      CupertinoApp(
        home: CupertinoPageScaffold(
          child: Align(
            alignment: Alignment.bottomCenter,
            child: buildChatInputBarForTest(
              controller: controller,
              canSend: false,
              onMicTap: () => started = true,
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.byKey(const ValueKey('chat-input-mic')));
    await tester.pump();

    expect(started, isTrue);
  });

  testWidgets('voice recorder shows inline cancel and send actions', (
    tester,
  ) async {
    final controller = TextEditingController();
    addTearDown(controller.dispose);
    var cancelled = false;
    var sent = false;

    await tester.pumpWidget(
      CupertinoApp(
        home: CupertinoPageScaffold(
          child: Align(
            alignment: Alignment.bottomCenter,
            child: buildChatInputBarForTest(
              controller: controller,
              canSend: false,
              isVoiceRecording: true,
              voiceElapsed: const Duration(seconds: 7),
              onVoiceCancel: () => cancelled = true,
              onVoiceSend: () => sent = true,
            ),
          ),
        ),
      ),
    );

    expect(find.byKey(const ValueKey('chat-voice-recorder')), findsOneWidget);
    expect(find.byKey(const ValueKey('chat-voice-cancel')), findsOneWidget);
    expect(find.byKey(const ValueKey('chat-voice-send')), findsOneWidget);
    expect(find.byKey(const ValueKey('chat-input-mic')), findsNothing);
    expect(find.text('Sola kaydir iptal'), findsNothing);

    await tester.tap(find.byKey(const ValueKey('chat-voice-cancel')));
    await tester.pump();
    expect(cancelled, isTrue);

    await tester.tap(find.byKey(const ValueKey('chat-voice-send')));
    await tester.pump();
    expect(sent, isTrue);
  });

  testWidgets('photo bubble opens a fullscreen image viewer', (tester) async {
    await tester.pumpWidget(
      CupertinoApp(
        home: CupertinoPageScaffold(
          child: Center(
            child: buildChatImageBubbleForTest('assets/images/chat_photo.png'),
          ),
        ),
      ),
    );

    await tester.tap(find.byType(GestureDetector).first);
    await tester.pumpAndSettle();

    expect(find.byKey(const ValueKey('chat-image-viewer')), findsOneWidget);
  });

  testWidgets('failed outgoing media bubble exposes retry action', (
    tester,
  ) async {
    var retried = false;

    await tester.pumpWidget(
      CupertinoApp(
        home: CupertinoPageScaffold(
          child: Center(
            child: buildChatMessageBubbleForTest(
              message: const ChatMessage(
                side: ChatBubbleSide.me,
                type: ChatMessageType.audio,
                time: '12:00',
                asset: 'voice.m4a',
                duration: Duration(seconds: 3),
                deliveryStatus: 'failed',
              ),
              onRetry: () => retried = true,
            ),
          ),
        ),
      ),
    );

    expect(find.text('Tekrar dene'), findsOneWidget);

    await tester.tap(find.text('Tekrar dene'));
    await tester.pump();

    expect(retried, isTrue);
  });

  testWidgets('short message lists stay anchored near the composer', (
    tester,
  ) async {
    await tester.pumpWidget(
      CupertinoApp(
        home: CupertinoPageScaffold(
          child: SizedBox(
            height: 420,
            child: buildBottomAlignedChatMessagesForTest(
              children: const [
                SizedBox(
                  key: ValueKey('short-chat-message'),
                  height: 40,
                  child: ColoredBox(color: CupertinoColors.activeBlue),
                ),
              ],
            ),
          ),
        ),
      ),
    );

    final rect = tester.getRect(
      find.byKey(const ValueKey('short-chat-message')),
    );
    expect(rect.bottom, greaterThan(360));

    final list = tester.widget<ListView>(find.byType(ListView));
    expect(list.physics, isA<RangeMaintainingScrollPhysics>());
    expect(
      (list.physics! as RangeMaintainingScrollPhysics).parent,
      isA<ClampingScrollPhysics>(),
    );
  });

  testWidgets('message list keeps keyed item state across viewport changes', (
    tester,
  ) async {
    _StableMessageProbeState.initCount = 0;

    Widget harness(double height) {
      return CupertinoApp(
        home: CupertinoPageScaffold(
          child: SizedBox(
            height: height,
            child: buildBottomAlignedChatMessagesForTest(
              children: const [
                _StableMessageProbe(key: ValueKey('stable-chat-message')),
              ],
            ),
          ),
        ),
      );
    }

    await tester.pumpWidget(harness(420));
    expect(_StableMessageProbeState.initCount, 1);

    await tester.pumpWidget(harness(320));
    await tester.pump();

    expect(_StableMessageProbeState.initCount, 1);
    expect(find.byKey(const ValueKey('stable-chat-message')), findsOneWidget);
  });

  testWidgets('chat message keys prefer client id before server id', (
    tester,
  ) async {
    const withClientId = AppConversationMessage(
      id: 91,
      conversationId: 7,
      senderId: 1,
      senderName: 'Me',
      type: 'metin',
      text: 'Merhaba',
      isRead: false,
      isAiGenerated: false,
      clientMessageId: 'client-abc',
    );
    const withServerId = AppConversationMessage(
      id: 92,
      conversationId: 7,
      senderId: 2,
      senderName: 'Peer',
      type: 'metin',
      text: 'Selam',
      isRead: false,
      isAiGenerated: false,
    );

    expect(
      buildConversationMessageKeyForTest(withClientId),
      const ValueKey('chat-message-client-client-abc'),
    );
    expect(
      buildConversationMessageKeyForTest(withServerId),
      const ValueKey('chat-message-server-92'),
    );
  });

  test('message merge collapses local media once server copy arrives', () {
    final createdAt = DateTime.utc(2026, 4, 26, 12);
    final localPhoto = AppConversationMessage(
      id: -101,
      conversationId: 7,
      senderId: 1,
      senderName: 'Me',
      type: 'foto',
      fileUrl: 'C:/tmp/photo.jpg',
      isRead: false,
      isAiGenerated: false,
      clientMessageId: 'client-photo-1',
      deliveryStatus: 'queued',
      createdAt: createdAt,
    );
    final serverPhoto = AppConversationMessage(
      id: 101,
      conversationId: 7,
      senderId: 1,
      senderName: 'Me',
      type: 'foto',
      fileUrl: 'https://example.test/photo.jpg',
      isRead: false,
      isAiGenerated: false,
      clientMessageId: 'client-photo-1',
      createdAt: createdAt.add(const Duration(milliseconds: 1)),
    );
    final localVoice = AppConversationMessage(
      id: -102,
      conversationId: 7,
      senderId: 1,
      senderName: 'Me',
      type: 'ses',
      fileUrl: 'C:/tmp/voice.m4a',
      fileDuration: const Duration(seconds: 3),
      isRead: false,
      isAiGenerated: false,
      clientMessageId: 'client-voice-1',
      deliveryStatus: 'queued',
      createdAt: createdAt.add(const Duration(seconds: 1)),
    );
    final serverVoice = AppConversationMessage(
      id: 102,
      conversationId: 7,
      senderId: 1,
      senderName: 'Me',
      type: 'ses',
      fileUrl: 'https://example.test/voice.m4a',
      fileDuration: const Duration(seconds: 3),
      isRead: false,
      isAiGenerated: false,
      clientMessageId: 'client-voice-1',
      createdAt: createdAt.add(const Duration(seconds: 1, milliseconds: 1)),
    );

    final merged = mergeConversationMessagesForTest(
      [localPhoto, localVoice],
      [serverPhoto, serverVoice],
    );

    expect(merged, hasLength(2));
    expect(merged.map((message) => message.id), [101, 102]);
    expect(merged.map((message) => message.fileUrl), [
      'https://example.test/photo.jpg',
      'https://example.test/voice.m4a',
    ]);

    final staleLocalMerge = mergeConversationMessagesForTest(
      [serverPhoto],
      [localPhoto],
    );
    expect(staleLocalMerge, hasLength(1));
    expect(staleLocalMerge.single.id, 101);
  });

  testWidgets(
    'input dock keeps bottom safe area stable while keyboard changes',
    (tester) async {
      Widget harness(EdgeInsets viewInsets) {
        return MediaQuery(
          data: MediaQueryData(
            size: const Size(390, 640),
            viewPadding: const EdgeInsets.only(bottom: 24),
            padding: const EdgeInsets.only(bottom: 24),
            viewInsets: viewInsets,
          ),
          child: Directionality(
            textDirection: TextDirection.ltr,
            child: Align(
              alignment: Alignment.bottomCenter,
              child: SizedBox(
                width: 390,
                child: buildChatInputDockForTest(
                  child: const SizedBox(
                    key: ValueKey('input-dock-child'),
                    height: 50,
                  ),
                ),
              ),
            ),
          ),
        );
      }

      await tester.pumpWidget(harness(EdgeInsets.zero));
      expect(
        tester.getSize(find.byKey(const ValueKey('chat-input-dock'))).height,
        74,
      );

      await tester.pumpWidget(harness(const EdgeInsets.only(bottom: 280)));
      await tester.pump();

      expect(
        tester.getSize(find.byKey(const ValueKey('chat-input-dock'))).height,
        74,
      );

      await tester.pumpWidget(harness(const EdgeInsets.only(bottom: 12)));
      await tester.pump();

      expect(
        tester.getSize(find.byKey(const ValueKey('chat-input-dock'))).height,
        74,
      );
    },
  );

  testWidgets('empty chat body fills its available width', (tester) async {
    await tester.pumpWidget(
      CupertinoApp(
        home: CupertinoPageScaffold(
          child: SizedBox(
            width: 390,
            height: 620,
            child: buildChatEmptyBodyForTest(),
          ),
        ),
      ),
    );

    expect(find.text('Bir Selam Ver!'), findsOneWidget);
    expect(
      tester.getSize(find.byKey(const ValueKey('chat-empty-body'))).width,
      390,
    );

    final mascotSize = tester.getSize(
      find.byKey(const ValueKey('chat-empty-mascot')),
    );
    expect(mascotSize.width, greaterThan(200));
    expect(mascotSize.width, lessThanOrEqualTo(260));
  });

  testWidgets('empty chat body stays compact when keyboard space is tight', (
    tester,
  ) async {
    await tester.pumpWidget(
      CupertinoApp(
        home: MediaQuery(
          data: const MediaQueryData(
            size: Size(390, 360),
            viewInsets: EdgeInsets.only(bottom: 260),
          ),
          child: CupertinoPageScaffold(
            child: SizedBox(
              width: 390,
              height: 360,
              child: buildChatEmptyBodyForTest(),
            ),
          ),
        ),
      ),
    );

    expect(tester.takeException(), isNull);
    expect(find.text('Bir Selam Ver!'), findsOneWidget);

    final mascotSize = tester.getSize(
      find.byKey(const ValueKey('chat-empty-mascot')),
    );
    expect(mascotSize.width, lessThanOrEqualTo(210));
  });

  testWidgets('passive empty composer keeps normal chat bar width', (
    tester,
  ) async {
    await tester.pumpWidget(
      CupertinoApp(
        home: CupertinoPageScaffold(
          child: Align(
            alignment: Alignment.bottomCenter,
            child: SizedBox(
              width: 390,
              child: buildPassiveChatInputBarForTest(),
            ),
          ),
        ),
      ),
    );

    expect(
      tester.getSize(find.byKey(const ValueKey('chat-input-bar'))).width,
      390,
    );
  });
}

class _StableMessageProbe extends StatefulWidget {
  const _StableMessageProbe({super.key});

  @override
  State<_StableMessageProbe> createState() => _StableMessageProbeState();
}

class _StableMessageProbeState extends State<_StableMessageProbe> {
  static int initCount = 0;

  @override
  void initState() {
    super.initState();
    initCount++;
  }

  @override
  Widget build(BuildContext context) {
    return const SizedBox(
      height: 40,
      child: ColoredBox(color: CupertinoColors.activeBlue),
    );
  }
}
