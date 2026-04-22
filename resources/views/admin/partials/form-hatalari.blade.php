@if ($errors->any())
    <div class="studio-alert">
        <div class="studio-alert__row">
            <div class="studio-alert__icon">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm9-3.758c0 4.971-4.029 9-9 9s-9-4.029-9-9 4.029-9 9-9 9 4.029 9 9Z" />
                </svg>
            </div>
            <div class="min-w-0">
                <h3 class="studio-alert__title">Kaydetmeden önce birkaç alanı düzeltelim.</h3>
                <p class="studio-alert__copy">Aşağıdaki doğrulama mesajları formu tamamlamanıza yardımcı olur.</p>
                <ul class="studio-alert__list">
                    @foreach ($errors->all() as $hata)
                        <li>{{ $hata }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
