<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModerationEvent;
use Illuminate\Http\Request;

class AiModerationEventController extends Controller
{
    public function index(Request $request)
    {
        $query = AiModerationEvent::query()
            ->with([
                'aiUser:id,ad,kullanici_adi,profil_resmi',
                'user:id,ad,kullanici_adi,profil_resmi',
            ])
            ->latest();

        if ($eventType = trim((string) $request->input('event_type'))) {
            $query->where('event_type', $eventType);
        }
        if ($dominance = trim((string) $request->input('dominance'))) {
            $query->where('dominance', $dominance);
        }

        $events = $query->paginate(25)->withQueryString();
        $stats = [
            'today' => AiModerationEvent::query()->whereDate('created_at', today())->count(),
            'ghost' => AiModerationEvent::query()->where('event_type', 'like', 'ghost_%')->count(),
            'block' => AiModerationEvent::query()->where('event_type', 'block')->count(),
            'active_lockouts' => AiModerationEvent::query()
                ->whereNotNull('lockout_until')
                ->where('lockout_until', '>', now())
                ->count(),
        ];

        return view('admin.moderasyon.ai-olaylari.index', compact('events', 'stats'));
    }
}
