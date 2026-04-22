import 'package:magmug/app_core.dart';

Color homeAvatarColorForName(String name) => avatarColorForName(name);

String homeInitialsOf(String fullName) => initialsOf(fullName);

class HomeAvatarCircle extends StatelessWidget {
  final String name;
  final double size;
  final bool online;

  const HomeAvatarCircle({
    super.key,
    required this.name,
    this.size = 52,
    this.online = false,
  });

  @override
  Widget build(BuildContext context) {
    return AvatarCircle(name: name, size: size, online: online);
  }
}
