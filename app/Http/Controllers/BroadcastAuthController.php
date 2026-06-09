<?php

namespace App\Http\Controllers;

use App\Models\GamePlayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pusher\Pusher;

class BroadcastAuthController extends Controller
{
    public function authenticate(Request $request): JsonResponse
    {
        $channelName = $request->channel_name;
        $socketId    = $request->socket_id;

        abort_if(! $channelName || ! $socketId, 422);

        if (str_starts_with($channelName, 'presence-room.')) {
            return $this->presenceRoomAuth($channelName, $socketId);
        }

        // Other channels (game.{id}, player.{id}, etc.).
        // Guests have no auth()->user() but do hold a game_player_id in session,
        // so authorize them via the session rather than \Broadcast::auth().
        $gamePlayerId = session('game_player_id');

        if ($gamePlayerId) {
            $player = GamePlayer::find($gamePlayerId);
            abort_if(! $player, 403);

            $cfg    = config('broadcasting.connections.reverb');
            $pusher = new Pusher($cfg['key'], $cfg['secret'], $cfg['app_id']);

            $auth = $pusher->authorizeChannel($channelName, $socketId);

            return response()->json(json_decode($auth, true));
        }

        // Authenticated users without a game session (e.g. group channels).
        if (auth()->check()) {
            return response()->json(\Broadcast::auth($request));
        }

        abort(403);
    }

    private function presenceRoomAuth(string $channelName, string $socketId): JsonResponse
    {
        $code = substr($channelName, strlen('presence-room.'));

        $gamePlayerId = session('game_player_id');
        abort_if(! $gamePlayerId, 403);

        $player = GamePlayer::with('room')->find($gamePlayerId);

        abort_if(
            ! $player
            || ! $player->room
            || strtoupper($player->room->code) !== strtoupper($code),
            403
        );

        $cfg = config('broadcasting.connections.reverb');

        $pusher = new Pusher($cfg['key'], $cfg['secret'], $cfg['app_id']);

        $auth = $pusher->authorizePresenceChannel(
            $channelName,
            $socketId,
            $player->id,
            ['display_name' => $player->displayName(), 'id' => $player->id],
        );

        return response()->json(json_decode($auth, true));
    }
}
