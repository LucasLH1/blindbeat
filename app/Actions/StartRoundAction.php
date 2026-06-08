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
        $round->load('themeTrack', 'room');

        $round->update([
            'status' => RoundStatus::Playing,
            'started_at' => now(),
            'track_title' => $round->themeTrack->title,
            'track_artist' => $round->themeTrack->artist,
        ]);

        $round->room->update(['current_round_number' => $round->round_number]);

        [$previewUrl, $coverUrl] = $this->fetchDeezerUrls($round->themeTrack->deezer_track_id);

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
