<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class GirisController extends Controller
{
    public function form()
    {
        if (Auth::check() && Auth::user()->is_admin) {
            return redirect()->route('admin.pano');
        }

        return view('admin.giris');
    }

    public function giris(Request $request)
    {
        $kimlikBilgileri = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Kullanıcıyı bul
        $kullanici = User::where('email', $kimlikBilgileri['email'])->first();

        if (!$kullanici) {
            Log::warning('Admin giriş: kullanıcı bulunamadı', ['email' => $kimlikBilgileri['email']]);
            return back()
                ->withErrors(['email' => 'Bu e-posta adresiyle kayıtlı kullanıcı bulunamadı.'])
                ->onlyInput('email');
        }

        if (!Hash::check($kimlikBilgileri['password'], $kullanici->password)) {
            Log::warning('Admin giriş: şifre hatalı', ['email' => $kimlikBilgileri['email']]);
            return back()
                ->withErrors(['email' => 'Şifre hatalı.'])
                ->onlyInput('email');
        }

        if (!$kullanici->is_admin) {
            Log::warning('Admin giriş: admin yetkisi yok', ['email' => $kimlikBilgileri['email']]);
            return back()
                ->withErrors(['email' => 'Bu hesabın admin yetkisi bulunmuyor.'])
                ->onlyInput('email');
        }

        Auth::login($kullanici, $request->boolean('beni_hatirla'));
        $request->session()->regenerate();

        Log::info('Admin giriş başarılı', ['user_id' => $kullanici->id, 'email' => $kullanici->email]);

        return redirect()->intended(route('admin.pano'));
    }

    public function cikis(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.giris');
    }
}
