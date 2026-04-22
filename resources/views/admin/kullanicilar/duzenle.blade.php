@extends('admin.layout.ana')

@section('baslik', $kullanici->kullanici_adi . ' — Düzenle')

@section('icerik')
    {{-- Üst başlık --}}
    <div class="mb-6 flex items-center gap-4 p-6 ">
        <a href="{{ route('admin.kullanicilar.goster', $kullanici) }}"
            class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
        </a>
        <div>
            <h2 class="text-xl font-bold text-gray-900">{{ $kullanici->ad }} {{ $kullanici->soyad }}</h2>
            <p class="text-sm text-gray-500">{{ '@' . $kullanici->kullanici_adi }} · #{{ $kullanici->id }} · Düzenleniyor
            </p>
        </div>
    </div>

    <form class="p-6 " method="POST" action="{{ route('admin.kullanicilar.guncelle', $kullanici) }}">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 p-6">
            {{-- Kişisel Bilgiler --}}
            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="mb-5 text-sm font-semibold uppercase tracking-wider text-gray-500">Kişisel Bilgiler</h3>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="ad" class="mb-1 block text-sm font-medium text-gray-700">Ad <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="ad" id="ad" value="{{ old('ad', $kullanici->ad) }}"
                                required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('ad') border-red-500 @enderror">
                            @error('ad')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="soyad" class="mb-1 block text-sm font-medium text-gray-700">Soyad</label>
                            <input type="text" name="soyad" id="soyad"
                                value="{{ old('soyad', $kullanici->soyad) }}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('soyad') border-red-500 @enderror">
                            @error('soyad')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-700">E-posta</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $kullanici->email) }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('email') border-red-500 @enderror">
                        @error('email')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="cinsiyet" class="mb-1 block text-sm font-medium text-gray-700">Cinsiyet</label>
                            <select name="cinsiyet" id="cinsiyet"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <option value="erkek"
                                    {{ old('cinsiyet', $kullanici->cinsiyet) === 'erkek' ? 'selected' : '' }}>Erkek
                                </option>
                                <option value="kadin"
                                    {{ old('cinsiyet', $kullanici->cinsiyet) === 'kadin' ? 'selected' : '' }}>Kadın
                                </option>
                                <option value="belirtmek_istemiyorum"
                                    {{ old('cinsiyet', $kullanici->cinsiyet) === 'belirtmek_istemiyorum' ? 'selected' : '' }}>
                                    Belirtmek İstemiyor</option>
                            </select>
                        </div>
                        <div>
                            <label for="dogum_yili" class="mb-1 block text-sm font-medium text-gray-700">Doğum Yılı</label>
                            <input type="number" name="dogum_yili" id="dogum_yili"
                                value="{{ old('dogum_yili', $kullanici->dogum_yili) }}" min="1950"
                                max="{{ date('Y') - 18 }}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('dogum_yili') border-red-500 @enderror">
                            @error('dogum_yili')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label for="ulke" class="mb-1 block text-sm font-medium text-gray-700">Ülke</label>
                            <input type="text" name="ulke" id="ulke" value="{{ old('ulke', $kullanici->ulke) }}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="il" class="mb-1 block text-sm font-medium text-gray-700">İl</label>
                            <input type="text" name="il" id="il" value="{{ old('il', $kullanici->il) }}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="ilce" class="mb-1 block text-sm font-medium text-gray-700">İlçe</label>
                            <input type="text" name="ilce" id="ilce" value="{{ old('ilce', $kullanici->ilce) }}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label for="biyografi" class="mb-1 block text-sm font-medium text-gray-700">Biyografi</label>
                        <textarea name="biyografi" id="biyografi" rows="3"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('biyografi') border-red-500 @enderror">{{ old('biyografi', $kullanici->biyografi) }}</textarea>
                        @error('biyografi')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Hesap Ayarları --}}
            <div class="space-y-6 ">
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="mb-5 text-sm font-semibold uppercase tracking-wider text-gray-500">Hesap Ayarları</h3>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="hesap_tipi" class="mb-1 block text-sm font-medium text-gray-700">Hesap
                                    Tipi</label>
                                <select name="hesap_tipi" id="hesap_tipi"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                    <option value="user"
                                        {{ old('hesap_tipi', $kullanici->hesap_tipi) === 'user' ? 'selected' : '' }}>Gerçek
                                    </option>
                                    <option value="ai"
                                        {{ old('hesap_tipi', $kullanici->hesap_tipi) === 'ai' ? 'selected' : '' }}>AI
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label for="hesap_durumu"
                                    class="mb-1 block text-sm font-medium text-gray-700">Durum</label>
                                <select name="hesap_durumu" id="hesap_durumu"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                    <option value="aktif"
                                        {{ old('hesap_durumu', $kullanici->hesap_durumu) === 'aktif' ? 'selected' : '' }}>
                                        Aktif</option>
                                    <option value="pasif"
                                        {{ old('hesap_durumu', $kullanici->hesap_durumu) === 'pasif' ? 'selected' : '' }}>
                                        Pasif</option>
                                    <option value="yasakli"
                                        {{ old('hesap_durumu', $kullanici->hesap_durumu) === 'yasakli' ? 'selected' : '' }}>
                                        Yasaklı</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="mevcut_puan" class="mb-1 block text-sm font-medium text-gray-700">Mevcut
                                    Puan</label>
                                <input type="number" name="mevcut_puan" id="mevcut_puan"
                                    value="{{ old('mevcut_puan', $kullanici->mevcut_puan) }}" min="0"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('mevcut_puan') border-red-500 @enderror">
                                @error('mevcut_puan')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="gunluk_ucretsiz_hak"
                                    class="mb-1 block text-sm font-medium text-gray-700">Günlük Hak</label>
                                <input type="number" name="gunluk_ucretsiz_hak" id="gunluk_ucretsiz_hak"
                                    value="{{ old('gunluk_ucretsiz_hak', $kullanici->gunluk_ucretsiz_hak) }}"
                                    min="0"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('gunluk_ucretsiz_hak') border-red-500 @enderror">
                                @error('gunluk_ucretsiz_hak')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Toggle'lar --}}
                        <div class="space-y-3 border-t border-gray-100 pt-4">
                            <label class="flex items-center justify-between">
                                <span class="text-sm text-gray-700">Premium Aktif</span>
                                <input type="hidden" name="premium_aktif_mi" value="0">
                                <input type="checkbox" name="premium_aktif_mi" value="1"
                                    {{ old('premium_aktif_mi', $kullanici->premium_aktif_mi) ? 'checked' : '' }}
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            </label>

                        </div>
                    </div>
                </div>

                {{-- Salt okunur bilgiler --}}
                <div class="rounded-lg bg-gray-50 p-6 shadow">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-400">Salt Okunur</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Kullanıcı Adı</dt>
                            <dd class="font-mono text-gray-700">{{ $kullanici->kullanici_adi }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Kayıt Tarihi</dt>
                            <dd class="text-gray-700">{{ $kullanici->created_at->format('d.m.Y H:i') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Son Güncelleme</dt>
                            <dd class="text-gray-700">{{ $kullanici->updated_at->format('d.m.Y H:i') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Kaydet --}}
        <div class="mt-6 flex items-center justify-end gap-3">
            <a href="{{ route('admin.kullanicilar.goster', $kullanici) }}"
                class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                İptal
            </a>
            <button type="submit"
                class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Değişiklikleri Kaydet
            </button>
        </div>
    </form>
@endsection
