@extends('admin.layout.ana')

@section('baslik', 'AI ZIP Import')

@section('icerik')
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.ai.import.store') }}" class="max-w-2xl rounded-lg border border-gray-200 bg-white p-6 shadow">
        @csrf
        <h1 class="text-2xl font-bold text-gray-900">Toplu AI karakter import</h1>
        <p class="mt-2 text-sm text-gray-500">ZIP icinde tek JSON dosyasi ve her karakter icin <code>character_id/profile.png</code> beklenir. Mevcut character_id kayitlari atlanir.</p>
        <input type="file" name="zip" accept=".zip" class="mt-5 w-full rounded-lg border border-gray-300 p-3 text-sm">
        <button class="mt-4 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Import et</button>
    </form>
@endsection
