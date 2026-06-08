<?php

namespace App\Events;

use App\Models\GamePlayer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerAnsweredWrong implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly GamePlayer $gamePlayer,
        public readonly string $roomId,
        public readonly string $answerText,
    ) {}

    public function broadcastAs(): string
    {
        return 'PlayerAnsweredWrong';
    }

    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->roomId)];
    }

    public function broadcastWith(): array
    {
        return [
            'game_player_id' => $this->gamePlayer->id,
            'display_name'   => $this->gamePlayer->displayName(),
            'answer_text'    => $this->answerText,
        ];
    }
}
