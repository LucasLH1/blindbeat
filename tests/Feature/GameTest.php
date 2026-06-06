<?php

use App\Actions\EndRoundAction;
use App\Actions\EvaluateAnswerAction;
use App\Actions\StartRoundAction;
use App\Enums\GamePlayerStatus;
use App\Enums\RoomStatus;
use App\Enums\RoundStatus;
use App\Events\GameEnded;
use App\Events\RoundEnded;
use App\Events\RoundStarted;
use App\Jobs\ProcessRoundEnd;
use App\Livewire\GameStage;
use App\Models\GamePlayer;
use App\Models\Playlist;
use App\Models\PlaylistTrack;
use App\Models\Room;
use App\Models\Round;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function gameRoom(int $maxPlayers = 4, int $roundDuration = 30): array
{
    $user = User::factory()->create();
    $playlist = Playlist::create(['user_id' => $user->id, 'name' => 'Test', 'is_public' => true]);
    $track = PlaylistTrack::create([
        'playlist_id' => $playlist->id,
        'deezer_track_id' => 1234567,
        'title' => 'Smells Like Teen Spirit',
        'artist' => 'Nirvana',
        'preview_url' => 'https://example.com/preview.mp3',
        'position' => 0,
    ]);
    $room = Room::create([
        'playlist_id' => $playlist->id,
        'code' => 'ABCDEF',
        'status' => RoomStatus::Waiting,
        'max_players' => $maxPlayers,
        'round_duration' => $roundDuration,
        'total_rounds' => 10,
    ]);

    return compact('user', 'playlist', 'track', 'room');
}

function addPlayer(Room $room, ?User $user = null, ?string $guestName = null): GamePlayer
{
    return GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => $user?->id,
        'guest_name' => $guestName,
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now(),
    ]);
}

function playingRound(Room $room, PlaylistTrack $track, int $roundNumber = 1): Round
{
    return Round::create([
        'room_id' => $room->id,
        'playlist_track_id' => $track->id,
        'round_number' => $roundNumber,
        'status' => RoundStatus::Playing,
        'started_at' => now(),
    ]);
}

function waitingRound(Room $room, PlaylistTrack $track, int $roundNumber = 1): Round
{
    return Round::create([
        'room_id' => $room->id,
        'playlist_track_id' => $track->id,
        'round_number' => $roundNumber,
        'status' => RoundStatus::Waiting,
    ]);
}

// ---------------------------------------------------------------------------
// StartRoundAction
// ---------------------------------------------------------------------------

test('StartRoundAction passe le round en playing et dispatche RoundStarted', function () {
    Queue::fake();
    Event::fake([RoundStarted::class]);
    Http::fake(['api.deezer.com/*' => Http::response(['preview' => 'https://cdn.deezer.com/fake.mp3', 'album' => ['cover_medium' => 'https://cdn.deezer.com/fake.jpg']])]);

    ['room' => $room, 'track' => $track] = gameRoom();
    $round = waitingRound($room, $track);

    (new StartRoundAction)->execute($round);

    $fresh = $round->fresh();
    expect($fresh->status)->toBe(RoundStatus::Playing)
        ->and($fresh->started_at)->not->toBeNull();

    expect($room->fresh()->current_round_number)->toBe(1);

    Event::assertDispatched(RoundStarted::class);
});

// ---------------------------------------------------------------------------
// EvaluateAnswerAction
// ---------------------------------------------------------------------------

test('réponse correcte (titre) rapporte >= 1000 points', function () {
    ['room' => $room, 'track' => $track] = gameRoom();
    $player = addPlayer($room);
    $round = playingRound($room, $track);

    $result = (new EvaluateAnswerAction)->execute($player, $round, 'Smells Like Teen Spirit');

    expect($result['correct'])->toBeTrue()
        ->and($result['points_earned'])->toBeGreaterThanOrEqual(1000);

    expect($player->fresh()->score)->toBe($result['points_earned']);
});

test('réponse correcte (artiste) est acceptée', function () {
    ['room' => $room, 'track' => $track] = gameRoom();
    $player = addPlayer($room);
    $round = playingRound($room, $track);

    $result = (new EvaluateAnswerAction)->execute($player, $round, 'nirvana');

    expect($result['correct'])->toBeTrue();
});

test('réponse incorrecte rapporte 0 points', function () {
    ['room' => $room, 'track' => $track] = gameRoom();
    $player = addPlayer($room);
    $round = playingRound($room, $track);

    $result = (new EvaluateAnswerAction)->execute($player, $round, 'Wrong Answer');

    expect($result['correct'])->toBeFalse()
        ->and($result['points_earned'])->toBe(0)
        ->and($player->fresh()->score)->toBe(0);
});

test('bonus vitesse diminue avec le temps de réponse', function () {
    ['room' => $room, 'track' => $track] = gameRoom(roundDuration: 30);
    $player1 = addPlayer($room, guestName: 'Fast');
    $player2 = addPlayer($room, guestName: 'Slow');

    // Fast player answers immediately
    $round = Round::create([
        'room_id' => $room->id,
        'playlist_track_id' => $track->id,
        'round_number' => 1,
        'status' => RoundStatus::Playing,
        'started_at' => now()->subMilliseconds(100),
    ]);

    $fastResult = (new EvaluateAnswerAction)->execute($player1, $round, 'Smells Like Teen Spirit');

    // Simulate late answer by adjusting started_at (mock late response)
    $round->update(['started_at' => now()->subSeconds(25)]);
    $round->refresh();

    // Manually create a 2nd player answer without triggering EndRound
    // (use separate round to isolate)
    $round2 = Round::create([
        'room_id' => $room->id,
        'playlist_track_id' => $track->id,
        'round_number' => 2,
        'status' => RoundStatus::Playing,
        'started_at' => now()->subSeconds(25),
    ]);
    $slowResult = (new EvaluateAnswerAction)->execute($player2, $round2, 'Smells Like Teen Spirit');

    expect($fastResult['points_earned'])->toBeGreaterThan($slowResult['points_earned']);
});

test('réponse normalisée ignore les accents', function () {
    $user = User::factory()->create();
    $playlist = Playlist::create(['user_id' => $user->id, 'name' => 'Test', 'is_public' => true]);
    $track = PlaylistTrack::create([
        'playlist_id' => $playlist->id,
        'deezer_track_id' => 999,
        'title' => 'Déjà Vu',
        'artist' => 'Beyoncé',
        'preview_url' => 'https://example.com/preview.mp3',
        'position' => 0,
    ]);
    $room = Room::create([
        'playlist_id' => $playlist->id,
        'code' => 'XYZABC',
        'status' => RoomStatus::Waiting,
        'max_players' => 4,
        'round_duration' => 30,
        'total_rounds' => 10,
    ]);
    $player = addPlayer($room);
    $round = playingRound($room, $track);

    $result = (new EvaluateAnswerAction)->execute($player, $round, 'deja vu');
    expect($result['correct'])->toBeTrue();
});

test('double réponse correcte lève DomainException', function () {
    ['room' => $room, 'track' => $track] = gameRoom(maxPlayers: 4);
    $player = addPlayer($room);
    addPlayer($room, guestName: 'Other'); // prevent EndRound
    $round = playingRound($room, $track);

    (new EvaluateAnswerAction)->execute($player, $round, 'Smells Like Teen Spirit');

    expect(fn () => (new EvaluateAnswerAction)->execute($player, $round, 'Another Answer'))
        ->toThrow(\DomainException::class, 'Already answered correctly');
});

test('réponse sur un round non-playing lève DomainException', function () {
    ['room' => $room, 'track' => $track] = gameRoom();
    $player = addPlayer($room);
    $round = waitingRound($room, $track);

    expect(fn () => (new EvaluateAnswerAction)->execute($player, $round, 'Answer'))
        ->toThrow(\DomainException::class, 'Round is not playing');
});

test('dernier joueur qui répond déclenche EndRoundAction (ProcessRoundEnd dispatché)', function () {
    Queue::fake();
    Event::fake([RoundEnded::class]);

    ['room' => $room, 'track' => $track] = gameRoom(maxPlayers: 4);
    $player = addPlayer($room);
    $round = playingRound($room, $track);

    (new EvaluateAnswerAction)->execute($player, $round, 'Smells Like Teen Spirit');

    Queue::assertPushed(ProcessRoundEnd::class);
    Event::assertDispatched(RoundEnded::class);
});

// ---------------------------------------------------------------------------
// EndRoundAction
// ---------------------------------------------------------------------------

test('EndRoundAction passe le round en revealed et dispatche RoundEnded', function () {
    Queue::fake();
    Event::fake([RoundEnded::class]);

    ['room' => $room, 'track' => $track] = gameRoom();
    $round = playingRound($room, $track);

    (new EndRoundAction)->execute($round);

    $fresh = $round->fresh();
    expect($fresh->status)->toBe(RoundStatus::Revealed)
        ->and($fresh->ended_at)->not->toBeNull();

    Event::assertDispatched(RoundEnded::class);
    Queue::assertPushed(ProcessRoundEnd::class);
});

test('EndRoundAction est idempotente si round déjà revealed', function () {
    Queue::fake();
    Event::fake([RoundEnded::class]);

    ['room' => $room, 'track' => $track] = gameRoom();
    $round = Round::create([
        'room_id' => $room->id,
        'playlist_track_id' => $track->id,
        'round_number' => 1,
        'status' => RoundStatus::Revealed,
        'started_at' => now(),
    ]);

    (new EndRoundAction)->execute($round); // Should be a no-op

    Event::assertNotDispatched(RoundEnded::class);
    Queue::assertNothingPushed();
});

// ---------------------------------------------------------------------------
// ProcessRoundEnd job
// ---------------------------------------------------------------------------

test('ProcessRoundEnd démarre la manche suivante', function () {
    Queue::fake();
    Event::fake([RoundStarted::class]);
    Http::fake(['api.deezer.com/*' => Http::response(['preview' => 'https://cdn.deezer.com/fake.mp3', 'album' => ['cover_medium' => 'https://cdn.deezer.com/fake.jpg']])]);

    ['room' => $room, 'track' => $track] = gameRoom();
    $round1 = Round::create([
        'room_id' => $room->id,
        'playlist_track_id' => $track->id,
        'round_number' => 1,
        'status' => RoundStatus::Revealed,
        'started_at' => now(),
        'ended_at' => now(),
    ]);
    $round2 = waitingRound($room, $track, roundNumber: 2);

    (new ProcessRoundEnd($round1))->handle();

    expect($round1->fresh()->status)->toBe(RoundStatus::Finished);
    expect($round2->fresh()->status)->toBe(RoundStatus::Playing);
    Event::assertDispatched(RoundStarted::class);
});

test('ProcessRoundEnd termine la partie si pas de round suivant', function () {
    Event::fake([GameEnded::class]);

    ['room' => $room, 'track' => $track] = gameRoom();
    $round = Round::create([
        'room_id' => $room->id,
        'playlist_track_id' => $track->id,
        'round_number' => 1,
        'status' => RoundStatus::Revealed,
        'started_at' => now(),
        'ended_at' => now(),
    ]);

    (new ProcessRoundEnd($round))->handle();

    expect($round->fresh()->status)->toBe(RoundStatus::Finished);
    expect($room->fresh()->status)->toBe(RoomStatus::Finished);
    Event::assertDispatched(GameEnded::class);
});

test('ProcessRoundEnd est idempotente si round déjà finished', function () {
    Event::fake([GameEnded::class, RoundStarted::class]);

    ['room' => $room, 'track' => $track] = gameRoom();
    $round = Round::create([
        'room_id' => $room->id,
        'playlist_track_id' => $track->id,
        'round_number' => 1,
        'status' => RoundStatus::Finished,
        'started_at' => now(),
    ]);

    (new ProcessRoundEnd($round))->handle();

    Event::assertNotDispatched(GameEnded::class);
    Event::assertNotDispatched(RoundStarted::class);
});

// ---------------------------------------------------------------------------
// AnswerController
// ---------------------------------------------------------------------------

test('AnswerController stocke la réponse et retourne JSON', function () {
    ['room' => $room, 'track' => $track] = gameRoom();
    $player = addPlayer($room);
    $round = playingRound($room, $track);

    $response = $this->withSession(['game_player_id' => $player->id])
        ->postJson('/api/answers', [
            'answer_text' => 'Smells Like Teen Spirit',
            'room_code' => $room->code,
        ]);

    $response->assertOk()
        ->assertJsonStructure(['correct', 'points_earned', 'correct_answer' => ['title', 'artist']]);
});

test('AnswerController rejette si pas de session', function () {
    ['room' => $room] = gameRoom();

    $this->postJson('/api/answers', ['answer_text' => 'Test', 'room_code' => $room->code])
        ->assertStatus(401);
});

test('AnswerController rejette si round non en playing', function () {
    ['room' => $room, 'track' => $track] = gameRoom();
    $player = addPlayer($room);
    waitingRound($room, $track);

    $response = $this->withSession(['game_player_id' => $player->id])
        ->postJson('/api/answers', [
            'answer_text' => 'Test',
            'room_code' => $room->code,
        ]);

    $response->assertStatus(422);
});

test('AnswerController rejette la double réponse après une réponse correcte', function () {
    ['room' => $room, 'track' => $track] = gameRoom(maxPlayers: 4);
    $player = addPlayer($room);
    addPlayer($room, guestName: 'Other'); // prevent EndRound
    $round = playingRound($room, $track);

    $this->withSession(['game_player_id' => $player->id])
        ->postJson('/api/answers', ['answer_text' => 'Smells Like Teen Spirit', 'room_code' => $room->code]);

    $response = $this->withSession(['game_player_id' => $player->id])
        ->postJson('/api/answers', ['answer_text' => 'Second', 'room_code' => $room->code]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'Already answered correctly');
});

// ---------------------------------------------------------------------------
// GameStage Livewire
// ---------------------------------------------------------------------------

test('GameStage redirige vers /join si pas de session', function () {
    ['room' => $room] = gameRoom();
    $room->update(['status' => RoomStatus::Playing]);

    Livewire::test(GameStage::class, ['code' => $room->code])
        ->assertRedirect(route('rooms.join'));
});

test('GameStage se monte en état waiting', function () {
    ['room' => $room] = gameRoom();
    $room->update(['status' => RoomStatus::Playing]);
    $player = addPlayer($room);

    session(['game_player_id' => $player->id]);

    Livewire::test(GameStage::class, ['code' => $room->code])
        ->assertSet('state', 'waiting')
        ->assertSet('gamePlayerId', $player->id);
});

test('GameStage se monte en état playing si un round est en cours', function () {
    Http::fake(['api.deezer.com/*' => Http::response(['preview' => 'https://cdn.deezer.com/fake.mp3', 'album' => ['cover_medium' => 'https://cdn.deezer.com/fake.jpg']])]);

    ['room' => $room, 'track' => $track] = gameRoom();
    $room->update(['status' => RoomStatus::Playing]);
    $player = addPlayer($room);
    playingRound($room, $track);

    session(['game_player_id' => $player->id]);

    Livewire::test(GameStage::class, ['code' => $room->code])
        ->assertSet('state', 'playing');
});

test('GameStage se monte en état finished si room terminée', function () {
    ['room' => $room] = gameRoom();
    $room->update(['status' => RoomStatus::Finished]);
    $player = addPlayer($room);

    session(['game_player_id' => $player->id]);

    Livewire::test(GameStage::class, ['code' => $room->code])
        ->assertSet('state', 'finished');
});
