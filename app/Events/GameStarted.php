<?php

namespace App\Events;

use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Room $room,
    ) {}

    public function broadcastAs(): string
    {
        return 'GameStarted';
    }

    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->room->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->room->id,
            'total_rounds' => $this->room->total_rounds,
            'round_duration' => $this->room->round_duration,
        ];
    }
}
