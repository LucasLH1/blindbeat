<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomReplayed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $roomId,
        public readonly string $newRoomCode,
    ) {}

    public function broadcastAs(): string
    {
        return 'RoomReplayed';
    }

    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->roomId)];
    }

    public function broadcastWith(): array
    {
        return ['new_room_code' => $this->newRoomCode];
    }
}
