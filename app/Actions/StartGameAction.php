<?php

namespace App\Actions;

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

        $tracks = $room->themes()
            ->with('tracks')
            ->get()
            ->pluck('tracks')
            ->flatten()
            ->when($room->top_only, fn ($c) => $c->where('is_top', true))
            ->unique('deezer_track_id')
            ->shuffle()
            ->take($room->total_rounds);

        foreach ($tracks as $index => $track) {
            Round::create([
                'room_id' => $room->id,
                'theme_track_id' => $track->id,
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
