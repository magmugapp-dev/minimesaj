@extends('admin.layout.ana')

@section('baslik', 'Yeni Influencer Persona')

@section('icerik')
    @include('admin.ai-v2.partials.persona-form', [
        'mode' => 'create',
        'action' => route('admin.influencer.kaydet'),
        'title' => 'Yeni Influencer Persona',
        'backUrl' => route('admin.influencer.index'),
        'backLabel' => 'Influencer Listesi',
        'cancelLabel' => 'Influencer Listesi',
        'submitLabel' => 'Influencer Hesabini Olustur',
        'showNavigation' => false,
        'preSectionsView' => 'admin.influencer.partials.instagram-section',
    ])
@endsection
