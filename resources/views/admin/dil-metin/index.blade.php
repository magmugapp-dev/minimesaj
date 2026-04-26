@extends('admin.layout.ana')

@section('baslik', 'Dil ve Metin Yonetimi')

@section('icerik')
    @php
        $initialModule = match (request('tab', 'keys')) {
            'languages' => 'languages',
            'legal' => 'legal',
            'faq' => 'faq',
            default => 'keys',
        };
        $defaultLanguage = $activeLanguages->firstWhere('is_default', true) ?? $activeLanguages->first();
        $spaConfig = [
            'csrf' => csrf_token(),
            'initialModule' => $initialModule,
            'defaultLanguageId' => $selectedLanguageId ?: $defaultLanguage?->id,
            'languages' => $languages
                ->map(
                    fn($language) => [
                        'id' => $language->id,
                        'code' => $language->code,
                        'name' => $language->name,
                        'native_name' => $language->native_name,
                        'is_active' => (bool) $language->is_active,
                        'is_default' => (bool) $language->is_default,
                        'sort_order' => $language->sort_order,
                        'is_archived' => $language->trashed(),
                    ],
                )
                ->values(),
            'activeLanguages' => $activeLanguages
                ->map(
                    fn($language) => [
                        'id' => $language->id,
                        'code' => $language->code,
                        'name' => $language->name,
                    ],
                )
                ->values(),
            'legalTypes' => $legalTypes,
            'endpoints' => [
                'meta' => route('admin.dil-metin.api.meta'),
                'keys' => route('admin.dil-metin.api.keys.index'),
                'languages' => route('admin.dil-metin.api.languages.index'),
                'legal' => route('admin.dil-metin.api.legal.index'),
                'faq' => route('admin.dil-metin.api.faq.index'),
            ],
        ];
    @endphp

    <div x-data='dilMetinSpa(@json($spaConfig))' x-init="init()"
        class="min-h-[calc(100vh-5rem)] overflow-hidden border border-slate-200 bg-white shadow-sm">
        <div class="flex min-h-[calc(100vh-8rem)] bg-slate-50">
            <aside class="hidden w-72 shrink-0 border-r border-slate-200 bg-slate-950 text-white lg:flex lg:flex-col">


                <nav class="flex-1 space-y-2 p-3">
                    <template x-for="module in modules" :key="module.id">
                        <button type="button" @click="switchModule(module.id)"
                            :class="activeModule === module.id ? 'bg-white text-slate-950 shadow-lg shadow-cyan-500/10' :
                                'text-slate-300 hover:bg-white/10 hover:text-white'"
                            class="flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-left text-sm font-black transition">
                            <span
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 via-cyan-400 to-pink-500 text-xs font-black text-white"
                                x-text="module.short"></span>
                            <span>
                                <span class="block" x-text="module.label"></span>
                                <span class="block text-xs font-semibold opacity-60" x-text="module.hint"></span>
                            </span>
                        </button>
                    </template>
                </nav>


            </aside>

            <main class="flex min-w-0 flex-1 flex-col">
                <header class="border-b border-slate-200 bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 xl:px-6">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                {{-- <span
                                    class="rounded-full bg-gradient-to-r from-indigo-500 via-cyan-400 to-pink-500 px-3 py-1 text-xs font-black uppercase tracking-wide text-white">SPA
                                    Workspace</span> --}}
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-500"
                                    x-text="activeModuleLabel()"></span>
                            </div>
                            {{-- <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Dil ve Metin Yonetimi</h2> --}}
                        </div>

                        <div class="flex flex-1 flex-wrap items-center justify-end gap-2">
                            <input x-model.debounce.450ms="filters.search" @input.debounce.450ms="loadList(1)"
                                type="search" placeholder="Global ara..."
                                class="h-11 min-w-[260px] rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none transition focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100">
                            <button type="button" @click="openCreateModal()"
                                class="h-11 rounded-2xl bg-slate-950 px-5 text-sm font-black text-white shadow-sm transition hover:bg-slate-800">
                                Yeni <span x-text="activeModuleSingular()"></span>
                            </button>
                        </div>
                    </div>

                    <div
                        class="grid gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3 md:grid-cols-2 xl:grid-cols-6 xl:px-6">
                        <select x-model="filters.status" @change="loadList(1)"
                            class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                            <option value="">Tum durumlar</option>
                            <option value="active">Aktif</option>
                            <option value="passive">Pasif</option>
                        </select>
                        <select x-model="filters.archive" @change="loadList(1)"
                            class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                            <option value="">Aktif kayitlar</option>
                            <option value="archived">Arsiv</option>
                        </select>
                        <select x-model="filters.language_id" @change="loadList(1)"
                            class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                            <option value="">Tum diller</option>
                            <template x-for="language in meta.languages" :key="language.id">
                                <option :value="language.id" x-text="`${language.code} - ${language.name}`"></option>
                            </template>
                        </select>
                        <template x-if="activeModule === 'keys' || activeModule === 'faq'">
                            <input x-model.debounce.450ms="filters.category" @input.debounce.450ms="loadList(1)"
                                placeholder="Kategori"
                                class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                        </template>
                        <template x-if="activeModule === 'keys' || activeModule === 'faq'">
                            <input x-model.debounce.450ms="filters.screen" @input.debounce.450ms="loadList(1)"
                                placeholder="Ekran / modul"
                                class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                        </template>
                        <template x-if="activeModule === 'legal'">
                            <select x-model="filters.type" @change="loadList(1)"
                                class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                                <option value="">Tum yasal tipler</option>
                                <template x-for="entry in Object.entries(meta.legalTypes)" :key="entry[0]">
                                    <option :value="entry[0]" x-text="entry[1]"></option>
                                </template>
                            </select>
                        </template>
                        <label x-show="activeModule === 'keys'"
                            class="flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-black text-slate-700">
                            <input type="checkbox" x-model="filters.missing" @change="loadList(1)"
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            Eksik ceviri
                        </label>
                    </div>

                    <div class="flex gap-2 overflow-x-auto border-t border-slate-100 bg-white px-4 py-2 xl:px-6">
                        <template x-for="tab in openTabs" :key="tab.id">
                            <button type="button" @click="activateTab(tab.id)"
                                :class="activeTabId === tab.id ? 'border-slate-950 bg-slate-950 text-white' :
                                    'border-slate-200 bg-slate-50 text-slate-700 hover:bg-white'"
                                class="group flex max-w-[280px] shrink-0 items-center gap-2 rounded-2xl border px-3 py-2 text-xs font-black transition">
                                <span class="h-2 w-2 rounded-full"
                                    :class="tab.dirty ? 'bg-pink-400' : 'bg-cyan-400'"></span>
                                <span class="truncate" x-text="tab.title"></span>
                                <span @click.stop="requestCloseTab(tab.id)"
                                    class="rounded-full px-1.5 py-0.5 opacity-60 hover:bg-white/20 hover:opacity-100">x</span>
                            </button>
                        </template>
                        <div x-show="openTabs.length === 0"
                            class="rounded-2xl border border-dashed border-slate-200 px-4 py-2 text-xs font-semibold text-slate-400">
                            Workspace bos. Listeden bir kayit ac.
                        </div>
                    </div>
                </header>

                <div class="grid min-h-0 flex-1 xl:grid-cols-[minmax(0,1fr)_520px]">
                    <section class="min-w-0 border-r border-slate-200 bg-white">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 xl:px-6">
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-wide text-slate-500"
                                    x-text="activeModuleLabel()"></h3>
                                <p class="text-xs text-slate-400"><span x-text="pagination.total || 0"></span> kayit</p>
                            </div>
                            <div x-show="loading"
                                class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-black text-cyan-700">Yukleniyor</div>
                        </div>

                        <div class="overflow-x-auto">
                            <template x-if="activeModule === 'keys'">
                                <table class="min-w-full divide-y divide-slate-100 text-sm">
                                    <thead
                                        class="bg-slate-50 text-left text-xs font-black uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3">Key</th>
                                            <th class="px-4 py-3">Kategori</th>
                                            <th class="px-4 py-3">Durum</th>
                                            <th class="px-4 py-3">Eksik</th>
                                            <th class="px-4 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="item in items" :key="item.id">
                                            <tr class="hover:bg-indigo-50/40">
                                                <td class="max-w-[360px] px-4 py-3">
                                                    <button type="button" @click="openItem(item)"
                                                        class="block text-left">
                                                        <span
                                                            class="block truncate font-mono text-xs font-black text-slate-950"
                                                            x-text="item.key"></span>
                                                        <span class="mt-1 line-clamp-1 text-xs text-slate-500"
                                                            x-text="item.default_value || 'Varsayilan deger yok'"></span>
                                                    </button>
                                                </td>
                                                <td class="px-4 py-3 text-xs font-bold text-slate-500"><span
                                                        x-text="`${item.category} / ${item.screen}`"></span></td>
                                                <td class="px-4 py-3"><span
                                                        class="rounded-full px-2 py-1 text-xs font-black"
                                                        :class="item.is_active ? 'bg-emerald-50 text-emerald-700' :
                                                            'bg-slate-100 text-slate-500'"
                                                        x-text="item.is_active ? 'Aktif' : 'Pasif'"></span></td>
                                                <td class="px-4 py-3"><span
                                                        class="rounded-full px-2 py-1 text-xs font-black"
                                                        :class="item.missing_count > 0 ? 'bg-pink-50 text-pink-700' :
                                                            'bg-cyan-50 text-cyan-700'"
                                                        x-text="item.missing_count"></span></td>
                                                <td class="px-4 py-3 text-right"><button type="button"
                                                        @click="openItem(item)"
                                                        class="rounded-xl bg-slate-950 px-3 py-1.5 text-xs font-black text-white">Ac</button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </template>

                            <template x-if="activeModule === 'languages'">
                                <table class="min-w-full divide-y divide-slate-100 text-sm">
                                    <thead
                                        class="bg-slate-50 text-left text-xs font-black uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3">Kod</th>
                                            <th class="px-4 py-3">Dil</th>
                                            <th class="px-4 py-3">Durum</th>
                                            <th class="px-4 py-3">Sira</th>
                                            <th class="px-4 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="item in items" :key="item.id">
                                            <tr class="hover:bg-cyan-50/40">
                                                <td class="px-4 py-3 font-mono text-xs font-black text-slate-950"
                                                    x-text="item.code"></td>
                                                <td class="px-4 py-3"><span class="font-bold text-slate-900"
                                                        x-text="item.name"></span><span
                                                        class="ml-2 text-xs text-slate-400"
                                                        x-text="item.native_name || ''"></span></td>
                                                <td class="px-4 py-3"><span
                                                        class="rounded-full px-2 py-1 text-xs font-black"
                                                        :class="item.is_default ? 'bg-indigo-50 text-indigo-700' : (item
                                                            .is_active ? 'bg-emerald-50 text-emerald-700' :
                                                            'bg-slate-100 text-slate-500')"
                                                        x-text="item.is_default ? 'Varsayilan' : (item.is_active ? 'Aktif' : 'Pasif')"></span>
                                                </td>
                                                <td class="px-4 py-3 text-xs font-bold text-slate-500"
                                                    x-text="item.sort_order"></td>
                                                <td class="px-4 py-3 text-right"><button type="button"
                                                        @click="openItem(item)"
                                                        class="rounded-xl bg-slate-950 px-3 py-1.5 text-xs font-black text-white">Ac</button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </template>

                            <template x-if="activeModule === 'legal'">
                                <table class="min-w-full divide-y divide-slate-100 text-sm">
                                    <thead
                                        class="bg-slate-50 text-left text-xs font-black uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3">Tip</th>
                                            <th class="px-4 py-3">Dil</th>
                                            <th class="px-4 py-3">Baslik</th>
                                            <th class="px-4 py-3">Durum</th>
                                            <th class="px-4 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="item in items" :key="item.id">
                                            <tr class="hover:bg-pink-50/40">
                                                <td class="px-4 py-3 text-xs font-black text-slate-600"
                                                    x-text="item.type_label"></td>
                                                <td class="px-4 py-3 text-xs font-bold text-slate-500"
                                                    x-text="item.language_code"></td>
                                                <td class="px-4 py-3 font-bold text-slate-900" x-text="item.title"></td>
                                                <td class="px-4 py-3"><span
                                                        class="rounded-full px-2 py-1 text-xs font-black"
                                                        :class="item.is_active ? 'bg-emerald-50 text-emerald-700' :
                                                            'bg-slate-100 text-slate-500'"
                                                        x-text="item.is_active ? 'Aktif' : 'Pasif'"></span></td>
                                                <td class="px-4 py-3 text-right"><button type="button"
                                                        @click="openItem(item)"
                                                        class="rounded-xl bg-slate-950 px-3 py-1.5 text-xs font-black text-white">Ac</button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </template>

                            <template x-if="activeModule === 'faq'">
                                <table class="min-w-full divide-y divide-slate-100 text-sm">
                                    <thead
                                        class="bg-slate-50 text-left text-xs font-black uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3">Soru</th>
                                            <th class="px-4 py-3">Dil</th>
                                            <th class="px-4 py-3">Kategori</th>
                                            <th class="px-4 py-3">Durum</th>
                                            <th class="px-4 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="item in items" :key="item.id">
                                            <tr class="hover:bg-indigo-50/40">
                                                <td class="max-w-[420px] px-4 py-3"><span
                                                        class="line-clamp-2 font-bold text-slate-900"
                                                        x-text="item.question"></span></td>
                                                <td class="px-4 py-3 text-xs font-bold text-slate-500"
                                                    x-text="item.language_code"></td>
                                                <td class="px-4 py-3 text-xs font-bold text-slate-500"
                                                    x-text="`${item.category} / ${item.screen}`"></td>
                                                <td class="px-4 py-3"><span
                                                        class="rounded-full px-2 py-1 text-xs font-black"
                                                        :class="item.is_active ? 'bg-emerald-50 text-emerald-700' :
                                                            'bg-slate-100 text-slate-500'"
                                                        x-text="item.is_active ? 'Aktif' : 'Pasif'"></span></td>
                                                <td class="px-4 py-3 text-right"><button type="button"
                                                        @click="openItem(item)"
                                                        class="rounded-xl bg-slate-950 px-3 py-1.5 text-xs font-black text-white">Ac</button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </template>

                            <div x-show="!loading && items.length === 0"
                                class="p-10 text-center text-sm font-semibold text-slate-400">
                                Kayit bulunamadi.
                            </div>
                        </div>


                    </section>

                    <section class="min-w-0 bg-slate-50">
                        <template x-if="!activeTab">
                            <div class="flex h-full min-h-[560px] items-center justify-center p-6">
                                <div
                                    class="max-w-sm rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm">
                                    <div
                                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-cyan-400 to-pink-500 text-xl font-black text-white">
                                        D</div>
                                    <h3 class="mt-4 text-lg font-black text-slate-950">Workspace bos</h3>
                                    <p class="mt-2 text-sm leading-6 text-slate-500">Listeden bir kayit ac veya yeni kayit
                                        olustur. Acilan kayitlar sekme olarak burada kalir.</p>
                                </div>
                            </div>
                        </template>

                        <template x-if="activeTab">
                            <div class="h-full overflow-y-auto p-4 xl:p-6">
                                <div class="mb-4 flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-xs font-black uppercase tracking-wide text-slate-400"
                                            x-text="moduleLabel(activeTab.module)"></div>
                                        <h3 class="mt-1 break-words text-xl font-black text-slate-950"
                                            x-text="activeTab.title"></h3>
                                        <p x-show="isTabArchived(activeTab)" class="mt-2 text-xs font-bold text-pink-600">
                                            Arsivdeki kayitlar duzenlenemez. Duzenlemek icin once geri alin.
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" @click="saveTab(activeTab)"
                                            :disabled="saving || isTabArchived(activeTab)"
                                            class="rounded-2xl bg-gradient-to-r from-indigo-600 to-cyan-500 px-4 py-2 text-sm font-black text-white shadow-sm disabled:opacity-40">Kaydet</button>
                                        <button type="button" @click="archiveOrRestore(activeTab)"
                                            class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-black text-slate-700">Aksiyon</button>
                                    </div>
                                </div>

                                <template x-if="activeTab.module === 'keys'">
                                    <div class="space-y-4">
                                        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                                            <div class="grid gap-3">
                                                <input x-model="activeTab.data.item.key" @input="markDirty(activeTab)"
                                                    placeholder="key"
                                                    class="h-11 rounded-2xl border border-slate-200 px-3 font-mono text-sm">
                                                <textarea x-model="activeTab.data.item.default_value" @input="markDirty(activeTab)" rows="3"
                                                    placeholder="Varsayilan deger" class="rounded-2xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <input x-model="activeTab.data.item.raw_category"
                                                        @input="markDirty(activeTab)" placeholder="Kategori"
                                                        class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                                    <input x-model="activeTab.data.item.raw_screen"
                                                        @input="markDirty(activeTab)" placeholder="Ekran / modul"
                                                        class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                                </div>
                                                <label
                                                    class="flex items-center gap-2 text-sm font-bold text-slate-700"><input
                                                        type="checkbox" x-model="activeTab.data.item.is_active"
                                                        @change="markDirty(activeTab)"
                                                        class="rounded border-slate-300 text-indigo-600"> Aktif</label>
                                            </div>
                                        </div>
                                        <div class="space-y-3">
                                            <template x-for="translation in activeTab.data.translations"
                                                :key="translation.app_language_id">
                                                <div class="rounded-3xl border bg-white p-4 shadow-sm"
                                                    :class="translation.is_missing ? 'border-pink-200' : 'border-slate-200'">
                                                    <div class="mb-2 flex items-center justify-between">
                                                        <div class="font-black text-slate-950"
                                                            x-text="`${translation.language_code} - ${translation.language_name}`">
                                                        </div>
                                                        <span class="rounded-full px-2 py-1 text-xs font-black"
                                                            :class="translation.is_missing ? 'bg-pink-50 text-pink-700' :
                                                                'bg-cyan-50 text-cyan-700'"
                                                            x-text="translation.is_missing ? 'Eksik' : 'Hazir'"></span>
                                                    </div>
                                                    <textarea x-model="translation.value" @input="translation.is_missing = !translation.value; markDirty(activeTab)"
                                                        rows="3" class="w-full rounded-2xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                                                    <label
                                                        class="mt-2 flex items-center gap-2 text-sm font-bold text-slate-700"><input
                                                            type="checkbox" x-model="translation.is_active"
                                                            @change="markDirty(activeTab)"
                                                            class="rounded border-slate-300 text-indigo-600"> Aktif</label>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="activeTab.module === 'languages'">
                                    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <div class="grid gap-3">
                                            <input x-model="activeTab.data.code" @input="markDirty(activeTab)"
                                                placeholder="kod"
                                                class="h-11 rounded-2xl border border-slate-200 px-3 font-mono text-sm">
                                            <input x-model="activeTab.data.name" @input="markDirty(activeTab)"
                                                placeholder="Dil adi"
                                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                            <input x-model="activeTab.data.native_name" @input="markDirty(activeTab)"
                                                placeholder="Yerel ad"
                                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                            <input type="number" x-model="activeTab.data.sort_order"
                                                @input="markDirty(activeTab)"
                                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input
                                                    type="checkbox" x-model="activeTab.data.is_active"
                                                    @change="markDirty(activeTab)" :disabled="activeTab.data.is_default"
                                                    class="rounded border-slate-300 text-indigo-600"> Aktif</label>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="activeTab.module === 'legal'">
                                    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <div class="grid gap-3">
                                            <select x-model="activeTab.data.type" @change="markDirty(activeTab)"
                                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                                <template x-for="entry in Object.entries(meta.legalTypes)"
                                                    :key="entry[0]">
                                                    <option :value="entry[0]" x-text="entry[1]"></option>
                                                </template>
                                            </select>
                                            <select x-model="activeTab.data.app_language_id"
                                                @change="markDirty(activeTab)"
                                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                                <template x-for="language in meta.languages" :key="language.id">
                                                    <option :value="language.id"
                                                        x-text="`${language.code} - ${language.name}`"></option>
                                                </template>
                                            </select>
                                            <input x-model="activeTab.data.title" @input="markDirty(activeTab)"
                                                placeholder="Baslik"
                                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                            <textarea x-model="activeTab.data.content" @input="markDirty(activeTab)" rows="14" placeholder="Icerik"
                                                class="rounded-2xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                                            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input
                                                    type="checkbox" x-model="activeTab.data.is_active"
                                                    @change="markDirty(activeTab)"
                                                    class="rounded border-slate-300 text-indigo-600"> Aktif</label>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="activeTab.module === 'faq'">
                                    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <div class="grid gap-3">
                                            <select x-model="activeTab.data.app_language_id"
                                                @change="markDirty(activeTab)"
                                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                                <template x-for="language in meta.languages" :key="language.id">
                                                    <option :value="language.id"
                                                        x-text="`${language.code} - ${language.name}`"></option>
                                                </template>
                                            </select>
                                            <input x-model="activeTab.data.question" @input="markDirty(activeTab)"
                                                placeholder="Soru"
                                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                            <textarea x-model="activeTab.data.answer" @input="markDirty(activeTab)" rows="8" placeholder="Cevap"
                                                class="rounded-2xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                                            <div class="grid grid-cols-3 gap-3">
                                                <input x-model="activeTab.data.raw_category" @input="markDirty(activeTab)"
                                                    placeholder="Kategori"
                                                    class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                                <input x-model="activeTab.data.raw_screen" @input="markDirty(activeTab)"
                                                    placeholder="Ekran"
                                                    class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                                <input type="number" x-model="activeTab.data.sort_order"
                                                    @input="markDirty(activeTab)"
                                                    class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                            </div>
                                            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input
                                                    type="checkbox" x-model="activeTab.data.is_active"
                                                    @change="markDirty(activeTab)"
                                                    class="rounded border-slate-300 text-indigo-600"> Aktif</label>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </section>
                </div>
            </main>
        </div>

        <div x-show="createModal.open" x-cloak x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
            <div class="absolute inset-0" @click="createModal.open = false"></div>
            <form @submit.prevent="saveCreate()"
                class="relative w-full max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-black uppercase tracking-wide text-indigo-500">Yeni kayit</div>
                        <h3 class="mt-1 text-xl font-black text-slate-950" x-text="activeModuleSingular()"></h3>
                    </div>
                    <button type="button" @click="createModal.open = false"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-black text-slate-500">Kapat</button>
                </div>
                <div class="grid gap-3">
                    <template x-if="activeModule === 'keys'">
                        <div class="grid gap-3">
                            <input x-model="createModal.form.key" placeholder="profile.help.title"
                                class="h-11 rounded-2xl border border-slate-200 px-3 font-mono text-sm">
                            <textarea x-model="createModal.form.default_value" rows="4" placeholder="Varsayilan deger"
                                class="rounded-2xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                            <div class="grid grid-cols-2 gap-3">
                                <input x-model="createModal.form.category" placeholder="Kategori"
                                    class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                <input x-model="createModal.form.screen" placeholder="Ekran"
                                    class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                            </div>
                            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox"
                                    x-model="createModal.form.is_active" class="rounded border-slate-300 text-indigo-600">
                                Aktif</label>
                        </div>
                    </template>

                    <template x-if="activeModule === 'languages'">
                        <div class="grid gap-3">
                            <input x-model="createModal.form.code" placeholder="tr"
                                class="h-11 rounded-2xl border border-slate-200 px-3 font-mono text-sm">
                            <input x-model="createModal.form.name" placeholder="Dil adi"
                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                            <input x-model="createModal.form.native_name" placeholder="Yerel ad"
                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                            <input type="number" x-model="createModal.form.sort_order"
                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox"
                                    x-model="createModal.form.is_active" class="rounded border-slate-300 text-indigo-600">
                                Aktif</label>
                            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox"
                                    x-model="createModal.form.is_default"
                                    class="rounded border-slate-300 text-indigo-600"> Varsayilan</label>
                        </div>
                    </template>

                    <template x-if="activeModule === 'legal'">
                        <div class="grid gap-3">
                            <select x-model="createModal.form.type"
                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                <template x-for="entry in Object.entries(meta.legalTypes)" :key="entry[0]">
                                    <option :value="entry[0]" x-text="entry[1]"></option>
                                </template>
                            </select>
                            <select x-model="createModal.form.app_language_id"
                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                <template x-for="language in meta.languages" :key="language.id">
                                    <option :value="language.id" x-text="`${language.code} - ${language.name}`">
                                    </option>
                                </template>
                            </select>
                            <input x-model="createModal.form.title" placeholder="Baslik"
                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                            <textarea x-model="createModal.form.content" rows="8" placeholder="Icerik"
                                class="rounded-2xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox"
                                    x-model="createModal.form.is_active" class="rounded border-slate-300 text-indigo-600">
                                Aktif</label>
                        </div>
                    </template>

                    <template x-if="activeModule === 'faq'">
                        <div class="grid gap-3">
                            <select x-model="createModal.form.app_language_id"
                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                <template x-for="language in meta.languages" :key="language.id">
                                    <option :value="language.id" x-text="`${language.code} - ${language.name}`">
                                    </option>
                                </template>
                            </select>
                            <input x-model="createModal.form.question" placeholder="Soru"
                                class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                            <textarea x-model="createModal.form.answer" rows="5" placeholder="Cevap"
                                class="rounded-2xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                            <div class="grid grid-cols-3 gap-3">
                                <input x-model="createModal.form.category" placeholder="Kategori"
                                    class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                <input x-model="createModal.form.screen" placeholder="Ekran"
                                    class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                                <input type="number" x-model="createModal.form.sort_order"
                                    class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
                            </div>
                            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox"
                                    x-model="createModal.form.is_active" class="rounded border-slate-300 text-indigo-600">
                                Aktif</label>
                        </div>
                    </template>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="createModal.open = false"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-600">Vazgec</button>
                    <button class="rounded-2xl bg-slate-950 px-5 py-2 text-sm font-black text-white">Olustur</button>
                </div>
            </form>
        </div>

        <div x-show="confirmModal.open" x-cloak x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
            <div class="absolute inset-0" @click="confirmModal.open = false"></div>
            <div class="relative w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
                <h3 class="text-lg font-black text-slate-950" x-text="confirmModal.title"></h3>
                <p class="mt-2 text-sm leading-6 text-slate-500" x-text="confirmModal.message"></p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="confirmModal.open = false"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-600">Vazgec</button>
                    <button type="button" @click="runConfirm()"
                        class="rounded-2xl bg-pink-600 px-5 py-2 text-sm font-black text-white"
                        x-text="confirmModal.actionLabel"></button>
                </div>
            </div>
        </div>

        <div class="fixed bottom-5 right-5 z-50 space-y-2">

            <template x-for="toast in toasts" :key="toast.id">
                <div class="rounded-2xl border bg-white px-4 py-3 text-sm font-bold shadow-xl"
                    :class="toast.type === 'error' ? 'border-pink-200 text-pink-700' : 'border-cyan-200 text-slate-800'"
                    x-text="toast.message"></div>
            </template>
            <div style="background-color: blueviolet;border-radius: 9999px;color: white;display: inline-block;font-size: 12px;font-weight: 700"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-xs font-bold text-slate-500 xl:px-6">
                <button type="button" @click="loadList(Math.max(1, pagination.currentPage - 1))"
                    :disabled="pagination.currentPage <= 1"
                    class="rounded-xl border border-slate-200 px-3 py-2 disabled:opacity-40">Onceki</button>
                <span>Sayfa <span x-text="pagination.currentPage || 1"></span> / <span
                        x-text="pagination.lastPage || 1"></span></span>
                <button type="button" @click="loadList(Math.min(pagination.lastPage, pagination.currentPage + 1))"
                    :disabled="pagination.currentPage >= pagination.lastPage"
                    class="rounded-xl border border-slate-200 px-3 py-2 disabled:opacity-40">Sonraki</button>
            </div>
        </div>
    </div>

    <script>
        function dilMetinSpa(config) {
            return {
                config,
                modules: [{
                        id: 'keys',
                        label: '',
                        short: 'K',
                        hint: 'Translation keys'
                    },
                    {
                        id: 'languages',
                        label: 'Diller',
                        short: 'D',
                        hint: 'Aktif diller'
                    },
                    {
                        id: 'legal',
                        label: 'Yasal Metinler',
                        short: 'Y',
                        hint: 'Politika metinleri'
                    },
                    {
                        id: 'faq',
                        label: 'FAQ',
                        short: 'F',
                        hint: 'Yardim icerigi'
                    },
                ],
                activeModule: config.initialModule || 'keys',
                filters: {
                    search: '',
                    status: '',
                    archive: '',
                    language_id: config.defaultLanguageId || '',
                    category: '',
                    screen: '',
                    type: '',
                    missing: false
                },
                meta: {
                    languages: config.languages || [],
                    activeLanguages: config.activeLanguages || [],
                    legalTypes: config.legalTypes || {}
                },
                items: [],
                pagination: {
                    currentPage: 1,
                    lastPage: 1,
                    total: 0
                },
                loading: false,
                saving: false,
                openTabs: [],
                activeTabId: null,
                createModal: {
                    open: false,
                    form: {}
                },
                confirmModal: {
                    open: false,
                    title: '',
                    message: '',
                    actionLabel: 'Onayla',
                    action: null
                },
                toasts: [],

                get activeTab() {
                    return this.openTabs.find((tab) => tab.id === this.activeTabId) || null;
                },

                async init() {
                    this.restoreTabs();
                    await this.refreshPersistedTabs();
                    await this.loadMeta();
                    this.loadList(1);
                },

                endpoint(module = this.activeModule) {
                    return this.config.endpoints[module];
                },

                async request(url, options = {}) {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.config.csrf,
                            ...(options.headers || {}),
                        },
                        ...options,
                    });
                    const payload = await response.json().catch(() => ({
                        success: false,
                        message: 'Sunucu yaniti okunamadi.',
                        errors: {}
                    }));
                    if (!response.ok || payload.success === false) {
                        throw payload;
                    }
                    return payload;
                },

                async loadMeta() {
                    try {
                        const payload = await this.request(this.config.endpoints.meta);
                        this.meta.languages = payload.data.languages || this.meta.languages;
                        this.meta.activeLanguages = payload.data.activeLanguages || this.meta.activeLanguages;
                        this.meta.legalTypes = payload.data.legalTypes || this.meta.legalTypes;
                    } catch (error) {
                        this.toast(error.message || 'Meta veriler yuklenemedi.', 'error');
                    }
                },

                normalizeArchived(value) {
                    return value === true || value === 1 || value === '1' || value === 'true';
                },

                isTabArchived(tab) {
                    if (!tab) return false;
                    return this.normalizeArchived(tab.data?.item?.is_archived) || this.normalizeArchived(tab.data
                        ?.is_archived);
                },

                syncTabArchivedState(tab, data) {
                    const item = data.item || data;
                    const archived = this.normalizeArchived(item?.is_archived);

                    if (tab.module === 'keys' && tab.data?.item) {
                        tab.data.item.is_archived = archived;
                        return;
                    }

                    if (tab.data) {
                        tab.data.is_archived = archived;
                    }
                },

                async refreshPersistedTabs() {
                    if (this.openTabs.length === 0) return;

                    const refreshedTabs = [];

                    for (const savedTab of this.openTabs) {
                        if (!savedTab?.module || !savedTab?.itemId) continue;

                        try {
                            const payload = await this.request(`${this.endpoint(savedTab.module)}/${savedTab.itemId}`);

                            if (savedTab.dirty) {
                                this.syncTabArchivedState(savedTab, payload.data);
                                refreshedTabs.push(savedTab);
                                continue;
                            }

                            const freshTab = this.makeTab(savedTab.module, payload.data);
                            refreshedTabs.push(freshTab);
                        } catch (_) {
                            if (savedTab.dirty) {
                                refreshedTabs.push(savedTab);
                            }
                        }
                    }

                    this.openTabs = refreshedTabs;

                    if (!this.openTabs.some((tab) => tab.id === this.activeTabId)) {
                        this.activeTabId = this.openTabs[0]?.id || null;
                    }

                    this.persistTabs();
                },

                async loadList(page = 1) {
                    this.loading = true;
                    const params = new URLSearchParams();
                    params.set('page', page);
                    params.set('per_page', 30);
                    Object.entries(this.filters).forEach(([key, value]) => {
                        if (value === '' || value === false || value === null || value === undefined) return;
                        if ((key === 'category' || key === 'screen') && !['keys', 'faq'].includes(this
                                .activeModule)) return;
                        if (key === 'type' && this.activeModule !== 'legal') return;
                        if (key === 'missing' && this.activeModule !== 'keys') return;
                        if (key === 'language_id' && this.activeModule === 'languages') return;
                        params.set(key, value === true ? '1' : value);
                    });
                    try {
                        const payload = await this.request(`${this.endpoint()}?${params.toString()}`);
                        this.items = payload.data.items || [];
                        this.pagination = payload.data.pagination || {
                            currentPage: 1,
                            lastPage: 1,
                            total: this.items.length
                        };
                    } catch (error) {
                        this.toast(error.message || 'Liste yuklenemedi.', 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                switchModule(module) {
                    this.activeModule = module;
                    this.filters.category = '';
                    this.filters.screen = '';
                    this.filters.type = '';
                    this.filters.missing = false;
                    this.loadList(1);
                },

                async openItem(item) {
                    try {
                        const payload = await this.request(`${this.endpoint(this.activeModule)}/${item.id}`);
                        const tab = this.makeTab(this.activeModule, payload.data);
                        const index = this.openTabs.findIndex((candidate) => candidate.id === tab.id);
                        if (index >= 0) {
                            this.openTabs[index] = tab;
                        } else {
                            this.openTabs.push(tab);
                        }
                        this.activeTabId = tab.id;
                        this.persistTabs();
                    } catch (error) {
                        this.toast(error.message || 'Kayit acilamadi.', 'error');
                    }
                },

                makeTab(module, data) {
                    const item = data.item || data;
                    const title = module === 'keys' ? item.key : module === 'languages' ? `${item.code} - ${item.name}` :
                        module === 'legal' ? item.title : item.question;
                    return {
                        id: `${module}:${item.id}`,
                        module,
                        itemId: item.id,
                        title,
                        data: JSON.parse(JSON.stringify(data)),
                        dirty: false,
                        errors: {}
                    };
                },

                activateTab(id) {
                    this.activeTabId = id;
                },

                markDirty(tab) {
                    tab.dirty = true;
                    this.persistTabs();
                },

                requestCloseTab(id) {
                    const tab = this.openTabs.find((candidate) => candidate.id === id);
                    if (tab?.dirty) {
                        this.confirmModal = {
                            open: true,
                            title: 'Kaydedilmemis degisiklik',
                            message: 'Bu tabi kapatirsan degisiklikler kaybolur.',
                            actionLabel: 'Kapat',
                            action: () => this.closeTab(id, true),
                        };
                        return;
                    }
                    this.closeTab(id, true);
                },

                closeTab(id, confirmed = false) {
                    if (!confirmed) return this.requestCloseTab(id);
                    this.openTabs = this.openTabs.filter((tab) => tab.id !== id);
                    if (this.activeTabId === id) {
                        this.activeTabId = this.openTabs.at(-1)?.id || null;
                    }
                    this.confirmModal.open = false;
                    this.persistTabs();
                },

                async saveTab(tab) {
                    this.saving = true;
                    tab.errors = {};
                    try {
                        let payload;
                        if (tab.module === 'keys') {
                            payload = await this.request(`${this.config.endpoints.keys}/${tab.itemId}`, {
                                method: 'PUT',
                                body: JSON.stringify({
                                    key: tab.data.item.key,
                                    default_value: tab.data.item.default_value,
                                    category: tab.data.item.raw_category,
                                    screen: tab.data.item.raw_screen,
                                    is_active: tab.data.item.is_active,
                                }),
                            });
                            payload = await this.request(`${this.config.endpoints.keys}/${tab.itemId}/ceviriler`, {
                                method: 'PUT',
                                body: JSON.stringify({
                                    translations: tab.data.translations
                                }),
                            });
                        } else {
                            payload = await this.request(`${this.endpoint(tab.module)}/${tab.itemId}`, {
                                method: 'PUT',
                                body: JSON.stringify(this.formPayload(tab.module, tab.data)),
                            });
                        }
                        const refreshed = this.makeTab(tab.module, payload.data);
                        Object.assign(tab, refreshed, {
                            dirty: false
                        });
                        this.activeTabId = tab.id;
                        this.persistTabs();
                        this.loadList(this.pagination.currentPage || 1);
                        this.loadMeta();
                        this.toast(payload.message || 'Kaydedildi.');
                    } catch (error) {
                        tab.errors = error.errors || {};
                        this.toast(error.message || 'Kaydedilemedi.', 'error');
                    } finally {
                        this.saving = false;
                    }
                },

                formPayload(module, data) {
                    if (module === 'languages') {
                        return {
                            code: data.code,
                            name: data.name,
                            native_name: data.native_name,
                            sort_order: data.sort_order,
                            is_active: data.is_active,
                        };
                    }
                    if (module === 'legal') {
                        return {
                            type: data.type,
                            app_language_id: data.app_language_id,
                            title: data.title,
                            content: data.content,
                            is_active: data.is_active,
                        };
                    }
                    return {
                        app_language_id: data.app_language_id,
                        question: data.question,
                        answer: data.answer,
                        category: data.raw_category,
                        screen: data.raw_screen,
                        sort_order: data.sort_order,
                        is_active: data.is_active,
                    };
                },

                openCreateModal() {
                    this.createModal = {
                        open: true,
                        form: this.blankForm(this.activeModule)
                    };
                },

                blankForm(module) {
                    if (module === 'keys') return {
                        key: '',
                        default_value: '',
                        category: '',
                        screen: '',
                        is_active: true
                    };
                    if (module === 'languages') return {
                        code: '',
                        name: '',
                        native_name: '',
                        sort_order: 0,
                        is_active: true,
                        is_default: false
                    };
                    if (module === 'legal') return {
                        type: Object.keys(this.meta.legalTypes)[0] || 'privacy',
                        app_language_id: this.meta.languages[0]?.id || '',
                        title: '',
                        content: '',
                        is_active: true
                    };
                    return {
                        app_language_id: this.meta.languages[0]?.id || '',
                        question: '',
                        answer: '',
                        category: 'help',
                        screen: 'profile.help',
                        sort_order: 0,
                        is_active: true
                    };
                },

                async saveCreate() {
                    try {
                        const payload = await this.request(this.endpoint(), {
                            method: 'POST',
                            body: JSON.stringify(this.createModal.form),
                        });
                        this.createModal.open = false;
                        this.toast(payload.message || 'Olusturuldu.');
                        this.loadList(1);
                        this.loadMeta();
                        const tab = this.makeTab(this.activeModule, payload.data);
                        this.openTabs.push(tab);
                        this.activeTabId = tab.id;
                        this.persistTabs();
                    } catch (error) {
                        this.toast(error.message || 'Olusturulamadi.', 'error');
                    }
                },

                archiveOrRestore(tab) {
                    const archived = this.isTabArchived(tab);
                    this.confirmModal = {
                        open: true,
                        title: archived ? 'Arsivden geri al' : 'Arsivle',
                        message: archived ? 'Bu kayit tekrar aktif listeye alinsin mi?' : 'Bu kayit arsive alinsin mi?',
                        actionLabel: archived ? 'Geri Al' : 'Arsivle',
                        action: () => this.runArchive(tab, archived),
                    };
                },

                async runArchive(tab, archived) {
                    try {
                        const url = `${this.endpoint(tab.module)}/${tab.itemId}${archived ? '/geri-al' : ''}`;
                        const payload = await this.request(url, {
                            method: archived ? 'PATCH' : 'DELETE'
                        });
                        this.confirmModal.open = false;
                        this.toast(payload.message || 'Islem tamam.');
                        this.closeTab(tab.id, true);
                        this.loadList(1);
                        this.loadMeta();
                    } catch (error) {
                        this.toast(error.message || 'Islem yapilamadi.', 'error');
                    }
                },

                runConfirm() {
                    if (this.confirmModal.action) this.confirmModal.action();
                },

                persistTabs() {
                    localStorage.setItem('dil-metin-open-tabs', JSON.stringify({
                        tabs: this.openTabs,
                        active: this.activeTabId
                    }));
                },

                restoreTabs() {
                    try {
                        const saved = JSON.parse(localStorage.getItem('dil-metin-open-tabs') || '{}');
                        this.openTabs = Array.isArray(saved.tabs) ? saved.tabs : [];
                        this.activeTabId = saved.active || this.openTabs[0]?.id || null;
                    } catch (_) {
                        this.openTabs = [];
                    }
                },

                moduleLabel(module) {
                    return this.modules.find((item) => item.id === module)?.label || module;
                },

                activeModuleLabel() {
                    return this.moduleLabel(this.activeModule);
                },

                activeModuleSingular() {
                    return {
                        keys: 'Key',
                        languages: 'Dil',
                        legal: 'Yasal Metin',
                        faq: 'FAQ'
                    } [this.activeModule] || 'Kayit';
                },

                toast(message, type = 'success') {
                    const id = Date.now() + Math.random();
                    this.toasts.push({
                        id,
                        message,
                        type
                    });
                    setTimeout(() => {
                        this.toasts = this.toasts.filter((toast) => toast.id !== id);
                    }, 3500);
                },
            };
        }
    </script>
@endsection
