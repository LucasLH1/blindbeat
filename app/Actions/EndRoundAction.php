<?php

namespace App\Actions;

use App\Enums\RoundStatus;
use App\Events\RoundEnded;
use App\Jobs\ProcessRoundEnd;
use App\Models\Round;

class EndRoundAction
{
    public function execute(Round $round): void
    {
        // Idempotency guard — may be called concurrently when last player answers
        if ($round->status !== RoundStatus::Playing) {
            return;
        }

        $round->update([
            'status' => RoundStatus::Revealed,
            'ended_at' => now(),
        ]);

        $round->load('playlistTrack', 'answers.gamePlayer');

        RoundEnded::dispatch($round);

        ProcessRoundEnd::dispatch($round)->delay(now()->addSeconds(5));
    }
}
