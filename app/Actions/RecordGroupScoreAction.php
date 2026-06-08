<?php

namespace App\Actions;

use App\Models\GroupScore;
use App\Models\Room;

class RecordGroupScoreAction
{
    public function execute(Room $room): void
    {
        $group = $room->groups()->first();

        if (! $group) {
            return;
        }

        $memberUserIds = $group->groupMembers()->pluck('user_id');

        $players = $room->gamePlayers()
            ->whereNotNull('user_id')
            ->whereIn('user_id', $memberUserIds)
            ->get();

        $scoreMaxPossible = max(1, $room->total_rounds * 1500);

        foreach ($players as $gamePlayer) {
            $normalized = round(($gamePlayer->score / $scoreMaxPossible) * 1000, 2);

            $score = GroupScore::firstOrNew([
                'group_id' => $group->id,
                'user_id'  => $gamePlayer->user_id,
            ]);

            $score->total_normalized_points = ($score->total_normalized_points ?? 0) + $normalized;
            $score->games_played            = ($score->games_played ?? 0) + 1;
            $score->best_normalized_score   = max($score->best_normalized_score ?? 0, $normalized);
            $score->last_played_at          = now();
            $score->save();
        }
    }
}
