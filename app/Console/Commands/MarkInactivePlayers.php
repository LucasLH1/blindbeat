<?php

namespace App\Console\Commands;

use App\Enums\GamePlayerStatus;
use App\Models\GamePlayer;
use Illuminate\Console\Command;

class MarkInactivePlayers extends Command
{
    protected $signature = 'players:mark-inactive';
    protected $description = 'Mark active players whose heartbeat expired (>90s) as disconnected';

    public function handle(): int
    {
        $count = GamePlayer::where('status', GamePlayerStatus::Active)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', now()->subSeconds(90))
            ->update(['status' => GamePlayerStatus::Disconnected]);

        $this->info("Marked {$count} player(s) as disconnected.");

        return Command::SUCCESS;
    }
}
