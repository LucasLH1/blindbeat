<?php

namespace App\Events;

use App\Enums\AnswerType;
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
        $this->round->loadMissing('themeTrack', 'answers', 'room.gamePlayers');

        $track = $this->round->themeTrack;

        $results = $this->round->room->gamePlayers->map(function ($player) {
            $playerAnswers = $this->round->answers->where('game_player_id', $player->id);

            $foundTitle  = $playerAnswers->contains(fn ($a) => $a->answer_type === AnswerType::Title && $a->is_correct);
            $foundArtist = $playerAnswers->contains(fn ($a) => $a->answer_type === AnswerType::Artist && $a->is_correct);
            $pointsThisRound = $playerAnswers->where('is_correct', true)->sum('points_earned');

            return [
                'game_player_id'   => $player->id,
                'display_name'     => $player->displayName(),
                'found_title'      => $foundTitle,
                'found_artist'     => $foundArtist,
                'points_this_round' => $pointsThisRound,
                'total_score'      => $player->score,
            ];
        })->sortByDesc('points_this_round')->values()->all();

        return [
            'id'             => $this->round->id,
            'round_number'   => $this->round->round_number,
            'correct_answer' => [
                'title'           => $track->title,
                'artist'          => $track->artist,
                'deezer_track_id' => $track->deezer_track_id,
                'cover_url'       => $track->cover_url,
            ],
            'results' => $results,
            'answers' => $results, // backward compat
        ];
    }
}
