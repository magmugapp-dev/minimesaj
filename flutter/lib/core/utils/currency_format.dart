String formatCurrencyAmount(num amount, {String currency = 'TL'}) {
  final normalizedCurrency = currency.trim().isEmpty ? 'TL' : currency.trim();
  final fixed = amount.toStringAsFixed(2);
  final parts = fixed.split('.');
  final whole = parts.first;
  final decimal = parts.length > 1 ? parts[1] : '00';
  final buffer = StringBuffer();

  for (var index = 0; index < whole.length; index++) {
    if (index > 0 && (whole.length - index) % 3 == 0) {
      buffer.write('.');
    }
    buffer.write(whole[index]);
  }

  return '${buffer.toString()},$decimal $normalizedCurrency';
}
