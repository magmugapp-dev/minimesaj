<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppContentService;
use Illuminate\Http\Request;

class AppContentController extends Controller
{
    public function __construct(private AppContentService $contentService) {}

    public function __invoke(Request $request)
    {
        $payload = $this->contentService->payload($request->query('lang'));
        $version = $payload['version'];

        return response()
            ->json($payload)
            ->setEtag($version)
            ->header('Cache-Control', 'public, max-age=300');
    }
}
