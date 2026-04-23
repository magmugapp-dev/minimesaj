@extends('admin.layout.ana')

@section('baslik', 'Hediye Duzenle')

@section('icerik')
    <div class="p-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="mb-6 text-lg font-semibold text-gray-900">{{ $hediye->ad }} hediyesini duzenle</h2>

            <form method="POST" action="{{ route('admin.hediyeler.update', $hediye) }}">
                @include('admin.hediyeler._form')
            </form>
        </div>
    </div>
@endsection
