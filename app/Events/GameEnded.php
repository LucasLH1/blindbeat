<?php

namespace App\Events;

use App\Enums\GamePlayerStatus;
use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameEnded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Room $room,
    ) {}

    public function broadcastAs(): string
    {
        return 'GameEnded';
    }

    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->room->id)];
    }

    public function broadcastWith(): array
    {
        $leaderboard = $this->room->gamePlayers()
            ->where('status', GamePlayerStatus::Active)
            ->orderByDesc('score')
            ->get()
            ->map(fn ($player) => [
                'id' => $player->id,
                'display_name' => $player->displayName(),
                'score' => $player->score,
            ])->all();

        return [
            'room_id' => $this->room->id,
            'leaderboard' => $leaderboard,
        ];
    }
}
