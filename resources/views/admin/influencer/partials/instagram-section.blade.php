@php
    $instagramHesap = $instagramHesap ?? $kullanici?->instagramHesaplari?->first();
@endphp

<section class="ai-console-panel">
    <div class="ai-console-panel__header">
        <div>
            <h2 class="ai-console-panel__title">Instagram Operasyonu</h2>
            <p class="ai-console-panel__copy">Influencer hesabinin Instagram baglantisi ve otomasyon ayarlari.</p>
        </div>
    </div>

    <div class="ai-console-control-group ai-console-control-group--2">
        <label>
            <span class="ai-console-label">Instagram Kullanici Adi</span>
            <input
                class="ai-console-input"
                type="text"
                name="instagram_kullanici_adi"
                value="{{ old('instagram_kullanici_adi', $instagramHesap?->instagram_kullanici_adi) }}"
                required
                placeholder="orn. burcinprofile">
        </label>
        <label>
            <span class="ai-console-label">Instagram Profil ID</span>
            <input
                class="ai-console-input"
                type="text"
                name="instagram_profil_id"
                value="{{ old('instagram_profil_id', $instagramHesap?->instagram_profil_id) }}"
                placeholder="1784...">
        </label>
    </div>

    <div class="ai-console-card-grid ai-console-card-grid--3 mt-5">
        <label class="ai-console-toggle flex items-start justify-between gap-4">
            <input type="hidden" name="otomatik_cevap_aktif_mi" value="0">
            <div>
                <div class="ai-console-label !text-xs">Otomatik Cevap</div>
                <p class="mt-2 text-sm leading-6 text-slate-400">DM akisini otomatik cevaplar ile yonetir.</p>
            </div>
            <input
                class="mt-1 h-5 w-5 accent-pink-500"
                type="checkbox"
                name="otomatik_cevap_aktif_mi"
                value="1"
                @checked(old('otomatik_cevap_aktif_mi', $instagramHesap?->otomatik_cevap_aktif_mi ?? true))>
        </label>

        <label class="ai-console-toggle flex items-start justify-between gap-4">
            <input type="hidden" name="yarim_otomatik_mod_aktif_mi" value="0">
            <div>
                <div class="ai-console-label !text-xs">Yari Otomatik Mod</div>
                <p class="mt-2 text-sm leading-6 text-slate-400">Cevap taslaklarini acik tutup manuel kontrolu korur.</p>
            </div>
            <input
                class="mt-1 h-5 w-5 accent-pink-500"
                type="checkbox"
                name="yarim_otomatik_mod_aktif_mi"
                value="1"
                @checked(old('yarim_otomatik_mod_aktif_mi', $instagramHesap?->yarim_otomatik_mod_aktif_mi ?? false))>
        </label>

        <label class="ai-console-toggle flex items-start justify-between gap-4">
            <input type="hidden" name="instagram_hesap_aktif_mi" value="0">
            <div>
                <div class="ai-console-label !text-xs">Instagram Hesabi Aktif</div>
                <p class="mt-2 text-sm leading-6 text-slate-400">Bagli hesap panel akislari icin kullanilabilir olur.</p>
            </div>
            <input
                class="mt-1 h-5 w-5 accent-pink-500"
                type="checkbox"
                name="instagram_hesap_aktif_mi"
                value="1"
                @checked(old('instagram_hesap_aktif_mi', $instagramHesap?->aktif_mi ?? true))>
        </label>
    </div>
</section>
