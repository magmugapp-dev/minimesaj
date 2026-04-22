<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dating\FotografYukleRequest;
use App\Http\Resources\FotografResource;
use App\Models\UserFotografi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FotografController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return FotografResource::collection(
            $request->user()->fotograflar()->orderBy('sira_no')->get()
        )->response();
    }

    public function store(FotografYukleRequest $request): JsonResponse
    {
        $yol = $request->file('dosya')->store('fotograflar/' . $request->user()->id, 'public');

        $fotograf = $request->user()->fotograflar()->create([
            'dosya_yolu' => $yol,
            'sira_no' => $request->user()->fotograflar()->count(),
            'ana_fotograf_mi' => $request->boolean('ana_fotograf_mi'),
        ]);

        return (new FotografResource($fotograf))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, UserFotografi $fotograf): JsonResponse
    {
        Gate::authorize('sil', $fotograf);

        $fotograf->delete();

        return response()->json(['mesaj' => 'Fotoğraf silindi.']);
    }
}
