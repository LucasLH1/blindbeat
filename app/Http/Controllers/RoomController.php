<?php

namespace App\Http\Controllers;

use App\Actions\CreateRoomAction;
use App\Actions\JoinRoomAction;
use App\Actions\StartGameAction;
use App\Enums\GamePlayerStatus;
use App\Models\GamePlayer;
use App\Models\Playlist;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function create(): View
    {
        $playlists = Playlist::with('tracks')->where('is_public', true)->get();

        return view('rooms.create', compact('playlists'));
    }

    public function showJoin(): View
    {
        return view('rooms.join');
    }

    public function join(Request $request, JoinRoomAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'guest_name' => [Rule::requiredIf(! auth()->check()), 'nullable', 'string', 'max:20'],
        ]);

        $room = Room::where('code', strtoupper($validated['code']))->firstOrFail();

        $gamePlayer = $action->execute($room, auth()->user(), $validated['guest_name'] ?? null);

        session(['game_player_id' => $gamePlayer->id]);

        return redirect()->route('rooms.lobby', $room->code);
    }

    public function store(Request $request, CreateRoomAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'playlist_id' => ['required', 'exists:playlists,id'],
            'guest_name' => [Rule::requiredIf(! auth()->check()), 'nullable', 'string', 'max:20'],
            'max_players' => ['integer', 'min:2', 'max:16'],
            'round_duration' => ['integer', 'min:15', 'max:60'],
            'total_rounds' => ['integer', 'min:5', 'max:20'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $playlist = Playlist::findOrFail($validated['playlist_id']);

        $hostPlayer = $action->execute(auth()->user() ?: null, $playlist, $validated);

        session(['game_player_id' => $hostPlayer->id]);

        return redirect()->route('rooms.lobby', $hostPlayer->room->code);
    }

    public function lobby(string $code): View|RedirectResponse
    {
        $code = strtoupper($code);
        $gamePlayerId = session('game_player_id');
        $player = $gamePlayerId ? GamePlayer::with('room')->find($gamePlayerId) : null;

        if (! $player || ! $player->room || $player->room->code !== $code) {
            return redirect()->route('rooms.join', ['code' => $code]);
        }

        return view('rooms.lobby', compact('code'));
    }

    public function start(Request $request, string $code, StartGameAction $action): RedirectResponse
    {
        $room = Room::with(['playlist.tracks'])->where('code', $code)->firstOrFail();

        $hostPlayer = $room->gamePlayers()
            ->where('status', GamePlayerStatus::Active)
            ->orderBy('joined_at')
            ->first();

        abort_if($hostPlayer?->user_id !== auth()->id(), 403);

        try {
            $action->execute($room);
        } catch (\DomainException $e) {
            abort(422, $e->getMessage());
        }

        return redirect()->route('rooms.play', $code);
    }

    public function play(string $code): View
    {
        return view('rooms.play', compact('code'));
    }
}
