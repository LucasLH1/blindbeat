<?php

namespace App\Events;

use App\Models\GamePlayer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerJoined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly GamePlayer $gamePlayer,
        public readonly string $roomCode,
    ) {}

    public function broadcastAs(): string
    {
        return 'PlayerJoined';
    }

    public function broadcastOn(): array
    {
        return [new PresenceChannel('room.' . $this->roomCode)];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->gamePlayer->id,
            'display_name' => $this->gamePlayer->displayName(),
            'score' => $this->gamePlayer->score,
        ];
    }
}
