<?php

namespace App\Events;

use App\Models\Round;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoundEnded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Round $round,
    ) {}

    public function broadcastAs(): string
    {
        return 'RoundEnded';
    }

    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->round->room_id)];
    }

    public function broadcastWith(): array
    {
        $track = $this->round->playlistTrack;

        $scores = $this->round->answers->map(fn ($answer) => [
            'game_player_id' => $answer->game_player_id,
            'is_correct' => $answer->is_correct,
            'response_time_ms' => $answer->response_time_ms,
            'points_earned' => $answer->is_correct
                ? $answer->gamePlayer->score  // cumulative, displayed in scores panel
                : 0,
        ])->all();

        return [
            'id' => $this->round->id,
            'round_number' => $this->round->round_number,
            'correct_answer' => [
                'title' => $track->title,
                'artist' => $track->artist,
                'deezer_track_id' => $track->deezer_track_id,
                'cover_url' => $track->cover_url,
            ],
            'answers' => $scores,
        ];
    }
}
