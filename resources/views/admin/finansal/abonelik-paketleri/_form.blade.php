@csrf
@if ($paket->exists)
    @method('PUT')
@endif

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Kod</label>
        <input type="text" name="kod" value="{{ old('kod', $paket->kod) }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Sira</label>
        <input type="number" name="sira" value="{{ old('sira', $paket->sira ?? 0) }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Android urun kodu</label>
        <input type="text" name="android_urun_kodu"
            value="{{ old('android_urun_kodu', $paket->android_urun_kodu) }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">iOS urun kodu</label>
        <input type="text" name="ios_urun_kodu" value="{{ old('ios_urun_kodu', $paket->ios_urun_kodu) }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Sure (ay)</label>
        <input type="number" name="sure_ay" min="1" max="36" value="{{ old('sure_ay', $paket->sure_ay ?? 1) }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Fiyat</label>
        <input type="number" name="fiyat" min="0" step="0.01"
            value="{{ old('fiyat', $paket->fiyat ?? 0) }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Para birimi</label>
        <input type="text" name="para_birimi" maxlength="3"
            value="{{ old('para_birimi', $paket->para_birimi ?? 'USD') }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm uppercase focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Rozet</label>
        <input type="text" name="rozet" value="{{ old('rozet', $paket->rozet) }}" placeholder="EN POPULER"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:col-span-2">
        <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
            <input type="hidden" name="onerilen_mi" value="0">
            <input type="checkbox" name="onerilen_mi" value="1"
                {{ old('onerilen_mi', $paket->onerilen_mi) ? 'checked' : '' }}>
            <span class="text-sm text-gray-700">Onerilen paket</span>
        </label>

        <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" {{ old('aktif', $paket->aktif ?? true) ? 'checked' : '' }}>
            <span class="text-sm text-gray-700">Aktif</span>
        </label>
    </div>
</div>

@if ($errors->any())
    <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('admin.finansal.abonelik-paketleri.index') }}"
        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
        Vazgec
    </a>
    <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700">
        Kaydet
    </button>
</div>
