<?php

namespace App\Events;

use App\Models\GamePlayer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerAnsweredCorrectly implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly GamePlayer $gamePlayer,
        public readonly string $roomId,
        public readonly int $pointsEarned,
        public readonly int $responseTimeMs,
        public readonly string $answerType,
        public readonly bool $foundTitle,
        public readonly bool $foundArtist,
    ) {}

    public function broadcastAs(): string
    {
        return 'PlayerAnsweredCorrectly';
    }

    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->roomId)];
    }

    public function broadcastWith(): array
    {
        return [
            'game_player_id'   => $this->gamePlayer->id,
            'display_name'     => $this->gamePlayer->displayName(),
            'points_earned'    => $this->pointsEarned,
            'response_time_ms' => $this->responseTimeMs,
            'answer_type'      => $this->answerType,
            'found_title'      => $this->foundTitle,
            'found_artist'     => $this->foundArtist,
        ];
    }
}
