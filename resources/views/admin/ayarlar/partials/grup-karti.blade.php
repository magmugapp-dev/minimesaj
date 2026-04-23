<section class="studio-card">
    <div class="studio-card__header">
        <div>
            <h3 class="studio-title">{{ $gAd }} ayarlari</h3>
        </div>

        <div class="studio-pill-list">
            <span class="studio-pill studio-pill--info">{{ count($ayarListesi) }} alan</span>
        </div>
    </div>

    @if (empty($ayarListesi))
        <div class="studio-surface">
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($ayarListesi as $anahtar => $ayar)
                @continue($anahtar === 'firebase_server_key')

                @php
                    $placeholder = $ayar['aciklama'] ?? 'Bir deger girin';
                    $kartSinifi = match (true) {
                        $ayar['tip'] === 'text',
                        $ayar['tip'] === 'json',
                        $ayar['tip'] === 'file',
                        in_array($anahtar, $dosyaAyarlari)
                            => 'md:col-span-2 xl:col-span-3',
                        default => '',
                    };
                    $tipRozeti = match ($ayar['tip']) {
                        'file' => ['Dosya', 'studio-pill studio-pill--info'],
                        'boolean' => ['Durum', 'studio-pill studio-pill--success'],
                        'integer' => ['Sayi', 'studio-pill studio-pill--warning'],
                        'json' => ['JSON', 'studio-pill studio-pill--neutral'],
                        'text' => ['Metin', 'studio-pill studio-pill--neutral'],
                        default => ['Alan', 'studio-pill studio-pill--neutral'],
                    };
                @endphp

                <div class="{{ $kartSinifi }}">
                    <article class="studio-surface h-full">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <label class="studio-label"
                                    for="{{ $anahtar }}">{{ $ayar['aciklama'] ?? str_replace('_', ' ', ucfirst($anahtar)) }}</label>
                                <p class="studio-hint">{{ $anahtar }}</p>
                            </div>
                            <span class="{{ $tipRozeti[1] }}">{{ $tipRozeti[0] }}</span>
                        </div>

                        @if ($ayar['tip'] === 'file' || in_array($anahtar, $dosyaAyarlari))
                            <input type="file" name="{{ $anahtar }}" id="{{ $anahtar }}"
                                class="studio-input"
                                @if ($anahtar === 'firebase_service_account_path') accept=".json,application/json" @endif>
                            @if (!empty($ayar['deger']))
                                <div class="studio-pill-list mt-3">
                                    <span class="studio-pill studio-pill--success">Mevcut dosya:
                                        {{ basename($ayar['deger']) }}</span>
                                </div>
                            @endif
                        @elseif (isset($modelSecenekleri[$anahtar]))
                            <select name="{{ $anahtar }}" id="{{ $anahtar }}" class="studio-select">
                                @foreach ($modelSecenekleri[$anahtar] as $deger => $etiket)
                                    <option value="{{ $deger }}"
                                        {{ $ayar['deger'] == $deger ? 'selected' : '' }}>{{ $etiket }}</option>
                                @endforeach
                            </select>
                        @elseif ($ayar['tip'] === 'boolean')
                            <label class="studio-toggle mt-3 block">
                                <div class="studio-toggle__row">
                                    <div>
                                        <div class="studio-toggle__title">Durum yonetimi</div>
                                    </div>
                                    <div class="shrink-0">
                                        <input type="hidden" name="{{ $anahtar }}" value="0">
                                        <input type="checkbox" name="{{ $anahtar }}" value="1"
                                            class="studio-check" {{ $ayar['deger'] ? 'checked' : '' }}>
                                    </div>
                                </div>
                            </label>
                        @elseif ($ayar['tip'] === 'integer')
                            <input type="number" name="{{ $anahtar }}" id="{{ $anahtar }}"
                                value="{{ $ayar['deger'] }}" class="studio-input" placeholder="0">
                        @elseif ($ayar['tip'] === 'text' || $ayar['tip'] === 'json')
                            <textarea name="{{ $anahtar }}" id="{{ $anahtar }}" rows="5" class="studio-textarea"
                                placeholder="{{ $ayar['tip'] === 'json' ? 'JSON degeri girin' : $placeholder }}">{{ $ayar['tip'] === 'json' ? json_encode($ayar['deger'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $ayar['deger'] }}</textarea>
                        @else
                            @if (str_contains($anahtar, 'password') ||
                                    str_contains($anahtar, 'secret') ||
                                    str_contains($anahtar, 'key') ||
                                    str_contains($anahtar, 'sifre'))
                                <input type="password" name="{{ $anahtar }}" id="{{ $anahtar }}"
                                    value="{{ $ayar['deger'] }}" class="studio-input" placeholder="••••••••">
                            @else
                                <input type="text" name="{{ $anahtar }}" id="{{ $anahtar }}"
                                    value="{{ $ayar['deger'] }}" class="studio-input"
                                    placeholder="{{ $placeholder }}">
                            @endif
                        @endif
                    </article>
                </div>
            @endforeach
        </div>
    @endif
</section>
