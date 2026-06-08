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

        // Other channels (private player.{id}, etc.) require standard auth
        return response()->json(\Broadcast::auth($request));
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
