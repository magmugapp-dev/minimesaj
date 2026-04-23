<!DOCTYPE html>
<html lang="tr" class="h-full bg-gray-100">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Giriş — MiniMesaj Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="h-full flex items-center justify-center">
    <div class="w-full max-w-md px-4">
        <div class="rounded-xl bg-white p-8 shadow-lg">
            <div class="mb-6 text-center">
                <h1 class="text-2xl font-bold text-gray-900">MiniMesaj</h1>
            </div>

            {{-- Başarı mesajı --}}
            @if (session('basari'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 p-3">
                    <div class="flex">
                        <svg class="h-5 w-5 text-green-400 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-sm text-green-700">{{ session('basari') }}</p>
                    </div>
                </div>
            @endif

            {{-- Bilgi mesajı --}}
            @if (session('bilgi'))
                <div class="mb-4 rounded-md bg-blue-50 border border-blue-200 p-3">
                    <div class="flex">
                        <svg class="h-5 w-5 text-blue-400 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-sm text-blue-700">{{ session('bilgi') }}</p>
                    </div>
                </div>
            @endif

            {{-- Uyarı mesajı --}}
            @if (session('uyari'))
                <div class="mb-4 rounded-md bg-yellow-50 border border-yellow-200 p-3">
                    <div class="flex">
                        <svg class="h-5 w-5 text-yellow-400 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-sm text-yellow-700">{{ session('uyari') }}</p>
                    </div>
                </div>
            @endif

            {{-- Hata mesajları --}}
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 border border-red-200 p-3">
                    <div class="flex">
                        <svg class="h-5 w-5 text-red-400 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            @foreach ($errors->all() as $hata)
                                <p class="text-sm text-red-700">{{ $hata }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.giris') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">E-posta</label>
                    <input type="email" name="email" id="email" required autofocus value="{{ old('email') }}"
                        class="mt-1 block w-full rounded-md border {{ $errors->has('email') ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500' }} px-3 py-2 text-sm shadow-sm focus:outline-none">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Şifre</label>
                    <input type="password" name="password" id="password" required
                        class="mt-1 block w-full rounded-md border {{ $errors->has('email') ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500' }} px-3 py-2 text-sm shadow-sm focus:outline-none">
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="beni_hatirla" id="beni_hatirla"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="beni_hatirla" class="ml-2 block text-sm text-gray-700">Beni hatırla</label>
                </div>
                <button type="submit"
                    class="w-full rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Giriş Yap
                </button>
            </form>
        </div>

    </div>
</body>

</html>
