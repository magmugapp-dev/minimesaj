@extends('admin.layout.ana')

@section('baslik', 'AI Moderasyon Olaylari')

@section('icerik')
    <div class="space-y-6 p-6">
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-semibold text-gray-500">Bugun</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($stats['today']) }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-semibold text-indigo-700">Ghost</p>
                <p class="mt-1 text-2xl font-bold text-indigo-800">{{ number_format($stats['ghost']) }}</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                <p class="text-xs font-semibold text-red-700">Block</p>
                <p class="mt-1 text-2xl font-bold text-red-800">{{ number_format($stats['block']) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-semibold text-amber-700">Aktif Lockout</p>
                <p class="mt-1 text-2xl font-bold text-amber-800">{{ number_format($stats['active_lockouts']) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-600">Olay tipi</label>
                    <select name="event_type" class="rounded-lg border-gray-300 text-sm">
                        <option value="">Tum tipler</option>
                        @foreach (['ghost_silent', 'ghost_narrative', 'block'] as $type)
                            <option value="{{ $type }}" @selected(request('event_type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-600">Dominance</label>
                    <select name="dominance" class="rounded-lg border-gray-300 text-sm">
                        <option value="">Tumleri</option>
                        @foreach (['passive', 'balanced', 'dominant'] as $dominance)
                            <option value="{{ $dominance }}" @selected(request('dominance') === $dominance)>{{ $dominance }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Filtrele</button>
                @if (request()->hasAny(['event_type', 'dominance']))
                    <a href="{{ route('admin.moderasyon.ai-olaylari') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Temizle</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-3">Olay</th>
                            <th class="px-3 py-3">AI</th>
                            <th class="px-3 py-3">Kullanici</th>
                            <th class="px-3 py-3">Sohbet</th>
                            <th class="px-3 py-3">Lockout</th>
                            <th class="px-3 py-3">Metadata</th>
                            <th class="px-3 py-3">Tarih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($events as $event)
                            <tr>
                                <td class="px-3 py-3">
                                    <span class="rounded-full bg-gray-100 px-2 py-1 font-mono text-xs font-bold text-gray-700">{{ $event->event_type }}</span>
                                    @if ($event->dominance)
                                        <p class="mt-1 text-xs text-gray-500">{{ $event->dominance }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($event->aiUser)
                                        <a href="{{ route('admin.kullanicilar.goster', $event->aiUser) }}" class="font-semibold text-indigo-600">{{ $event->aiUser->ad }}</a>
                                    @else
                                        <span class="text-gray-400">Silinmis</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($event->user)
                                        <a href="{{ route('admin.kullanicilar.goster', $event->user) }}" class="font-semibold text-indigo-600">{{ $event->user->ad }}</a>
                                    @else
                                        <span class="text-gray-400">Silinmis</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 font-mono text-xs">#{{ $event->conversation_id ?: '-' }}</td>
                                <td class="px-3 py-3 text-xs text-gray-600">
                                    @if ($event->lockout_until)
                                        {{ $event->lockout_until->format('d.m.Y H:i') }}
                                        @if ($event->lockout_until->isFuture())
                                            <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 font-semibold text-amber-700">aktif</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="max-w-sm px-3 py-3 font-mono text-xs text-gray-500">{{ $event->metadata ? json_encode($event->metadata, JSON_UNESCAPED_UNICODE) : '-' }}</td>
                                <td class="px-3 py-3 text-xs text-gray-500">{{ $event->created_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-10 text-center text-gray-400">AI moderasyon olayi yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($events->hasPages())
                <div class="mt-4 border-t border-gray-100 pt-4">{{ $events->links() }}</div>
            @endif
        </div>
    </div>
@endsection
