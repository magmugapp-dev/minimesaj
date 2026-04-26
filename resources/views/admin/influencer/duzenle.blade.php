@extends('admin.layout.ana')

@section('baslik', 'Influencer Persona Editor')

@section('icerik')
    @include('admin.ai-v2.partials.persona-form', [
        'mode' => 'edit',
        'action' => route('admin.influencer.guncelle', $kullanici),
        'title' => $kullanici->ad . ' ' . $kullanici->soyad,
        'backUrl' => route('admin.influencer.goster', $kullanici),
        'backLabel' => 'Detay Sayfasina Don',
        'cancelLabel' => 'Vazgec',
        'submitLabel' => 'Degisiklikleri Kaydet',
        'showNavigation' => false,
        'preSectionsView' => 'admin.influencer.partials.instagram-section',
    ])
@endsection
