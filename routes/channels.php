<?php

use App\Models\GamePlayer;
use App\Models\GroupMember;
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

    // Status is managed by the app (playerLeft, mount re-activation) — not enforced here.

    return [
        'id'           => $player->id,
        'display_name' => $player->displayName(),
    ];
});

// Private channel per group — only members may subscribe.
Broadcast::channel('group.{groupId}', function ($user, string $groupId) {
    return GroupMember::where('group_id', $groupId)
        ->where('user_id', $user->id)
        ->exists();
});
