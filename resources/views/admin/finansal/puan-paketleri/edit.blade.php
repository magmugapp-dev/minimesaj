@extends('admin.layout.ana')

@section('baslik', 'Puan Paketi Düzenle')

@section('icerik')
    <div class="p-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="mb-6 text-lg font-semibold text-gray-900">{{ $paket->kod }} paketini düzenle</h2>

            <form method="POST" action="{{ route('admin.finansal.puan-paketleri.update', $paket) }}">
                @include('admin.finansal.puan-paketleri._form')
            </form>
        </div>
    </div>
@endsection
