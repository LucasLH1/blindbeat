<?php

namespace App\Actions;

use App\Enums\RoundStatus;
use App\Events\RoundStarted;
use App\Jobs\ScheduleRoundEnd;
use App\Models\Round;
use Illuminate\Support\Facades\Http;

class StartRoundAction
{
    public function execute(Round $round): void
    {
        $round->update([
            'status' => RoundStatus::Playing,
            'started_at' => now(),
        ]);

        $round->room->update(['current_round_number' => $round->round_number]);

        $round->load('playlistTrack', 'room');

        [$previewUrl, $coverUrl] = $this->fetchDeezerUrls($round->playlistTrack->deezer_track_id);

        RoundStarted::dispatch($round, $previewUrl, $coverUrl);

        ScheduleRoundEnd::dispatch($round)->delay(now()->addSeconds($round->room->round_duration));
    }

    public static function fetchDeezerUrls(int $trackId): array
    {
        try {
            $response = Http::timeout(5)->get("https://api.deezer.com/track/{$trackId}");
            if ($response->successful()) {
                $data = $response->json();
                return [
                    $data['preview'] ?? null,
                    $data['album']['cover_medium'] ?? null,
                ];
            }
        } catch (\Throwable) {}

        return [null, null];
    }
}
