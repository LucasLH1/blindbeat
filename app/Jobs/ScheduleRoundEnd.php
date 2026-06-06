<?php

namespace App\Jobs;

use App\Actions\EndRoundAction;
use App\Enums\RoundStatus;
use App\Models\Round;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScheduleRoundEnd implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Round $round) {}

    public function handle(): void
    {
        $round = $this->round->fresh();

        if ($round->status !== RoundStatus::Playing) {
            return;
        }

        (new EndRoundAction)->execute($round);
    }
}
