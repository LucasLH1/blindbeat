<?php

namespace App\Livewire;

use App\Actions\StartGameAction;
use App\Enums\GamePlayerStatus;
use App\Enums\RoomStatus;
use App\Models\GamePlayer;
use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Lobby extends Component
{
    public string $code;
    public string $roomId = '';
    public Room $room;
    public Collection $players;
    public bool $isHost = false;

    #[Locked]
    public string $gamePlayerId = '';

    public function mount(string $code): void
    {
        $this->players = collect();
        $this->code = $code;
        $this->room = Room::with('playlist')->where('code', $code)->firstOrFail();

        if ($this->room->status === RoomStatus::Playing) {
            $this->redirect(route('rooms.play', $this->code));
            return;
        }

        $gamePlayerId = session('game_player_id');

        if (! $gamePlayerId || ! GamePlayer::find($gamePlayerId)) {
            $this->redirect(route('rooms.join', ['code' => $this->code]));
            return;
        }

        $this->roomId = $this->room->id;
        $this->gamePlayerId = $gamePlayerId;
        $this->refreshPlayers();
    }

    #[On('echo-presence:room.{code},PlayerJoined')]
    public function refreshPlayers(): void
    {
        if (! $this->gamePlayerId) {
            return;
        }

        $this->players = $this->room->gamePlayers()
            ->where('status', GamePlayerStatus::Active)
            ->orderBy('joined_at')
            ->get();

        $this->isHost = $this->players->first()?->id === $this->gamePlayerId;
    }

    public function startGame(): void
    {
        if (! $this->isHost) {
            return;
        }

        try {
            app(StartGameAction::class)->execute($this->room->fresh(['playlist.tracks']));
        } catch (\DomainException) {
            return;
        }

        $this->redirect(route('rooms.play', $this->code));
    }

    #[On('echo:game.{roomId},GameStarted')]
    public function onGameStarted(): void
    {
        if (! $this->gamePlayerId) {
            return;
        }

        $this->redirect(route('rooms.play', $this->code));
    }

    public function playerLeft(?string $playerId): void
    {
        if (! $playerId || ! $this->gamePlayerId) {
            return;
        }

        GamePlayer::where('id', $playerId)
            ->where('room_id', $this->room->id)
            ->update(['status' => GamePlayerStatus::Disconnected]);

        $this->refreshPlayers();
    }

    public function render(): View
    {
        return view('livewire.lobby');
    }
}
