<?php

namespace App\Livewire;

use App\Actions\EndRoundAction;
use App\Actions\StartRoundAction;
use App\Enums\GamePlayerStatus;
use App\Enums\RoundStatus;
use App\Enums\RoomStatus;
use App\Models\GamePlayer;
use App\Models\Room;
use App\Models\Round;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class GameStage extends Component
{
    public string $code;
    public string $roomId = '';
    public Room $room;
    public Collection $players;
    public string $state = 'waiting'; // waiting | playing | revealed | finished

    #[Locked]
    public string $gamePlayerId = '';

    // Playing state
    public ?array $currentRound = null;
    public int $duration = 30;

    // Revealed state
    public ?array $correctAnswer = null;
    public array $roundAnswers = [];

    // Finished state
    public array $leaderboard = [];

    public function mount(string $code): void
    {
        $this->players = collect();
        $this->code = $code;
        $this->room = Room::with('playlist')->where('code', $code)->firstOrFail();

        $gamePlayerId = session('game_player_id');

        if (! $gamePlayerId || ! GamePlayer::find($gamePlayerId)) {
            $this->redirect(route('rooms.join'));
            return;
        }

        $this->roomId = $this->room->id;
        $this->gamePlayerId = $gamePlayerId;
        $this->loadCurrentState();
    }

    #[On('echo:game.{roomId},RoundStarted')]
    public function onRoundStarted(array $data): void
    {
        $this->state = 'playing';
        $this->currentRound = $data;
        $this->duration = $data['duration'];
        $this->correctAnswer = null;
        $this->roundAnswers = [];
        $this->refreshPlayers();
    }

    #[On('echo:game.{roomId},RoundEnded')]
    public function onRoundEnded(array $data): void
    {
        $this->state = 'revealed';
        $this->correctAnswer = $data['correct_answer'];
        $this->roundAnswers = $data['answers'];
        $this->refreshPlayers();
    }

    #[On('echo:game.{roomId},GameEnded')]
    public function onGameEnded(array $data): void
    {
        $this->state = 'finished';
        $this->leaderboard = $data['leaderboard'];
    }

    private function loadCurrentState(): void
    {
        $this->refreshPlayers();

        $this->room->refresh();

        if ($this->room->status === RoomStatus::Finished) {
            $this->state = 'finished';
            $this->leaderboard = $this->players
                ->sortByDesc('score')
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'display_name' => $p->displayName(),
                    'score' => $p->score,
                ])->values()->all();
            return;
        }

        $playingRound = $this->room->rounds()
            ->with('playlistTrack')
            ->where('status', RoundStatus::Playing)
            ->first();

        if ($playingRound) {
            $this->state = 'playing';
            [$previewUrl, $coverUrl] = StartRoundAction::fetchDeezerUrls(
                $playingRound->playlistTrack->deezer_track_id
            );
            $this->currentRound = [
                'id' => $playingRound->id,
                'round_number' => $playingRound->round_number,
                'deezer_track_id' => $playingRound->playlistTrack->deezer_track_id,
                'preview_url' => $previewUrl,
                'cover_url' => $coverUrl,
                'duration' => $this->room->round_duration,
                'started_at' => $playingRound->started_at?->toIso8601String(),
            ];
            $this->duration = $this->room->round_duration;
        }

        $revealedRound = $this->room->rounds()
            ->with(['playlistTrack', 'answers'])
            ->where('status', RoundStatus::Revealed)
            ->first();

        if ($revealedRound) {
            $this->state = 'revealed';
            $this->correctAnswer = [
                'title' => $revealedRound->playlistTrack->title,
                'artist' => $revealedRound->playlistTrack->artist,
                'deezer_track_id' => $revealedRound->playlistTrack->deezer_track_id,
                'cover_url' => $revealedRound->playlistTrack->cover_url,
            ];
        }
    }

    public function playerLeft(?string $playerId): void
    {
        if (! $playerId) {
            return;
        }

        GamePlayer::where('id', $playerId)
            ->where('room_id', $this->room->id)
            ->update(['status' => GamePlayerStatus::Disconnected]);

        $this->refreshPlayers();

        $round = $this->room->rounds()->where('status', RoundStatus::Playing)->first();
        if ($round) {
            $this->checkRoundEnd($round);
        }
    }

    private function checkRoundEnd(Round $round): void
    {
        $maxAttempts = $this->room->fresh()->max_attempts;

        $activePlayers = $this->room->gamePlayers()
            ->where('status', GamePlayerStatus::Active)
            ->pluck('id');

        if ($activePlayers->isEmpty()) {
            (new EndRoundAction)->execute($round);
            return;
        }

        $donePlayers = $activePlayers->filter(function ($playerId) use ($round, $maxAttempts) {
            $answers = $round->answers()->where('game_player_id', $playerId);
            return $answers->where('is_correct', true)->exists()
                || ($maxAttempts !== null && $answers->count() >= $maxAttempts);
        })->count();

        if ($donePlayers >= $activePlayers->count()) {
            (new EndRoundAction)->execute($round);
        }
    }

    private function refreshPlayers(): void
    {
        $this->players = $this->room->gamePlayers()
            ->where('status', GamePlayerStatus::Active)
            ->orderByDesc('score')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.game-stage');
    }
}
