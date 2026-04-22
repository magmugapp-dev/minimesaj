import 'package:magmug/app_core.dart';
import 'package:magmug/features/match/match_flow.dart';

// =============================================================================

enum ChatBubbleSide { me, them }

enum ChatMessageType { text, image, audio, typing }

@immutable
class ChatMessage {
  final ChatBubbleSide side;
  final ChatMessageType type;
  final String? text;
  final String? asset;
  final Duration? duration;
  final String time;

  const ChatMessage({
    required this.side,
    required this.type,
    required this.time,
    this.text,
    this.asset,
    this.duration,
  });

  const ChatMessage.typing({this.side = ChatBubbleSide.them})
    : type = ChatMessageType.typing,
      text = null,
      asset = null,
      duration = null,
      time = '';
}

@immutable
class ChatPeer {
  final String name;
  final String handle;
  final String status;
  final String avatarAsset;
  final bool online;

  const ChatPeer({
    required this.name,
    required this.handle,
    required this.status,
    required this.avatarAsset,
    this.online = true,
  });
}

const ChatPeer _edaSoyral = ChatPeer(
  name: 'Eda Soyral',
  handle: '@eda.s',
  status: 'Cevrimici',
  avatarAsset: 'assets/images/portrait_eda.png',
  online: true,
);

const List<ChatMessage> _mockChatMessages = [
  ChatMessage(
    side: ChatBubbleSide.them,
    type: ChatMessageType.text,
    text: 'Selam! Dunku gezi nasil gecti?',
    time: '11:37',
  ),
  ChatMessage(
    side: ChatBubbleSide.me,
    type: ChatMessageType.text,
    text: 'Cok guzeldi! Kesinlikle tekrar gitmek istiyorum',
    time: '11:38',
  ),
  ChatMessage(
    side: ChatBubbleSide.them,
    type: ChatMessageType.text,
    text: 'Ben de cok gitmek istiyorum! Fotograflar var mi?',
    time: '11:39',
  ),
  ChatMessage(
    side: ChatBubbleSide.me,
    type: ChatMessageType.image,
    asset: 'assets/images/chat_photo.png',
    time: '11:40',
  ),
  ChatMessage(
    side: ChatBubbleSide.them,
    type: ChatMessageType.text,
    text: 'Gecen haftadan, harika yerlerdi!',
    time: '11:41',
  ),
  ChatMessage(
    side: ChatBubbleSide.me,
    type: ChatMessageType.text,
    text: 'Vay be cok guzel! Bu hafta sonu gidelim mi?',
    time: '11:42',
  ),
  ChatMessage(
    side: ChatBubbleSide.them,
    type: ChatMessageType.audio,
    duration: Duration(seconds: 23),
    time: '11:43',
  ),
  ChatMessage(
    side: ChatBubbleSide.me,
    type: ChatMessageType.audio,
    duration: Duration(seconds: 8),
    time: '11:44',
  ),
  ChatMessage.typing(),
];

// ------ Chat header / app bar -------------------------------------------------

class _ChatAppBar extends StatelessWidget {
  final ChatPeer peer;
  final VoidCallback? onAvatarTap;
  final VoidCallback? onGiftTap;
  final VoidCallback? onMoreTap;

  const _ChatAppBar({
    required this.peer,
    this.onAvatarTap,
    this.onGiftTap,
    this.onMoreTap,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 64,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: const BoxDecoration(
        color: AppColors.white,
        border: Border(bottom: BorderSide(color: Color(0xFFF0F0F0), width: 1)),
      ),
      child: Row(
        children: [
          PressableScale(
            onTap: () => Navigator.of(context).maybePop(),
            scale: 0.9,
            child: const Padding(
              padding: EdgeInsets.all(6),
              child: Icon(
                CupertinoIcons.chevron_back,
                size: 22,
                color: AppColors.black,
              ),
            ),
          ),
          const SizedBox(width: 4),
          PressableScale(
            onTap: onAvatarTap ?? () {},
            scale: 0.95,
            child: SizedBox(
              width: 40,
              height: 40,
              child: Stack(
                children: [
                  ClipOval(
                    child: Image.asset(
                      peer.avatarAsset,
                      width: 40,
                      height: 40,
                      fit: BoxFit.cover,
                    ),
                  ),
                  if (peer.online)
                    Positioned(
                      right: 0,
                      bottom: 0,
                      child: Container(
                        width: 12,
                        height: 12,
                        decoration: BoxDecoration(
                          color: const Color(0xFF2DD4A0),
                          shape: BoxShape.circle,
                          border: Border.all(color: AppColors.white, width: 2),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: PressableScale(
              onTap: onAvatarTap ?? () {},
              scale: 0.99,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    peer.name,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      color: AppColors.black,
                    ),
                  ),
                  Text(
                    peer.status,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w600,
                      fontSize: 11.5,
                      color: Color(0xFF2DD4A0),
                    ),
                  ),
                ],
              ),
            ),
          ),
          _GiftStarChip(onTap: onGiftTap ?? () {}),
          const SizedBox(width: 8),
          PressableScale(
            onTap: onMoreTap ?? () {},
            scale: 0.92,
            child: Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: AppColors.grayField,
                borderRadius: BorderRadius.circular(19),
              ),
              alignment: Alignment.center,
              child: const Icon(
                CupertinoIcons.ellipsis_vertical,
                size: 18,
                color: AppColors.black,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _GiftStarChip extends StatelessWidget {
  final VoidCallback onTap;

  const _GiftStarChip({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.92,
      child: Container(
        width: 38,
        height: 38,
        decoration: const BoxDecoration(
          shape: BoxShape.circle,
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFFA594F9), Color(0xFF7C6DF5)],
          ),
          boxShadow: [
            BoxShadow(
              color: Color(0x3C7C6DF5),
              blurRadius: 10,
              offset: Offset(0, 3),
            ),
          ],
        ),
        alignment: Alignment.center,
        child: Transform.rotate(
          angle: -0.35,
          child: const Text('⭐', style: TextStyle(fontSize: 19, height: 1.0)),
        ),
      ),
    );
  }
}

// ------ Chat bubbles ----------------------------------------------------------

class _MessageBubble extends StatelessWidget {
  final ChatMessage message;
  final String avatarAsset;

  const _MessageBubble({required this.message, required this.avatarAsset});

  @override
  Widget build(BuildContext context) {
    final isMe = message.side == ChatBubbleSide.me;

    Widget content;
    switch (message.type) {
      case ChatMessageType.typing:
        content = const _TypingBubble();
      case ChatMessageType.image:
        content = _ImageBubble(asset: message.asset!);
      case ChatMessageType.audio:
        content = _AudioBubble(
          isMe: isMe,
          duration: message.duration ?? const Duration(),
        );
      case ChatMessageType.text:
        content = _TextBubble(isMe: isMe, text: message.text ?? '');
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 6, 16, 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        mainAxisAlignment: isMe
            ? MainAxisAlignment.end
            : MainAxisAlignment.start,
        children: [
          if (!isMe) ...[
            ClipOval(
              child: Image.asset(
                avatarAsset,
                width: 30,
                height: 30,
                fit: BoxFit.cover,
              ),
            ),
            const SizedBox(width: 8),
          ],
          Flexible(
            child: Column(
              crossAxisAlignment: isMe
                  ? CrossAxisAlignment.end
                  : CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                content,
                if (message.time.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Text(
                      message.time,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontSize: 10,
                        color: Color(0xFFCCCCCC),
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _TextBubble extends StatelessWidget {
  final bool isMe;
  final String text;

  const _TextBubble({required this.isMe, required this.text});

  @override
  Widget build(BuildContext context) {
    final bg = isMe
        ? const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF5C6BFF), Color(0xFF7B6FFF)],
            ),
          )
        : const BoxDecoration(color: AppColors.grayField);
    final radius = isMe
        ? const BorderRadius.only(
            topLeft: Radius.circular(20),
            topRight: Radius.circular(20),
            bottomLeft: Radius.circular(20),
            bottomRight: Radius.circular(6),
          )
        : const BorderRadius.only(
            topLeft: Radius.circular(20),
            topRight: Radius.circular(20),
            bottomLeft: Radius.circular(6),
            bottomRight: Radius.circular(20),
          );
    return Container(
      constraints: BoxConstraints(
        maxWidth: MediaQuery.sizeOf(context).width * 0.68,
      ),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: bg.copyWith(borderRadius: radius),
      child: Text(
        text,
        style: TextStyle(
          fontFamily: AppFont.family,
          fontWeight: FontWeight.w500,
          fontSize: 15,
          height: 1.35,
          color: isMe ? AppColors.white : AppColors.black,
        ),
      ),
    );
  }
}

class _ImageBubble extends StatelessWidget {
  final String asset;

  const _ImageBubble({required this.asset});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: Image.asset(
        asset,
        width: MediaQuery.sizeOf(context).width * 0.62,
        fit: BoxFit.cover,
      ),
    );
  }
}

class _AudioBubble extends StatelessWidget {
  final bool isMe;
  final Duration duration;

  const _AudioBubble({required this.isMe, required this.duration});

  static const List<double> _bars = [
    0.35,
    0.55,
    0.7,
    0.9,
    0.65,
    0.4,
    0.15,
    0.15,
    0.1,
    0.35,
    0.55,
    0.8,
    0.65,
    0.75,
    0.7,
    0.45,
    0.1,
    0.25,
    0.4,
    0.75,
    0.85,
    1.0,
    0.85,
  ];

  @override
  Widget build(BuildContext context) {
    final bg = isMe
        ? const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF5C6BFF), Color(0xFF7B6FFF)],
            ),
          )
        : const BoxDecoration(color: AppColors.grayField);
    final radius = isMe
        ? const BorderRadius.only(
            topLeft: Radius.circular(20),
            topRight: Radius.circular(20),
            bottomLeft: Radius.circular(20),
            bottomRight: Radius.circular(6),
          )
        : const BorderRadius.only(
            topLeft: Radius.circular(20),
            topRight: Radius.circular(20),
            bottomLeft: Radius.circular(6),
            bottomRight: Radius.circular(20),
          );
    final minutes = duration.inMinutes.remainder(60).toString().padLeft(1, '0');
    final seconds = duration.inSeconds.remainder(60).toString().padLeft(2, '0');
    final playBg = isMe
        ? AppColors.white.withValues(alpha: 0.2)
        : AppColors.black;
    final barColor = isMe
        ? AppColors.white.withValues(alpha: 0.45)
        : const Color(0xFFC4C4C4);
    final timeColor = isMe
        ? AppColors.white.withValues(alpha: 0.6)
        : const Color(0xFF999999);

    return Container(
      width: 216,
      height: 54,
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: bg.copyWith(borderRadius: radius),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: playBg,
              borderRadius: BorderRadius.circular(17),
            ),
            alignment: Alignment.center,
            child: Icon(
              CupertinoIcons.play_fill,
              size: 14,
              color: AppColors.white,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: CustomPaint(
              size: const Size.fromHeight(22),
              painter: _WaveformPainter(bars: _bars, color: barColor),
            ),
          ),
          const SizedBox(width: 8),
          Text(
            '$minutes:$seconds',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w700,
              fontSize: 12,
              color: timeColor,
            ),
          ),
        ],
      ),
    );
  }
}

class _WaveformPainter extends CustomPainter {
  final List<double> bars;
  final Color color;

  _WaveformPainter({required this.bars, required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeCap = StrokeCap.round
      ..strokeWidth = 2.5;
    final gap = size.width / bars.length;
    final midY = size.height / 2;
    for (var i = 0; i < bars.length; i++) {
      final h = bars[i] * size.height;
      final x = i * gap + gap / 2;
      canvas.drawLine(Offset(x, midY - h / 2), Offset(x, midY + h / 2), paint);
    }
  }

  @override
  bool shouldRepaint(covariant _WaveformPainter old) =>
      old.color != color || old.bars != bars;
}

class _TypingBubble extends StatefulWidget {
  const _TypingBubble();

  @override
  State<_TypingBubble> createState() => _TypingBubbleState();
}

class _TypingBubbleState extends State<_TypingBubble>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 35,
      width: 67,
      decoration: BoxDecoration(
        color: AppColors.grayField,
        borderRadius: BorderRadius.circular(20),
      ),
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) {
          return Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(3, (i) {
              final t = ((_controller.value + i * 0.2) % 1.0);
              final scale = 0.6 + (1.0 - (t - 0.5).abs() * 2).clamp(0, 1) * 0.6;
              return Padding(
                padding: const EdgeInsets.symmetric(horizontal: 2.5),
                child: Transform.scale(
                  scale: scale.toDouble(),
                  child: Container(
                    width: 7,
                    height: 7,
                    decoration: const BoxDecoration(
                      color: Color(0xFFCCCCCC),
                      shape: BoxShape.circle,
                    ),
                  ),
                ),
              );
            }),
          );
        },
      ),
    );
  }
}

class _DaySeparator extends StatelessWidget {
  final String label;

  const _DaySeparator({required this.label});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Center(
        child: Text(
          label,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w600,
            fontSize: 11,
            color: Color(0xFFCCCCCC),
          ),
        ),
      ),
    );
  }
}

// ------ Input bar -------------------------------------------------------------

enum ChatInputVariant { empty, full }

class _ChatInputBar extends StatelessWidget {
  final ChatInputVariant variant;

  const _ChatInputBar({this.variant = ChatInputVariant.empty});

  @override
  Widget build(BuildContext context) {
    final isEmpty = variant == ChatInputVariant.empty;
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 16),
      decoration: const BoxDecoration(color: AppColors.white),
      child: Row(
        children: [
          _circleButton(
            icon: isEmpty ? CupertinoIcons.camera : CupertinoIcons.photo,
            bg: AppColors.grayField,
            iconColor: AppColors.black,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Container(
              height: 44,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              decoration: BoxDecoration(
                color: AppColors.grayField,
                borderRadius: BorderRadius.circular(24),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      isEmpty ? 'Message...' : 'Mesaj yaz...',
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w500,
                        fontSize: 14.5,
                        color: Color(0xFF999999),
                      ),
                    ),
                  ),
                  if (isEmpty) ...[
                    const SizedBox(width: 8),
                    const Icon(
                      CupertinoIcons.paperclip,
                      size: 18,
                      color: Color(0xFF999999),
                    ),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(width: 8),
          _circleButton(
            icon: CupertinoIcons.mic_fill,
            bg: const Color(0xFF1A1A1A),
            iconColor: AppColors.white,
          ),
        ],
      ),
    );
  }

  Widget _circleButton({
    required IconData icon,
    required Color bg,
    required Color iconColor,
  }) {
    return Container(
      width: 44,
      height: 44,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(22),
      ),
      alignment: Alignment.center,
      child: Icon(icon, size: 19, color: iconColor),
    );
  }
}

// ------ Chat Screen -----------------------------------------------------------

enum ChatScreenMode { empty, messages }

class ChatScreen extends StatelessWidget {
  final ChatScreenMode mode;

  const ChatScreen({super.key, this.mode = ChatScreenMode.messages});

  @override
  Widget build(BuildContext context) {
    void openProfile() {
      Navigator.of(context).push(cupertinoRoute(const ChatProfileScreen()));
    }

    void openGift() {
      showCupertinoModalPopup<void>(
        context: context,
        builder: (_) => const GiftSheet(),
      );
    }

    void openMore() {
      showCupertinoModalPopup<void>(
        context: context,
        builder: (ctx) => CupertinoActionSheet(
          actions: [
            CupertinoActionSheetAction(
              onPressed: () {
                Navigator.of(ctx).pop();
                showCupertinoModalPopup<void>(
                  context: context,
                  builder: (_) => const ReportSheet(),
                );
              },
              child: const Text(
                'Sikayet Et',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  color: Color(0xFFEF4444),
                ),
              ),
            ),
            CupertinoActionSheetAction(
              isDestructiveAction: true,
              onPressed: () {
                Navigator.of(ctx).pop();
                showCupertinoModalPopup<void>(
                  context: context,
                  builder: (_) => const BlockConfirmSheet(),
                );
              },
              child: const Text(
                'Engelle',
                style: TextStyle(fontFamily: AppFont.family),
              ),
            ),
          ],
          cancelButton: CupertinoActionSheetAction(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text(
              'Vazgec',
              style: TextStyle(fontFamily: AppFont.family),
            ),
          ),
        ),
      );
    }

    return CupertinoPageScaffold(
      backgroundColor: AppColors.white,
      child: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _ChatAppBar(
              peer: _edaSoyral,
              onAvatarTap: openProfile,
              onGiftTap: openGift,
              onMoreTap: openMore,
            ),
            Expanded(
              child: mode == ChatScreenMode.empty
                  ? const _ChatEmptyBody()
                  : const _ChatMessagesBody(),
            ),
            _ChatInputBar(
              variant: mode == ChatScreenMode.empty
                  ? ChatInputVariant.empty
                  : ChatInputVariant.full,
            ),
            SizedBox(height: MediaQuery.paddingOf(context).bottom),
          ],
        ),
      ),
    );
  }
}

class _ChatEmptyBody extends StatelessWidget {
  const _ChatEmptyBody();

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        const Spacer(),
        Image.asset(
          'assets/images/hello_mascot.png',
          width: 220,
          height: 260,
          fit: BoxFit.contain,
        ),
        const SizedBox(height: 8),
        const Text(
          'Bir Selam Ver!',
          style: TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w800,
            fontSize: 17,
            color: AppColors.black,
          ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Hadi bu firsati kacirma...',
          style: TextStyle(
            fontFamily: AppFont.family,
            fontSize: 13.5,
            height: 1.5,
            color: Color(0xFF666666),
          ),
        ),
        const Spacer(flex: 2),
      ],
    );
  }
}

class _ChatMessagesBody extends StatelessWidget {
  const _ChatMessagesBody();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.only(top: 16, bottom: 16),
      itemCount: _mockChatMessages.length + 1,
      itemBuilder: (context, i) {
        if (i == 0) return const _DaySeparator(label: 'Bugun');
        final msg = _mockChatMessages[i - 1];
        return _MessageBubble(
          message: msg,
          avatarAsset: _edaSoyral.avatarAsset,
        );
      },
    );
  }
}

// ------ Chat Profile Screen ---------------------------------------------------

class ChatProfileScreen extends StatefulWidget {
  const ChatProfileScreen({super.key});

  @override
  State<ChatProfileScreen> createState() => _ChatProfileScreenState();
}

class _ChatProfileScreenState extends State<ChatProfileScreen> {
  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      backgroundColor: AppColors.neutral100,
      child: SafeArea(
        child: ListView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
          children: [
            Row(
              children: [
                PressableScale(
                  onTap: () => Navigator.of(context).maybePop(),
                  scale: 0.9,
                  child: const Padding(
                    padding: EdgeInsets.all(8),
                    child: Icon(
                      CupertinoIcons.chevron_back,
                      size: 22,
                      color: AppColors.black,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Center(
              child: Container(
                width: 90,
                height: 90,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: Color(0x14000000),
                      blurRadius: 16,
                      offset: Offset(0, 4),
                    ),
                  ],
                ),
                child: ClipOval(
                  child: Image.asset(_edaSoyral.avatarAsset, fit: BoxFit.cover),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Center(
              child: Text(
                _edaSoyral.name,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w800,
                  fontSize: 20,
                  color: AppColors.black,
                  letterSpacing: -0.5,
                ),
              ),
            ),
            const SizedBox(height: 2),
            Center(
              child: Text(
                _edaSoyral.handle,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 13,
                  color: Color(0xFF999999),
                ),
              ),
            ),
            const SizedBox(height: 4),
            Center(
              child: Text(
                _edaSoyral.status,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w700,
                  fontSize: 12,
                  color: Color(0xFF2DD4A0),
                ),
              ),
            ),
            const SizedBox(height: 20),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                _ProfileActionChip(
                  label: 'Ozel Emoji',
                  gradient: true,
                  icon: const Text('⭐', style: TextStyle(fontSize: 22)),
                  onTap: () {},
                ),
                const SizedBox(width: 24),
                _ProfileActionChip(
                  label: 'Sessize Al',
                  gradient: false,
                  icon: const Icon(
                    CupertinoIcons.bell,
                    size: 20,
                    color: AppColors.black,
                  ),
                  onTap: () {},
                ),
              ],
            ),
            const SizedBox(height: 24),
            const _MediaTabBar(),
            const SizedBox(height: 12),
            const _MediaGrid(),
            const SizedBox(height: 12),
            const _ShowAllButton(),
            const SizedBox(height: 24),
            const _ChatThemeSection(),
            const SizedBox(height: 12),
            const _ShowAllButton(),
            const SizedBox(height: 24),
            _DangerListCard(
              onBlock: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const BlockConfirmSheet(),
              ),
              onReport: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const ReportSheet(),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProfileActionChip extends StatelessWidget {
  final String label;
  final Widget icon;
  final bool gradient;
  final VoidCallback onTap;

  const _ProfileActionChip({
    required this.label,
    required this.icon,
    required this.gradient,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        PressableScale(
          onTap: onTap,
          scale: 0.92,
          child: Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              gradient: gradient
                  ? const LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [Color(0xFFA594F9), Color(0xFF7C6DF5)],
                    )
                  : null,
              color: gradient ? null : const Color(0xFFEEEEF0),
              shape: BoxShape.circle,
              boxShadow: gradient
                  ? const [
                      BoxShadow(
                        color: Color(0x3C7C6DF5),
                        blurRadius: 10,
                        offset: Offset(0, 3),
                      ),
                    ]
                  : null,
            ),
            alignment: Alignment.center,
            child: gradient
                ? Transform.rotate(angle: -0.35, child: icon)
                : icon,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          label,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w600,
            fontSize: 11,
            color: Color(0xFF555555),
          ),
        ),
      ],
    );
  }
}

class _MediaTabBar extends StatelessWidget {
  const _MediaTabBar();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 32),
      padding: const EdgeInsets.all(4),
      height: 42,
      decoration: BoxDecoration(
        color: const Color(0xFFE8E8EA),
        borderRadius: BorderRadius.circular(80),
      ),
      child: Row(
        children: const [
          _MediaTabItem(label: 'Tumu', selected: true),
          _MediaTabItem(label: 'Fotograflar', selected: false),
          _MediaTabItem(label: 'Videolar', selected: false),
        ],
      ),
    );
  }
}

class _MediaTabItem extends StatelessWidget {
  final String label;
  final bool selected;

  const _MediaTabItem({required this.label, required this.selected});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        height: 34,
        decoration: BoxDecoration(
          color: selected ? AppColors.white : const Color(0x00000000),
          borderRadius: BorderRadius.circular(80),
          boxShadow: selected
              ? const [
                  BoxShadow(
                    color: Color(0x0F000000),
                    blurRadius: 4,
                    offset: Offset(0, 1),
                  ),
                ]
              : null,
        ),
        alignment: Alignment.center,
        child: Text(
          label,
          style: TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w600,
            fontSize: 13,
            color: selected ? AppColors.black : const Color(0xFF999999),
          ),
        ),
      ),
    );
  }
}

class _MediaGrid extends StatelessWidget {
  const _MediaGrid();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(6),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(20),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(14),
        child: GridView.count(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisCount: 3,
          mainAxisSpacing: 2,
          crossAxisSpacing: 2,
          children: [
            _galleryTile('assets/images/gallery_1.png'),
            _galleryTile('assets/images/gallery_2.png'),
            _galleryTile('assets/images/gallery_3.png', videoLabel: '0:34'),
            _galleryTile('assets/images/gallery_4.png'),
            _galleryTile('assets/images/gallery_5.png', videoLabel: '1:12'),
            _galleryTile('assets/images/gallery_6.png'),
          ],
        ),
      ),
    );
  }

  Widget _galleryTile(String asset, {String? videoLabel}) {
    return Stack(
      fit: StackFit.expand,
      children: [
        Image.asset(asset, fit: BoxFit.cover),
        if (videoLabel != null)
          Positioned(
            left: 6,
            bottom: 6,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(
                color: const Color(0x8C000000),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(
                    CupertinoIcons.play_fill,
                    size: 9,
                    color: AppColors.white,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    videoLabel,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w500,
                      fontSize: 10,
                      color: AppColors.white,
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class _ShowAllButton extends StatelessWidget {
  const _ShowAllButton();

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: () {},
      scale: 0.99,
      child: Container(
        height: 42,
        decoration: BoxDecoration(
          color: AppColors.grayField,
          borderRadius: BorderRadius.circular(14),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: const [
            Text(
              'Tumunu Goster',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w500,
                fontSize: 13,
                color: Color(0xFF555555),
              ),
            ),
            SizedBox(width: 8),
            Icon(
              CupertinoIcons.chevron_down,
              size: 14,
              color: Color(0xFF555555),
            ),
          ],
        ),
      ),
    );
  }
}

class _ChatThemeSection extends StatefulWidget {
  const _ChatThemeSection();

  @override
  State<_ChatThemeSection> createState() => _ChatThemeSectionState();
}

class _ChatThemeSectionState extends State<_ChatThemeSection> {
  int _selected = 0;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 18),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Sohbet Temasi',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w700,
              fontSize: 16,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Mesajlasma gorunumunu degistir',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontSize: 12,
              color: Color(0xFF999999),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: List.generate(3, (i) {
              return Expanded(
                child: Padding(
                  padding: EdgeInsets.only(right: i == 2 ? 0 : 8),
                  child: _ThemeCard(
                    index: i,
                    selected: _selected == i,
                    onTap: () => setState(() => _selected = i),
                  ),
                ),
              );
            }),
          ),
        ],
      ),
    );
  }
}

class _ThemeCard extends StatelessWidget {
  final int index;
  final bool selected;
  final VoidCallback onTap;

  const _ThemeCard({
    required this.index,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final labels = ['Varsayilan', 'Karanlik', 'Gunbatimi'];
    return PressableScale(
      onTap: onTap,
      scale: 0.97,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        height: 150,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? AppColors.indigo : const Color(0xFFE8E8E8),
            width: 2,
          ),
          boxShadow: selected
              ? const [
                  BoxShadow(
                    color: Color(0x1F5C6BFF),
                    blurRadius: 12,
                    offset: Offset(0, 4),
                  ),
                ]
              : null,
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(14),
          child: Column(
            children: [
              Expanded(
                child: CustomPaint(
                  painter: _ThemePreviewPainter(
                    index: index,
                    showCheck: selected && index == 0,
                  ),
                  size: Size.infinite,
                ),
              ),
              Container(
                height: 26,
                color: index == 1
                    ? const Color(0xFF111118)
                    : (index == 2 ? const Color(0xFFFFB8C6) : AppColors.white),
                alignment: Alignment.center,
                child: Text(
                  labels[index],
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 10,
                    color: index == 1
                        ? const Color(0xB3FFFFFF)
                        : (index == 2 ? AppColors.white : AppColors.black),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ThemePreviewPainter extends CustomPainter {
  final int index;
  final bool showCheck;

  _ThemePreviewPainter({required this.index, required this.showCheck});

  @override
  void paint(Canvas canvas, Size size) {
    final bgPaint = Paint();
    if (index == 1) {
      bgPaint.color = const Color(0xFF111118);
    } else if (index == 2) {
      bgPaint.shader = const LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [Color(0xFF6C63FF), Color(0xFFFFB8C6)],
      ).createShader(Offset.zero & size);
    } else {
      bgPaint.color = AppColors.white;
    }
    canvas.drawRect(Offset.zero & size, bgPaint);

    final leftBubbleColor = index == 1
        ? const Color(0xFF222230)
        : (index == 2
              ? AppColors.white.withValues(alpha: 0.7)
              : const Color(0xFFF0F0F0));
    final rightBubbleShader = const LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: [Color(0xFF5C6BFF), Color(0xFF7B6FFF)],
    );

    final bar1 = Rect.fromLTWH(7, size.height - 46, size.width - 42, 10);
    canvas.drawRRect(
      RRect.fromRectAndCorners(
        bar1,
        topLeft: const Radius.circular(7),
        topRight: const Radius.circular(7),
        bottomLeft: const Radius.circular(3),
        bottomRight: const Radius.circular(7),
      ),
      Paint()..color = leftBubbleColor,
    );
    final bar2 = Rect.fromLTWH(
      size.width / 2 + 6,
      size.height - 33,
      size.width / 2 - 13,
      10,
    );
    final p2 = Paint();
    if (index == 2) {
      p2.color = AppColors.white.withValues(alpha: 0.3);
    } else {
      p2.shader = rightBubbleShader.createShader(bar2);
    }
    canvas.drawRRect(
      RRect.fromRectAndCorners(
        bar2,
        topLeft: const Radius.circular(7),
        topRight: const Radius.circular(7),
        bottomLeft: const Radius.circular(7),
        bottomRight: const Radius.circular(3),
      ),
      p2,
    );
    final bar3 = Rect.fromLTWH(7, size.height - 20, size.width - 66, 10);
    canvas.drawRRect(
      RRect.fromRectAndCorners(
        bar3,
        topLeft: const Radius.circular(7),
        topRight: const Radius.circular(7),
        bottomLeft: const Radius.circular(3),
        bottomRight: const Radius.circular(7),
      ),
      Paint()..color = leftBubbleColor,
    );

    if (showCheck) {
      final checkBg = Paint()..color = AppColors.indigo;
      canvas.drawCircle(Offset(size.width - 15, 15), 9, checkBg);
      final checkPaint = Paint()
        ..color = AppColors.white
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.5
        ..strokeCap = StrokeCap.round;
      final path = Path()
        ..moveTo(size.width - 19, 15)
        ..lineTo(size.width - 16, 18)
        ..lineTo(size.width - 11, 12);
      canvas.drawPath(path, checkPaint);
    }
  }

  @override
  bool shouldRepaint(covariant _ThemePreviewPainter old) =>
      old.index != index || old.showCheck != showCheck;
}

class _DangerListCard extends StatelessWidget {
  final VoidCallback onBlock;
  final VoidCallback onReport;

  const _DangerListCard({required this.onBlock, required this.onReport});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        children: [
          _DangerListRow(
            icon: CupertinoIcons.nosign,
            label: 'Engelle',
            onTap: onBlock,
          ),
          Container(
            height: 1,
            margin: const EdgeInsets.symmetric(horizontal: 20),
            color: const Color(0xFFF0F0F0),
          ),
          _DangerListRow(
            icon: CupertinoIcons.exclamationmark_triangle,
            label: 'Sikayet Et',
            onTap: onReport,
          ),
        ],
      ),
    );
  }
}

class _DangerListRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _DangerListRow({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.99,
      child: Container(
        height: 52,
        padding: const EdgeInsets.symmetric(horizontal: 20),
        alignment: Alignment.centerLeft,
        child: Row(
          children: [
            Icon(icon, size: 18, color: const Color(0xFFEF4444)),
            const SizedBox(width: 12),
            Text(
              label,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w600,
                fontSize: 14.5,
                color: Color(0xFFEF4444),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ------ Sheet: Gift -----------------------------------------------------------

@immutable
class _GiftItem {
  final String emoji;
  final String name;
  final int cost;

  const _GiftItem(this.emoji, this.name, this.cost);
}

class GiftSheet extends ConsumerStatefulWidget {
  const GiftSheet({super.key});

  @override
  ConsumerState<GiftSheet> createState() => _GiftSheetState();
}

class _GiftSheetState extends ConsumerState<GiftSheet> {
  int _selectedGift = 1;
  int _selectedCategory = 0;

  static const List<String> _categories = [
    'Populer',
    'Romantik',
    'Eglenceli',
    'Ozel',
  ];

  static const List<_GiftItem> _gifts = [
    _GiftItem('🌹', 'Gul', 5),
    _GiftItem('💝', 'Kalp Kutu', 10),
    _GiftItem('🧸', 'Ayi', 15),
    _GiftItem('🍫', 'Cikolata', 8),
    _GiftItem('💍', 'Yuzuk', 50),
    _GiftItem('☕', 'Kahve', 3),
    _GiftItem('💐', 'Buket', 20),
    _GiftItem('⭐', 'Yildiz', 7),
    _GiftItem('👑', 'Tac', 30),
  ];

  @override
  Widget build(BuildContext context) {
    final gem = ref.watch(matchProvider.select((s) => s.gemBalance));
    final selectedCost = _gifts[_selectedGift].cost;

    return Container(
      decoration: const BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 48,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFFD4D4D4),
                borderRadius: BorderRadius.circular(8),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: const [
                    Text(
                      'Hediye Gonder',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 20,
                        color: AppColors.black,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      "Anna'ya ozel bir hediye sec",
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontSize: 12.5,
                        color: Color(0xFF999999),
                      ),
                    ),
                  ],
                ),
              ),
              BalanceChip(amount: gem),
            ],
          ),
          const SizedBox(height: 16),
          SizedBox(
            height: 36,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _categories.length,
              separatorBuilder: (context, index) => const SizedBox(width: 8),
              itemBuilder: (context, i) {
                final selected = _selectedCategory == i;
                return PressableScale(
                  onTap: () => setState(() => _selectedCategory = i),
                  scale: 0.95,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: selected ? AppColors.black : AppColors.grayField,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      _categories[i],
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                        color: selected
                            ? AppColors.white
                            : const Color(0xFF666666),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 16),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 3,
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: 0.92,
            ),
            itemCount: _gifts.length,
            itemBuilder: (context, i) {
              final selected = _selectedGift == i;
              return _GiftTile(
                gift: _gifts[i],
                selected: selected,
                onTap: () => setState(() => _selectedGift = i),
              );
            },
          ),
          const SizedBox(height: 20),
          Container(height: 1, color: const Color(0xFFF0F0F0)),
          const SizedBox(height: 16),
          _SendGiftButton(cost: selectedCost),
        ],
      ),
    );
  }
}

class _GiftTile extends StatelessWidget {
  final _GiftItem gift;
  final bool selected;
  final VoidCallback onTap;

  const _GiftTile({
    required this.gift,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.96,
      child: Stack(
        children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: selected ? const Color(0x145C6BFF) : AppColors.grayField,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: selected ? AppColors.indigo : const Color(0x00000000),
                width: 1.5,
              ),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  gift.emoji,
                  style: const TextStyle(fontSize: 34, height: 1.1),
                ),
                const SizedBox(height: 6),
                Text(
                  gift.name,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                    color: AppColors.black,
                  ),
                ),
                const SizedBox(height: 4),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(
                      CupertinoIcons.bolt_fill,
                      size: 12,
                      color: AppColors.indigo,
                    ),
                    const SizedBox(width: 2),
                    Text(
                      '${gift.cost}',
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w700,
                        fontSize: 12,
                        color: AppColors.indigo,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (selected)
            const Positioned(
              top: 6,
              right: 6,
              child: Icon(
                CupertinoIcons.check_mark_circled_solid,
                size: 18,
                color: AppColors.indigo,
              ),
            ),
        ],
      ),
    );
  }
}

class _SendGiftButton extends StatelessWidget {
  final int cost;

  const _SendGiftButton({required this.cost});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: () => Navigator.of(context).maybePop(),
      child: Container(
        height: 56,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        decoration: BoxDecoration(
          gradient: AppColors.primary,
          borderRadius: BorderRadius.circular(40),
          boxShadow: const [
            BoxShadow(
              color: AppColors.shadow,
              blurRadius: 24,
              offset: Offset(0, 8),
            ),
          ],
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Text(
              'Gonder',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 16,
                color: AppColors.white,
              ),
            ),
            const SizedBox(width: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: AppColors.white,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Image.asset(
                    'assets/images/icon_diamond.png',
                    width: 14,
                    height: 14,
                  ),
                  const SizedBox(width: 6),
                  Text(
                    '$cost tas',
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                      color: AppColors.zinc900,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ------ Sheet: Report ---------------------------------------------------------

class ReportSheet extends StatefulWidget {
  const ReportSheet({super.key});

  @override
  State<ReportSheet> createState() => _ReportSheetState();
}

class _ReportSheetState extends State<ReportSheet> {
  int? _selected;

  static const List<String> _options = [
    'Uygunsuz icerik',
    'Sahte profil',
    'Taciz veya zorbalik',
    'Diger',
  ];

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 48,
            height: 4,
            decoration: BoxDecoration(
              color: const Color(0xFFD4D4D4),
              borderRadius: BorderRadius.circular(8),
            ),
          ),
          const SizedBox(height: 20),
          const Text(
            'Sikayet Et',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 18,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Bir veya birden fazla sebep secin',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontSize: 13,
              color: Color(0xFF999999),
            ),
          ),
          const SizedBox(height: 16),
          ...List.generate(
            _options.length,
            (i) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: _ReportOption(
                label: _options[i],
                selected: _selected == i,
                onTap: () => setState(() => _selected = i),
              ),
            ),
          ),
          const SizedBox(height: 16),
          PressableScale(
            onTap: _selected == null
                ? null
                : () => Navigator.of(context).maybePop(),
            child: Container(
              height: 52,
              decoration: BoxDecoration(
                color: _selected == null
                    ? const Color(0xFFEEEEEE)
                    : const Color(0xFFEF4444),
                borderRadius: BorderRadius.circular(26),
              ),
              alignment: Alignment.center,
              child: Text(
                'Sikayet Et',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w700,
                  fontSize: 15,
                  color: _selected == null
                      ? const Color(0xFF999999)
                      : AppColors.white,
                ),
              ),
            ),
          ),
          const SizedBox(height: 8),
          PressableScale(
            onTap: () => Navigator.of(context).maybePop(),
            scale: 0.99,
            child: Container(
              height: 44,
              alignment: Alignment.center,
              child: const Text(
                'Vazgec',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w600,
                  fontSize: 14,
                  color: Color(0xFF666666),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ReportOption extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _ReportOption({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.99,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        height: 52,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(
          color: AppColors.grayField,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? AppColors.indigo : const Color(0x00000000),
            width: 1.5,
          ),
        ),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w600,
                  fontSize: 14.5,
                  color: AppColors.black,
                ),
              ),
            ),
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 180),
              transitionBuilder: (child, anim) =>
                  ScaleTransition(scale: anim, child: child),
              child: selected
                  ? Container(
                      key: const ValueKey('on'),
                      width: 22,
                      height: 22,
                      decoration: const BoxDecoration(
                        color: AppColors.indigo,
                        shape: BoxShape.circle,
                      ),
                      alignment: Alignment.center,
                      child: const Icon(
                        CupertinoIcons.check_mark,
                        size: 12,
                        color: AppColors.white,
                      ),
                    )
                  : Container(
                      key: const ValueKey('off'),
                      width: 22,
                      height: 22,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: const Color(0xFFD4D4D4),
                          width: 1.5,
                        ),
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

// ------ Sheet: Block Confirm --------------------------------------------------

class BlockConfirmSheet extends StatelessWidget {
  const BlockConfirmSheet({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: const EdgeInsets.fromLTRB(24, 12, 24, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 48,
            height: 4,
            decoration: BoxDecoration(
              color: const Color(0xFFD4D4D4),
              borderRadius: BorderRadius.circular(8),
            ),
          ),
          const SizedBox(height: 24),
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: const Color(0x1FEF4444),
              shape: BoxShape.circle,
            ),
            alignment: Alignment.center,
            child: const Icon(
              CupertinoIcons.nosign,
              size: 28,
              color: Color(0xFFEF4444),
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            'Engellemek istediginize emin misiniz?',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w700,
              fontSize: 17,
              color: AppColors.black,
              height: 1.3,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Eda Soyral size mesaj gonderemeyecek ve profilinizi goremeyecek.',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontFamily: AppFont.family,
              fontSize: 13.5,
              height: 1.5,
              color: Color(0xFF666666),
            ),
          ),
          const SizedBox(height: 24),
          PressableScale(
            onTap: () => Navigator.of(context).maybePop(),
            child: Container(
              height: 52,
              width: double.infinity,
              decoration: BoxDecoration(
                color: const Color(0xFFEF4444),
                borderRadius: BorderRadius.circular(14),
              ),
              alignment: Alignment.center,
              child: const Text(
                'Engelle',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w700,
                  fontSize: 15,
                  color: AppColors.white,
                ),
              ),
            ),
          ),
          const SizedBox(height: 8),
          PressableScale(
            onTap: () => Navigator.of(context).maybePop(),
            scale: 0.99,
            child: Container(
              height: 52,
              width: double.infinity,
              decoration: BoxDecoration(
                color: AppColors.grayField,
                borderRadius: BorderRadius.circular(14),
              ),
              alignment: Alignment.center,
              child: const Text(
                'Vazgec',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w600,
                  fontSize: 15,
                  color: AppColors.black,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// =============================================================================
// Notifications module â€” 2 varyasyon (empty / dolu)
