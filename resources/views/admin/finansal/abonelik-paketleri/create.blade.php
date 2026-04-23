@extends('admin.layout.ana')

@section('baslik', 'Abonelik Paketi Ekle')

@section('icerik')
    <div class="space-y-6 p-6">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">Yeni abonelik paketi</h2>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <form method="POST" action="{{ route('admin.finansal.abonelik-paketleri.store') }}">
                @include('admin.finansal.abonelik-paketleri._form')
            </form>
        </div>
    </div>
@endsection
