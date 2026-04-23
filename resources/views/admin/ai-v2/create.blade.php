@extends('admin.layout.ana')

@section('baslik', 'Yeni AI Persona')

@section('icerik')
    @include('admin.ai-v2.partials.persona-form', [
        'mode' => 'create',
        'action' => route('admin.ai.kaydet'),
        'title' => 'Yeni AI Persona',
        'backUrl' => route('admin.ai.index'),
        'backLabel' => 'AI Studio',
        'cancelLabel' => 'AI Studio',
        'submitLabel' => 'AI Personayi Olustur',
    ])
@endsection
