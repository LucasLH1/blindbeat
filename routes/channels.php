<?php

use App\Models\GamePlayer;
use App\Models\Room;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Presence channel for the lobby — guests and authenticated users both allowed.
// Return user data so Pusher/Reverb can build the presence membership list.
Broadcast::channel('room.{code}', function ($user, string $code) {
    $room = Room::where('code', $code)->first();
    if (! $room) {
        return false;
    }

    $gamePlayerId = request()->session()->get('game_player_id');
    if (! $gamePlayerId) {
        return false;
    }

    $player = GamePlayer::find($gamePlayerId);
    if (! $player || $player->room_id !== $room->id) {
        return false;
    }

    return [
        'id'   => $player->id,
        'info' => ['display_name' => $player->displayName()],
    ];
});
