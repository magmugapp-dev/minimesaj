import 'package:flutter/cupertino.dart';

class AppColors {
  AppColors._();

  static const Color indigo = Color(0xFF5C6BFF);
  static const Color peach = Color(0xFFFDB384);
  static const Color coral = Color(0xFFFF9794);
  static const Color black = Color(0xFF111111);
  static const Color gray = Color(0xFF999999);
  static const Color grayField = Color(0xFFF5F5F7);
  static const Color grayBorder = Color(0xFFE0E0E0);
  static const Color grayProgress = Color(0xFFEFEFEF);
  static const Color white = Color(0xFFFFFFFF);
  static const Color shadow = Color(0x405C6BFF);
  static const Color neutral100 = Color(0xFFF5F5F5);
  static const Color neutral500 = Color(0xFF737373);
  static const Color neutral600 = Color(0xFF525252);
  static const Color neutral950 = Color(0xFF0A0A0A);
  static const Color zinc900 = Color(0xFF18181B);
  static const Color brandBlue = Color(0xFF1E90FF);
  static const Color onlineGreen = Color(0xFF22C55E);

  static const LinearGradient primary = LinearGradient(
    begin: Alignment(-1.0, -0.3),
    end: Alignment(1.0, 0.3),
    colors: [indigo, peach, coral],
    stops: [0.0, 0.5, 1.0],
  );
}
