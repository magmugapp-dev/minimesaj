@extends('admin.layout.ana')

@section('baslik', 'AI Persona Editor')

@section('icerik')
    @include('admin.ai-v2.partials.persona-form', [
        'mode' => 'edit',
        'action' => route('admin.ai.guncelle', $kullanici),
        'title' => $kullanici->ad . ' ' . $kullanici->soyad,
        'backUrl' => route('admin.ai.goster', $kullanici),
        'backLabel' => 'Detay Sayfasina Don',
        'cancelLabel' => 'Vazgec',
        'submitLabel' => 'Degisiklikleri Kaydet',
    ])
@endsection
