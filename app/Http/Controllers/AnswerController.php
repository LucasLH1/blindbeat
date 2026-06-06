<?php

namespace App\Http\Controllers;

use App\Actions\EvaluateAnswerAction;
use App\Enums\RoundStatus;
use App\Models\GamePlayer;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnswerController extends Controller
{
    public function store(Request $request, EvaluateAnswerAction $action): JsonResponse
    {
        $validated = $request->validate([
            'answer_text' => ['required', 'string', 'max:255'],
            'room_code' => ['required', 'string', 'size:6'],
        ]);

        $gamePlayerId = session('game_player_id');

        if (! $gamePlayerId) {
            return response()->json(['error' => 'Not in a room'], 401);
        }

        $gamePlayer = GamePlayer::find($gamePlayerId);

        if (! $gamePlayer) {
            return response()->json(['error' => 'Player not found'], 401);
        }

        $room = Room::where('code', strtoupper($validated['room_code']))->firstOrFail();

        $currentRound = $room->rounds()
            ->where('status', RoundStatus::Playing)
            ->first();

        if (! $currentRound) {
            return response()->json(['error' => 'No round in progress'], 422);
        }

        try {
            $result = $action->execute($gamePlayer, $currentRound, $validated['answer_text']);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
