import 'package:flutter/cupertino.dart';

Route<T> cupertinoRoute<T>(Widget page, {String? name}) {
  return CupertinoPageRoute<T>(
    builder: (_) => page,
    settings: RouteSettings(name: name),
  );
}

Route<T> cupertinoRouteFromLeft<T>(Widget page, {String? name}) {
  return PageRouteBuilder<T>(
    settings: RouteSettings(name: name),
    pageBuilder: (_, animation, secondaryAnimation) => page,
    transitionsBuilder: (_, animation, secondaryAnimation, child) {
      final curve = CurvedAnimation(
        parent: animation,
        curve: Curves.easeOutCubic,
        reverseCurve: Curves.easeInCubic,
      );

      return SlideTransition(
        position: Tween<Offset>(
          begin: const Offset(-1, 0),
          end: Offset.zero,
        ).animate(curve),
        child: child,
      );
    },
  );
}
