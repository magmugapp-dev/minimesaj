@csrf
@if ($hediye->exists)
    @method('PUT')
@endif

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Kod</label>
        <input type="text" name="kod" value="{{ old('kod', $hediye->kod) }}" placeholder="gul"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Sira</label>
        <input type="number" name="sira" min="0" value="{{ old('sira', $hediye->sira ?? 0) }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Hediye adi</label>
        <input type="text" name="ad" value="{{ old('ad', $hediye->ad) }}" placeholder="Gul"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Ikon / Emoji</label>
        <input type="text" name="ikon" maxlength="20" value="{{ old('ikon', $hediye->ikon) }}" placeholder="🎁"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Kredi maliyeti</label>
        <input type="number" name="puan_bedeli" min="1" max="100000"
            value="{{ old('puan_bedeli', $hediye->puan_bedeli ?? 1) }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div class="flex items-end">
        <label class="flex w-full items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1"
                {{ old('aktif', $hediye->aktif ?? true) ? 'checked' : '' }}>
            <span class="text-sm text-gray-700">Uygulamada aktif goster</span>
        </label>
    </div>
</div>

@if ($errors->any())
    <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('admin.hediyeler.index') }}"
        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
        Vazgec
    </a>
    <button type="submit"
        class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700">
        Kaydet
    </button>
</div>
