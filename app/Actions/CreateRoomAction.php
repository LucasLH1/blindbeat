<?php

namespace App\Actions;

use App\Enums\GamePlayerStatus;
use App\Enums\RoomStatus;
use App\Models\GamePlayer;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Str;

class CreateRoomAction
{
    public function execute(?User $user, array $themeIds, array $params): GamePlayer
    {
        $room = Room::create([
            'code' => $this->generateUniqueCode(),
            'status' => RoomStatus::Waiting,
            'max_players' => $params['max_players'] ?? 8,
            'round_duration' => $params['round_duration'] ?? 30,
            'total_rounds' => $params['total_rounds'] ?? 10,
            'max_attempts' => $params['max_attempts'] ?? null,
            'top_only' => $params['top_only'] ?? false,
        ]);

        if ($themeIds) {
            $room->themes()->attach($themeIds);
        }

        $hostPlayer = GamePlayer::create([
            'room_id' => $room->id,
            'user_id' => $user?->id,
            'guest_name' => $user === null ? ($params['guest_name'] ?? null) : null,
            'status' => GamePlayerStatus::Active,
            'score' => 0,
            'joined_at' => now(),
        ]);

        $hostPlayer->setRelation('room', $room);

        return $hostPlayer;
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Room::where('code', $code)->exists());

        return $code;
    }
}
