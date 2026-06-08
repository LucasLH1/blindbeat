<?php

namespace App\Http\Controllers;

use App\Enums\GamePlayerStatus;
use App\Models\GamePlayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $gamePlayerId = session('game_player_id');

        if (! $gamePlayerId) {
            return response()->json(['ok' => false], 401);
        }

        GamePlayer::where('id', $gamePlayerId)
            ->where('status', GamePlayerStatus::Active)
            ->update(['last_seen_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
