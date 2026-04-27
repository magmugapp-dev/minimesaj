@php
    $photos =
        ($kullanici?->relationLoaded('fotograflar')
            ? $kullanici->fotograflar
            : $kullanici?->fotograflar()->orderBy('sira_no')->orderBy('id')->get()) ?? collect();
    $photos = $photos
        ->where('medya_tipi', 'fotograf')
        ->sortBy([['sira_no', 'asc'], ['id', 'asc']])
        ->values();
    $maxPhotos = $maxPhotos ?? 6;
    $remainingSlots = max(0, $maxPhotos - $photos->count());
@endphp

<section class="rounded-lg border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-200 px-5 py-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Fotograflar</div>
                <h2 class="mt-1 text-lg font-semibold text-gray-900">Profil galerisi</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $photos->count() }}/{{ $maxPhotos }} fotograf kayitli</p>
            </div>
            <a href="{{ route('admin.ai.fotograflar', ['user_id' => $kullanici->id]) }}"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Toplu
                panele ac</a>
        </div>
    </div>

    <div class="space-y-5 px-5 py-5">
        @if (session('hatalar'))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                @foreach ((array) session('hatalar') as $hata)
                    <div>{{ $hata }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.ai.fotograflar.user-store', $kullanici) }}"
            enctype="multipart/form-data" class="rounded-lg border border-gray-200 bg-gray-50 p-4">
            @csrf
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto_auto] lg:items-end">
                <label class="block">
                    <span class="mb-2 block text-xs font-semibold uppercase tracking-widest text-gray-500">Yeni
                        fotograf</span>
                    <input type="file" name="fotograflar[]" multiple accept="image/jpeg,image/png,image/webp"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700">
                </label>
                <label
                    class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700">
                    <input type="checkbox" name="ana_fotograf_mi" value="1" class="h-4 w-4 accent-indigo-600">
                    Ana fotograf yap
                </label>
                <button type="submit" @disabled($remainingSlots === 0)
                    class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">Yukle</button>
            </div>
            @if ($remainingSlots === 0)
                <p class="mt-3 text-xs font-medium text-rose-600">Maksimum fotograf limitine ulasildi.</p>
            @endif
        </form>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($photos as $photo)
                @php
                    $photoUrl = \App\Support\MediaUrl::resolve($photo->onizleme_yolu ?: $photo->dosya_yolu);
                @endphp
                <article class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                    <div class="relative aspect-[4/5] bg-gray-100">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="{{ $kullanici->ad }} fotograf"
                                class="h-full w-full object-cover">
                        @else
                            <div
                                class="flex h-full w-full items-center justify-center text-sm font-semibold text-gray-400">
                                Onizleme yok</div>
                        @endif
                        <div class="absolute left-2 top-2 flex flex-wrap gap-2">
                            @if ($photo->ana_fotograf_mi)
                                <span
                                    class="rounded-full bg-indigo-600 px-2 py-1 text-xs font-semibold text-white">Ana</span>
                            @endif
                            @unless ($photo->aktif_mi)
                                <span
                                    class="rounded-full bg-amber-500 px-2 py-1 text-xs font-semibold text-white">Pasif</span>
                            @endunless
                        </div>
                    </div>
                    <div class="space-y-3 p-3">
                        <form method="POST" action="{{ route('admin.ai.fotograflar.update', [$kullanici, $photo]) }}"
                            class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <input type="number" name="sira_no" min="0" max="99"
                                value="{{ $photo->sira_no }}"
                                class="w-20 rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
                            <button type="submit"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Sira</button>
                        </form>
                        <div class="flex flex-wrap gap-2">
                            @unless ($photo->ana_fotograf_mi)
                                <form method="POST"
                                    action="{{ route('admin.ai.fotograflar.update', [$kullanici, $photo]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="ana_fotograf_mi" value="1">
                                    <button type="submit"
                                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">Ana
                                        yap</button>
                                </form>
                            @endunless
                            <form method="POST"
                                action="{{ route('admin.ai.fotograflar.destroy', [$kullanici, $photo]) }}"
                                onsubmit="return confirm('Bu fotografi silmek istediginize emin misiniz?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Sil</button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach

            @for ($index = $photos->count(); $index < $maxPhotos; $index++)
                <div
                    class="flex min-h-56 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 text-sm font-medium text-gray-400">
                    Bos slot
                </div>
            @endfor
        </div>
    </div>
</section>
