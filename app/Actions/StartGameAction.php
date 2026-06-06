<?php

namespace App\Actions;

use App\Enums\GamePlayerStatus;
use App\Enums\RoomStatus;
use App\Enums\RoundStatus;
use App\Events\GameStarted;
use App\Models\Room;
use App\Models\Round;

class StartGameAction
{
    public function __construct(private StartRoundAction $startRound) {}

    public function execute(Room $room): void
    {
        if ($room->status !== RoomStatus::Waiting) {
            throw new \DomainException('Room is not waiting');
        }

        $activePlayers = $room->gamePlayers()
            ->where('status', GamePlayerStatus::Active)
            ->count();

        if ($activePlayers < 2) {
            throw new \DomainException('Not enough players');
        }

        $tracks = $room->playlist->tracks()
            ->inRandomOrder()
            ->take($room->total_rounds)
            ->get();

        foreach ($tracks as $index => $track) {
            Round::create([
                'room_id' => $room->id,
                'playlist_track_id' => $track->id,
                'round_number' => $index + 1,
                'status' => RoundStatus::Waiting,
            ]);
        }

        $room->update([
            'status' => RoomStatus::Playing,
            'started_at' => now(),
            'current_round_number' => 1,
        ]);

        GameStarted::dispatch($room);

        $firstRound = $room->rounds()->orderBy('round_number')->first();
        $this->startRound->execute($firstRound);
    }
}
