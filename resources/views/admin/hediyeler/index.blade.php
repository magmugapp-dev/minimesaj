@extends('admin.layout.ana')

@section('baslik', 'Hediyeler')

@section('icerik')
    <div class="space-y-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Hediye katalogu</h2>
                <p class="text-sm text-gray-500">Uygulamada gosterilecek hediyeleri ve puan maliyetlerini buradan yonet.</p>
            </div>
            <a href="{{ route('admin.hediyeler.create') }}"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Yeni Hediye
            </a>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wider text-gray-500">
                            <th class="px-3 py-3">Hediye</th>
                            <th class="px-3 py-3">Kod</th>
                            <th class="px-3 py-3">Puan</th>
                            <th class="px-3 py-3">Durum</th>
                            <th class="px-3 py-3 text-right">Islem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($hediyeler as $hediye)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-2xl">
                                            {{ $hediye->ikon ?: '🎁' }}
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $hediye->ad }}</p>
                                            <p class="text-xs text-gray-400">Sira: {{ $hediye->sira }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-gray-700">{{ $hediye->kod }}</td>
                                <td class="px-3 py-3 font-semibold text-gray-900">{{ number_format($hediye->puan_bedeli) }}</td>
                                <td class="px-3 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $hediye->aktif ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $hediye->aktif ? 'Aktif' : 'Pasif' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.hediyeler.edit', $hediye) }}"
                                            class="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                                            Duzenle
                                        </a>
                                        <form method="POST" action="{{ route('admin.hediyeler.destroy', $hediye) }}"
                                            onsubmit="return confirm('Bu hediyeyi silmek istedigine emin misin?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">
                                                Sil
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-10 text-center text-sm text-gray-500">
                                    Henuz hediye tanimlanmadi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
