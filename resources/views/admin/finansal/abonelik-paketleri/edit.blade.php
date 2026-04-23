@extends('admin.layout.ana')

@section('baslik', 'Abonelik Paketi Duzenle')

@section('icerik')
    <div class="space-y-6 p-6">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">Abonelik paketi duzenle</h2>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <form method="POST" action="{{ route('admin.finansal.abonelik-paketleri.update', $paket) }}">
                @include('admin.finansal.abonelik-paketleri._form')
            </form>
        </div>
    </div>
@endsection
