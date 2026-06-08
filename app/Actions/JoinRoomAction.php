<?php

namespace App\Actions;

use App\Enums\GamePlayerStatus;
use App\Enums\RoomStatus;
use App\Events\PlayerJoined;
use App\Models\GamePlayer;
use App\Models\Room;
use App\Models\User;

class JoinRoomAction
{
    public function execute(Room $room, ?User $user, ?string $guestName): GamePlayer
    {
        if ($room->status === RoomStatus::Finished) {
            throw new \DomainException('Room not available');
        }

        $activePlayers = $room->gamePlayers()
            ->where('status', GamePlayerStatus::Active)
            ->count();

        if ($activePlayers >= $room->max_players) {
            throw new \DomainException('Room full');
        }

        $gamePlayer = GamePlayer::create([
            'room_id' => $room->id,
            'user_id' => $user?->id,
            'guest_name' => $guestName,
            'status' => GamePlayerStatus::Active,
            'score' => 0,
            'joined_at' => now(),
        ]);

        PlayerJoined::dispatch($gamePlayer, $room->code);

        return $gamePlayer;
    }
}
