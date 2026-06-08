<?php

namespace App\Jobs;

use App\Actions\RecordGroupScoreAction;
use App\Actions\StartRoundAction;
use App\Enums\RoomStatus;
use App\Enums\RoundStatus;
use App\Events\GameEnded;
use App\Models\Round;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRoundEnd implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Round $round,
    ) {}

    public function handle(): void
    {
        $round = $this->round->fresh();

        logger()->info('ProcessRoundEnd: handling', ['round_id' => $round->id, 'status' => $round->status->value]);

        // Idempotency: only proceed if round is still revealed
        if ($round->status !== RoundStatus::Revealed) {
            return;
        }

        $round->update(['status' => RoundStatus::Finished]);

        $room = $round->room;

        $nextRound = $room->rounds()
            ->where('round_number', $round->round_number + 1)
            ->first();

        if ($nextRound) {
            (new StartRoundAction)->execute($nextRound);
        } else {
            $room->update(['status' => RoomStatus::Finished]);
            (new RecordGroupScoreAction)->execute($room);
            GameEnded::dispatch($room);
        }
    }
}
