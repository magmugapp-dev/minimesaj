@extends('admin.layout.ana')

@section('baslik', 'AI Fotograflar')

@section('icerik')
    <div class="space-y-6 p-6">
        <section
            class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-widest text-indigo-600">AI Studio</div>
                <h1 class="mt-1 text-3xl font-bold text-gray-900">AI fotograf yonetimi</h1>
                <p class="mt-2 text-sm text-gray-500">Tekil veya toplu fotograf yukle, ana profil fotografini belirle.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.ai.index') }}"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">AI
                    Studio</a>
                <a href="{{ route('admin.ai.ekle') }}"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Yeni
                    AI ekle</a>
            </div>
        </section>

        @if (session('basari'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('basari') }}</div>
        @endif

        @if (session('hata'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ session('hata') }}</div>
        @endif

        @if (session('hatalar'))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                @foreach ((array) session('hatalar') as $hata)
                    <div>{{ $hata }}</div>
                @endforeach
            </div>
        @endif

        @include('admin.ai-v2.partials.navigation')

        <section class="rounded-lg border border-gray-200 bg-white shadow" x-data="{ mode: '{{ old('hedef_modu', 'selected') }}' }">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Toplu yukleme</h2>
                <p class="mt-1 text-sm text-gray-500">Dosyalar optimize edilerek jpg olarak kaydedilir.</p>
            </div>

            <form method="POST" action="{{ route('admin.ai.fotograflar.store') }}" enctype="multipart/form-data"
                class="space-y-5 px-6 py-5">
                @csrf
                <div class="grid gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-widest text-gray-500">Yukleme
                            modu</span>
                        <select name="hedef_modu" x-model="mode"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                            <option value="selected">Secili AI kullanicisi</option>
                            <option value="filename">Dosya adindan ata</option>
                        </select>
                    </label>

                    <label class="block" x-show="mode === 'selected'">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-widest text-gray-500">AI
                            kullanici</span>
                        <select name="user_id"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                            <option value="">Seciniz</option>
                            @foreach ($aiUsers as $aiUser)
                                <option value="{{ $aiUser->id }}" @selected((string) old('user_id', $selectedUser?->id) === (string) $aiUser->id)>
                                    {{ $aiUser->ad }} {{ $aiUser->soyad }} / {{ '@' . $aiUser->kullanici_adi }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600"
                        x-show="mode === 'filename'">
                        Dosya adi formati: <span class="font-mono text-gray-900">kullanici_adi__1.jpg</span>
                    </div>
                </div>

                <label class="block">
                    <span
                        class="mb-2 block text-xs font-semibold uppercase tracking-widest text-gray-500">Fotograflar</span>
                    <input type="file" name="fotograflar[]" multiple accept="image/jpeg,image/png,image/webp"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700">
                    @error('fotograflar')
                        <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                    @error('fotograflar.*')
                        <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </label>

                <div class="flex justify-end">
                    <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition">Fotograflari
                        yukle</button>
                </div>
            </form>
        </section>

        @if ($selectedUser)
            <section class="rounded-lg border border-gray-200 bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">{{ $selectedUser->ad }} {{ $selectedUser->soyad }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">Mevcut fotograflari yonet ve ana profil fotografini belirle.</p>
                </div>
                <!-- Photo manager component will be included here -->
            </section>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white shadow">
            <div class="border-b border-gray-200 px-6 py-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">AI kullanicilar</h2>
                        <p class="mt-1 text-sm text-gray-500">Fotograf sayisi ve ana gorsel durumunu hizli kontrol et.</p>
                    </div>
                    <form method="GET" action="{{ route('admin.ai.fotograflar') }}" class="min-w-[18rem]">
                        <input type="search" name="q" value="{{ $search }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                            placeholder="AI ara">
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">AI
                                kullanici</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Fotograf</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Islem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($personalar as $personaUser)
                            @php
                                $avatarUrl = \App\Support\MediaUrl::resolve($personaUser->profil_resmi);
                            @endphp
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-12 w-12 overflow-hidden rounded-full bg-indigo-100 flex-shrink-0">
                                            @if ($avatarUrl)
                                                <img src="{{ $avatarUrl }}" alt="{{ $personaUser->ad }}"
                                                    class="h-full w-full object-cover">
                                            @else
                                                <div
                                                    class="flex h-full w-full items-center justify-center text-sm font-bold text-indigo-700">
                                                    {{ mb_substr((string) $personaUser->ad, 0, 1) }}{{ mb_substr((string) $personaUser->soyad, 0, 1) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900">{{ $personaUser->ad }}
                                                {{ $personaUser->soyad }}</div>
                                            <div class="text-xs text-gray-500">{{ '@' . $personaUser->kullanici_adi }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $personaUser->fotograf_sayisi }}/{{ $maxPhotos }}</div>
                                    <div
                                        class="mt-1 text-xs {{ $personaUser->profil_resmi ? 'text-emerald-600' : 'text-amber-600' }}">
                                        {{ $personaUser->profil_resmi ? 'Ana fotograf var' : 'Ana fotograf eksik' }}</div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('admin.ai.fotograflar', ['user_id' => $personaUser->id]) }}"
                                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition">Yonet</a>
                                        <a href="{{ route('admin.ai.duzenle', $personaUser) }}"
                                            class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 transition">Duzenle</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">Kayit yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
