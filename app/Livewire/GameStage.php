<?php

namespace App\Livewire;

use App\Actions\CreateRoomAction;
use App\Actions\EndRoundAction;
use App\Actions\StartRoundAction;
use App\Enums\AnswerType;
use App\Enums\GamePlayerStatus;
use App\Enums\RoundStatus;
use App\Enums\RoomStatus;
use App\Events\RoomReplayed;
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

    // Playing state — per-player title/artist progress
    public array $playerProgress = [];

    // Playing state — live wrong answers feed (last 10)
    public array $wrongAnswers = [];

    // Playing state — dernière mauvaise réponse par joueur [player_id => answer_text]
    public array $lastWrongAnswer = [];

    // Revealed state
    public ?array $correctAnswer = null;
    public array $roundAnswers = [];

    // Revealed/playing state — round ranking (players who found both, in order)
    public array $roundRanking = [];

    // Finished state
    public array $leaderboard = [];

    public function mount(string $code): void
    {
        $this->players = collect();
        $this->code = $code;
        $this->room = Room::where('code', $code)->firstOrFail();

        $gamePlayerId = session('game_player_id');

        if (! $gamePlayerId || ! GamePlayer::find($gamePlayerId)) {
            $this->redirect(route('rooms.join'));
            return;
        }

        $this->roomId = $this->room->id;
        $this->gamePlayerId = $gamePlayerId;

        // Re-activate the player in case they were marked disconnected during
        // the Lobby→GameStage page navigation (presence channel briefly leaves).
        GamePlayer::where('id', $gamePlayerId)
            ->update(['status' => GamePlayerStatus::Active, 'last_seen_at' => now()]);

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
        $this->wrongAnswers = [];
        $this->roundRanking = [];
        $this->lastWrongAnswer = [];
        $this->refreshPlayers();
        $this->initPlayerProgress();
    }

    #[On('echo:game.{roomId},PlayerAnsweredCorrectly')]
    public function onPlayerAnsweredCorrectly(array $data): void
    {
        $this->refreshPlayers();

        $playerId    = $data['game_player_id'] ?? null;
        $foundTitle  = $data['found_title']     ?? false;
        $foundArtist = $data['found_artist']    ?? false;

        if ($playerId) {
            if (! isset($this->playerProgress[$playerId])) {
                $this->playerProgress[$playerId] = ['found_title' => false, 'found_artist' => false];
            }
            if ($foundTitle)  $this->playerProgress[$playerId]['found_title']  = true;
            if ($foundArtist) $this->playerProgress[$playerId]['found_artist'] = true;

            if ($foundTitle && $foundArtist && ! in_array($playerId, $this->roundRanking)) {
                $this->roundRanking[] = $playerId;
            }

            // Une bonne réponse efface la dernière mauvaise réponse affichée
            unset($this->lastWrongAnswer[$playerId]);
        }

        $this->dispatch('player-scored', playerId: $data['game_player_id'], points: $data['points_earned']);
    }

    #[On('echo:game.{roomId},PlayerAnsweredWrong')]
    public function onPlayerAnsweredWrong(array $data): void
    {
        $playerId = $data['game_player_id'] ?? null;
        $text     = $data['answer_text']    ?? '';

        array_unshift($this->wrongAnswers, [
            'display_name' => $data['display_name'] ?? '',
            'answer_text'  => $text,
        ]);
        $this->wrongAnswers = array_slice($this->wrongAnswers, 0, 10);

        if ($playerId) {
            $this->lastWrongAnswer[$playerId] = $text;
        }
    }

    #[On('echo:game.{roomId},RoundEnded')]
    public function onRoundEnded(array $data): void
    {
        $this->state = 'revealed';
        $this->correctAnswer = $data['correct_answer'] ?? null;
        $this->roundAnswers  = $data['results'] ?? $data['answers'] ?? [];
        $this->refreshPlayers();
    }

    #[On('echo:game.{roomId},GameEnded')]
    public function onGameEnded(array $data): void
    {
        $this->state = 'finished';
        $this->leaderboard = $data['leaderboard'];
    }

    #[On('echo:game.{roomId},RoomReplayed')]
    public function onRoomReplayed(array $data): void
    {
        $this->redirect(route('rooms.join') . '?code=' . $data['new_room_code']);
    }

    public function replayGame(): void
    {
        $this->room->refresh();

        $currentPlayer = GamePlayer::find($this->gamePlayerId);

        $themeIds = $this->room->themes()->pluck('id')->toArray();

        $params = [
            'max_players'    => $this->room->max_players,
            'round_duration' => $this->room->round_duration,
            'total_rounds'   => $this->room->total_rounds,
            'max_attempts'   => $this->room->max_attempts,
            'top_only'       => $this->room->top_only,
            'guest_name'     => $currentPlayer?->guest_name,
        ];

        $hostPlayer = app(CreateRoomAction::class)->execute(
            auth()->user(),
            $themeIds,
            $params,
        );

        session(['game_player_id' => $hostPlayer->id]);

        RoomReplayed::dispatch($this->room->id, $hostPlayer->room->code);

        $this->redirect(route('rooms.lobby', $hostPlayer->room->code));
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
            ->with('themeTrack')
            ->where('status', RoundStatus::Playing)
            ->first();

        if ($playingRound) {
            $this->state = 'playing';
            [$previewUrl, $coverUrl] = StartRoundAction::fetchDeezerUrls(
                $playingRound->themeTrack->deezer_track_id
            );
            $this->currentRound = [
                'id' => $playingRound->id,
                'round_number' => $playingRound->round_number,
                'deezer_track_id' => $playingRound->themeTrack->deezer_track_id,
                'preview_url' => $previewUrl,
                'cover_url' => $coverUrl,
                'duration' => $this->room->round_duration,
                'started_at' => $playingRound->started_at?->toIso8601String(),
                'started_at_ms' => $playingRound->started_at ? (int) ($playingRound->started_at->timestamp * 1000) : null,
            ];
            $this->duration = $this->room->round_duration;
            $this->initPlayerProgress($playingRound);
        }

        $revealedRound = $this->room->rounds()
            ->with(['themeTrack', 'answers'])
            ->where('status', RoundStatus::Revealed)
            ->first();

        if ($revealedRound) {
            $this->state = 'revealed';
            $this->correctAnswer = [
                'title' => $revealedRound->themeTrack->title,
                'artist' => $revealedRound->themeTrack->artist,
                'deezer_track_id' => $revealedRound->themeTrack->deezer_track_id,
                'cover_url' => $revealedRound->themeTrack->cover_url,
            ];
            $this->roundAnswers = $this->buildRoundResults($revealedRound);
            $this->roundRanking = collect($this->roundAnswers)
                ->filter(fn ($r) => ($r['found_title'] ?? false) && ($r['found_artist'] ?? false))
                ->pluck('game_player_id')->all();
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
        if ($round && $round->shouldEnd()) {
            (new EndRoundAction)->execute($round);
        }
    }

    private function initPlayerProgress(?Round $round = null): void
    {
        $this->playerProgress = [];
        foreach ($this->players as $player) {
            $this->playerProgress[$player->id] = ['found_title' => false, 'found_artist' => false];
        }

        if ($round) {
            $round->loadMissing('answers');
            foreach ($round->answers as $answer) {
                if (! $answer->is_correct) {
                    continue;
                }
                $id = $answer->game_player_id;
                if (! isset($this->playerProgress[$id])) {
                    $this->playerProgress[$id] = ['found_title' => false, 'found_artist' => false];
                }
                if ($answer->answer_type === AnswerType::Title) {
                    $this->playerProgress[$id]['found_title'] = true;
                } elseif ($answer->answer_type === AnswerType::Artist) {
                    $this->playerProgress[$id]['found_artist'] = true;
                }
            }
        }
    }

    private function buildRoundResults(Round $round): array
    {
        $round->loadMissing('answers');

        return $this->players->map(function ($player) use ($round) {
            $answers = $round->answers->where('game_player_id', $player->id);

            return [
                'game_player_id'    => $player->id,
                'display_name'      => $player->displayName(),
                'found_title'       => $answers->contains(fn ($a) => $a->answer_type === AnswerType::Title && $a->is_correct),
                'found_artist'      => $answers->contains(fn ($a) => $a->answer_type === AnswerType::Artist && $a->is_correct),
                'points_this_round' => $answers->where('is_correct', true)->sum('points_earned'),
                'total_score'       => $player->score,
            ];
        })->sortByDesc('points_this_round')->values()->all();
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
