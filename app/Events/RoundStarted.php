<?php

namespace App\Events;

use App\Models\Round;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoundStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Round $round,
        public readonly ?string $previewUrl = null,
        public readonly ?string $coverUrl = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'RoundStarted';
    }

    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->round->room_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->round->id,
            'round_number' => $this->round->round_number,
            'deezer_track_id' => $this->round->playlistTrack->deezer_track_id,
            'preview_url' => $this->previewUrl,
            'cover_url' => $this->coverUrl,
            'duration' => $this->round->room->round_duration,
        ];
    }
}
