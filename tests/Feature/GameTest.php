<?php

use App\Actions\EndRoundAction;
use App\Actions\EvaluateAnswerAction;
use App\Actions\StartGameAction;
use App\Actions\StartRoundAction;
use App\Enums\AnswerType;
use App\Enums\GamePlayerStatus;
use App\Enums\RoomStatus;
use App\Enums\RoundStatus;
use App\Events\GameEnded;
use App\Events\PlayerAnsweredCorrectly;
use App\Events\PlayerAnsweredWrong;
use App\Events\RoomReplayed;
use App\Events\RoundEnded;
use App\Events\RoundStarted;
use App\Jobs\ProcessRoundEnd;
use App\Livewire\GameStage;
use App\Models\GamePlayer;
use App\Models\Room;
use App\Models\Round;
use App\Models\Theme;
use App\Models\ThemeTrack;
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
    $theme = Theme::create(['name' => 'Test Theme', 'emoji' => '🎵']);
    $track = ThemeTrack::create([
        'theme_id' => $theme->id,
        'deezer_track_id' => 1234567,
        'title' => 'Smells Like Teen Spirit',
        'artist' => 'Nirvana',
        'preview_url' => 'https://example.com/preview.mp3',
        'position' => 0,
    ]);
    $room = Room::create([
        'code' => 'ABCDEF',
        'status' => RoomStatus::Waiting,
        'max_players' => $maxPlayers,
        'round_duration' => $roundDuration,
        'total_rounds' => 10,
    ]);
    $room->themes()->attach($theme->id);

    return compact('theme', 'track', 'room');
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

function playingRound(Room $room, ThemeTrack $track, int $roundNumber = 1): Round
{
    return Round::create([
        'room_id' => $room->id,
        'theme_track_id' => $track->id,
        'track_title' => $track->title,
        'track_artist' => $track->artist,
        'round_number' => $roundNumber,
        'status' => RoundStatus::Playing,
        'started_at' => now(),
    ]);
}

function waitingRound(Room $room, ThemeTrack $track, int $roundNumber = 1): Round
{
    return Round::create([
        'room_id' => $room->id,
        'theme_track_id' => $track->id,
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
        ->and($fresh->started_at)->not->toBeNull()
        ->and($fresh->track_title)->toBe('Smells Like Teen Spirit')
        ->and($fresh->track_artist)->toBe('Nirvana');

    expect($room->fresh()->current_round_number)->toBe(1);

    Event::assertDispatched(RoundStarted::class);
});

// ---------------------------------------------------------------------------
// StartGameAction
// ---------------------------------------------------------------------------

test('StartGameAction démarre avec 1 seul joueur actif', function () {
    Queue::fake();
    Event::fake();
    Http::fake(['api.deezer.com/*' => Http::response(['preview' => 'https://cdn.deezer.com/fake.mp3', 'album' => ['cover_medium' => 'https://cdn.deezer.com/fake.jpg']])]);

    ['room' => $room] = gameRoom();
    addPlayer($room);

    app(StartGameAction::class)->execute($room->fresh());

    expect($room->fresh()->status)->toBe(RoomStatus::Playing);
});

test('StartGameAction déduplique les tracks entre thèmes', function () {
    Queue::fake();
    Event::fake();
    Http::fake(['api.deezer.com/*' => Http::response(['preview' => 'https://cdn.deezer.com/fake.mp3', 'album' => ['cover_medium' => 'https://cdn.deezer.com/fake.jpg']])]);

    $theme1 = Theme::create(['name' => 'Theme A', 'emoji' => '🅰️']);
    $theme2 = Theme::create(['name' => 'Theme B', 'emoji' => '🅱️']);

    // Same deezer_track_id in both themes
    ThemeTrack::create(['theme_id' => $theme1->id, 'deezer_track_id' => 111, 'title' => 'Track 1', 'artist' => 'Artist', 'preview_url' => 'https://x.com/1.mp3', 'position' => 0]);
    ThemeTrack::create(['theme_id' => $theme2->id, 'deezer_track_id' => 111, 'title' => 'Track 1', 'artist' => 'Artist', 'preview_url' => 'https://x.com/1.mp3', 'position' => 0]);
    ThemeTrack::create(['theme_id' => $theme1->id, 'deezer_track_id' => 222, 'title' => 'Track 2', 'artist' => 'Artist', 'preview_url' => 'https://x.com/2.mp3', 'position' => 1]);

    $room = Room::create(['code' => 'DEDUP1', 'status' => RoomStatus::Waiting, 'max_players' => 4, 'round_duration' => 30, 'total_rounds' => 5]);
    $room->themes()->attach([$theme1->id, $theme2->id]);
    addPlayer($room);

    app(StartGameAction::class)->execute($room->fresh());

    // Only 2 unique tracks → only 2 rounds created (total_rounds=5 but only 2 available)
    expect($room->rounds()->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// EvaluateAnswerAction
// ---------------------------------------------------------------------------

test('réponse correcte (titre) rapporte >= 500 points', function () {
    ['room' => $room, 'track' => $track] = gameRoom();
    $player = addPlayer($room);
    addPlayer($room, guestName: 'Other'); // keep 2 active players so round doesn't end
    $round = playingRound($room, $track);

    $result = (new EvaluateAnswerAction)->execute($player, $round, 'Smells Like Teen Spirit');

    expect($result['correct'])->toBeTrue()
        ->and($result['answer_type'])->toBe('title')
        ->and($result['found_title'])->toBeTrue()
        ->and($result['found_artist'])->toBeFalse()
        ->and($result['points_earned'])->toBeGreaterThanOrEqual(500);

    expect($player->fresh()->score)->toBe($result['points_earned']);
});

test('réponse correcte (artiste) est acceptée', function () {
    ['room' => $room, 'track' => $track] = gameRoom();
    $player = addPlayer($room);
    addPlayer($room, guestName: 'Other');
    $round = playingRound($room, $track);

    $result = (new EvaluateAnswerAction)->execute($player, $round, 'nirvana');

    expect($result['correct'])->toBeTrue()
        ->and($result['answer_type'])->toBe('artist')
        ->and($result['found_artist'])->toBeTrue()
        ->and($result['found_title'])->toBeFalse();
});

test('les deux réponses correctes donnent des points cumulés', function () {
    ['room' => $room, 'track' => $track] = gameRoom();
    $player = addPlayer($room);
    addPlayer($room, guestName: 'Other'); // prevent EndRound after finding both
    $round = playingRound($room, $track);

    $titleResult  = (new EvaluateAnswerAction)->execute($player, $round, 'Smells Like Teen Spirit');
    $artistResult = (new EvaluateAnswerAction)->execute($player, $round, 'nirvana');

    expect($titleResult['correct'])->toBeTrue()
        ->and($artistResult['correct'])->toBeTrue()
        ->and($artistResult['found_title'])->toBeTrue()
        ->and($artistResult['found_artist'])->toBeTrue();

    $totalPoints = $titleResult['points_earned'] + $artistResult['points_earned'];
    expect($player->fresh()->score)->toBe($totalPoints);
});

test('réponse incorrecte rapporte 0 points et dispatche PlayerAnsweredWrong', function () {
    Event::fake([PlayerAnsweredWrong::class]);

    ['room' => $room, 'track' => $track] = gameRoom();
    $player = addPlayer($room);
    $round = playingRound($room, $track);

    $result = (new EvaluateAnswerAction)->execute($player, $round, 'Wrong Answer');

    expect($result['correct'])->toBeFalse()
        ->and($result['points_earned'])->toBe(0)
        ->and($player->fresh()->score)->toBe(0);

    Event::assertDispatched(PlayerAnsweredWrong::class, function ($e) use ($player) {
        return $e->gamePlayer->id === $player->id && $e->answerText === 'Wrong Answer';
    });
});

test('bonus vitesse diminue avec le temps de réponse', function () {
    ['room' => $room, 'track' => $track] = gameRoom(roundDuration: 30);
    $player1 = addPlayer($room, guestName: 'Fast');
    $player2 = addPlayer($room, guestName: 'Slow');

    $round = Round::create([
        'room_id' => $room->id,
        'theme_track_id' => $track->id,
        'track_title' => $track->title,
        'track_artist' => $track->artist,
        'round_number' => 1,
        'status' => RoundStatus::Playing,
        'started_at' => now()->subMilliseconds(100),
    ]);

    $fastResult = (new EvaluateAnswerAction)->execute($player1, $round, 'Smells Like Teen Spirit');

    $round2 = Round::create([
        'room_id' => $room->id,
        'theme_track_id' => $track->id,
        'track_title' => $track->title,
        'track_artist' => $track->artist,
        'round_number' => 2,
        'status' => RoundStatus::Playing,
        'started_at' => now()->subSeconds(25),
    ]);
    $slowResult = (new EvaluateAnswerAction)->execute($player2, $round2, 'Smells Like Teen Spirit');

    expect($fastResult['points_earned'])->toBeGreaterThan($slowResult['points_earned']);
});

test('réponse normalisée ignore les accents', function () {
    $theme = Theme::create(['name' => 'Accents', 'emoji' => '🎵']);
    $track = ThemeTrack::create([
        'theme_id' => $theme->id,
        'deezer_track_id' => 999,
        'title' => 'Déjà Vu',
        'artist' => 'Beyoncé',
        'preview_url' => 'https://example.com/preview.mp3',
        'position' => 0,
    ]);
    $room = Room::create([
        'code' => 'XYZABC',
        'status' => RoomStatus::Waiting,
        'max_players' => 4,
        'round_duration' => 30,
        'total_rounds' => 10,
    ]);
    $room->themes()->attach($theme->id);
    $player = addPlayer($room);
    addPlayer($room, guestName: 'Other');
    $round = playingRound($room, $track);

    $result = (new EvaluateAnswerAction)->execute($player, $round, 'deja vu');
    expect($result['correct'])->toBeTrue();
});

test('double réponse correcte lève DomainException', function () {
    ['room' => $room, 'track' => $track] = gameRoom(maxPlayers: 4);
    $player = addPlayer($room);
    addPlayer($room, guestName: 'Other'); // prevent EndRound
    $round = playingRound($room, $track);

    // Find title
    (new EvaluateAnswerAction)->execute($player, $round, 'Smells Like Teen Spirit');
    // Find artist
    (new EvaluateAnswerAction)->execute($player, $round, 'nirvana');

    // Third attempt → both already found
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

    // Player must find both title AND artist to be "done"
    (new EvaluateAnswerAction)->execute($player, $round, 'Smells Like Teen Spirit');
    (new EvaluateAnswerAction)->execute($player, $round, 'nirvana');

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
        'theme_track_id' => $track->id,
        'round_number' => 1,
        'status' => RoundStatus::Revealed,
        'started_at' => now(),
    ]);

    (new EndRoundAction)->execute($round);

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
        'theme_track_id' => $track->id,
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
        'theme_track_id' => $track->id,
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
        'theme_track_id' => $track->id,
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
    addPlayer($room, guestName: 'Other');
    $round = playingRound($room, $track);

    $response = $this->withSession(['game_player_id' => $player->id])
        ->postJson('/api/answers', [
            'answer_text' => 'Smells Like Teen Spirit',
            'room_code' => $room->code,
        ]);

    $response->assertOk()
        ->assertJsonStructure(['correct', 'points_earned', 'found_title', 'found_artist'])
        ->assertJsonPath('correct', true)
        ->assertJsonPath('found_title', true)
        ->assertJsonPath('found_artist', false);
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

test('AnswerController rejette la double réponse après avoir trouvé les deux', function () {
    ['room' => $room, 'track' => $track] = gameRoom(maxPlayers: 4);
    $player = addPlayer($room);
    addPlayer($room, guestName: 'Other'); // prevent EndRound
    $round = playingRound($room, $track);

    // Submit title
    $this->withSession(['game_player_id' => $player->id])
        ->postJson('/api/answers', ['answer_text' => 'Smells Like Teen Spirit', 'room_code' => $room->code]);

    // Submit artist
    $this->withSession(['game_player_id' => $player->id])
        ->postJson('/api/answers', ['answer_text' => 'nirvana', 'room_code' => $room->code]);

    // Third attempt → 422
    $response = $this->withSession(['game_player_id' => $player->id])
        ->postJson('/api/answers', ['answer_text' => 'Again', 'room_code' => $room->code]);

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

// ---------------------------------------------------------------------------
// PlayerAnsweredCorrectly
// ---------------------------------------------------------------------------

test('EvaluateAnswerAction dispatche PlayerAnsweredCorrectly sur bonne réponse', function () {
    Event::fake([PlayerAnsweredCorrectly::class]);
    Queue::fake();

    ['room' => $room, 'track' => $track] = gameRoom();
    $room->update(['status' => RoomStatus::Playing]);
    $player = addPlayer($room);
    addPlayer($room, guestName: 'Other');
    $round = playingRound($room, $track);

    (new EvaluateAnswerAction)->execute($player, $round, 'Smells Like Teen Spirit');

    Event::assertDispatched(PlayerAnsweredCorrectly::class, function ($e) use ($player) {
        return $e->gamePlayer->id === $player->id
            && $e->pointsEarned > 0
            && $e->answerType === 'title'
            && $e->foundTitle === true
            && $e->foundArtist === false;
    });
});

test('EvaluateAnswerAction ne dispatche pas PlayerAnsweredCorrectly sur mauvaise réponse', function () {
    Event::fake([PlayerAnsweredCorrectly::class]);
    Queue::fake();

    ['room' => $room, 'track' => $track] = gameRoom();
    $room->update(['status' => RoomStatus::Playing]);
    $player = addPlayer($room);
    $round = playingRound($room, $track);

    (new EvaluateAnswerAction)->execute($player, $round, 'Mauvaise réponse');

    Event::assertNotDispatched(PlayerAnsweredCorrectly::class);
});

// ---------------------------------------------------------------------------
// Heartbeat
// ---------------------------------------------------------------------------

test('HeartbeatController met à jour last_seen_at', function () {
    ['room' => $room] = gameRoom();
    $player = addPlayer($room);

    $this->withSession(['game_player_id' => $player->id])
        ->postJson('/api/heartbeat')
        ->assertJson(['ok' => true]);

    expect($player->fresh()->last_seen_at)->not->toBeNull();
});

test('HeartbeatController retourne 401 sans session', function () {
    $this->postJson('/api/heartbeat')
        ->assertStatus(401)
        ->assertJson(['ok' => false]);
});

// ---------------------------------------------------------------------------
// MarkInactivePlayers command
// ---------------------------------------------------------------------------

test('players:mark-inactive passe les joueurs inactifs en disconnected', function () {
    ['room' => $room] = gameRoom();
    $player = addPlayer($room);
    $player->update(['last_seen_at' => now()->subSeconds(100)]);

    $this->artisan('players:mark-inactive')->assertSuccessful();

    expect($player->fresh()->status)->toBe(GamePlayerStatus::Disconnected);
});

test('players:mark-inactive ne touche pas les joueurs récents', function () {
    ['room' => $room] = gameRoom();
    $player = addPlayer($room);
    $player->update(['last_seen_at' => now()->subSeconds(30)]);

    $this->artisan('players:mark-inactive')->assertSuccessful();

    expect($player->fresh()->status)->toBe(GamePlayerStatus::Active);
});

// ---------------------------------------------------------------------------
// SyncThemes command
// ---------------------------------------------------------------------------

test('themes:sync crée les thèmes et insère les tracks via Deezer', function () {
    Http::fake([
        'api.deezer.com/chart/*' => Http::response([
            'data' => [
                ['id' => 111, 'title' => 'Hit 1', 'duration' => 30, 'rank' => 200000, 'preview' => 'https://cdn.deezer.com/1.mp3', 'artist' => ['name' => 'Artist A'], 'album' => ['title' => 'Album A', 'cover_medium' => 'https://cdn.deezer.com/cover1.jpg']],
            ],
        ]),
        'api.deezer.com/search*' => Http::response([
            'data' => [
                ['id' => 222, 'title' => 'Hit 2', 'duration' => 30, 'rank' => 150000, 'preview' => 'https://cdn.deezer.com/2.mp3', 'artist' => ['name' => 'Artist B'], 'album' => ['title' => 'Album B', 'cover_medium' => 'https://cdn.deezer.com/cover2.jpg']],
            ],
        ]),
    ]);

    $this->artisan('themes:sync')->assertSuccessful();

    expect(Theme::count())->toBe(10);
    expect(ThemeTrack::count())->toBeGreaterThan(0);

    $rap = Theme::where('name', 'Rap FR')->first();
    expect($rap)->not->toBeNull()
        ->and($rap->emoji)->toBe('🎤')
        ->and($rap->deezer_genre_id)->toBe(116)
        ->and($rap->tracks_count)->toBeGreaterThanOrEqual(0);
});

test('themes:sync filtre les tracks sans preview ou avec rank insuffisant', function () {
    Http::fake([
        'api.deezer.com/chart/*' => Http::response(['data' => []]),
        'api.deezer.com/search*' => Http::response([
            'data' => [
                // rank trop bas (< 100 000)
                ['id' => 1, 'title' => 'Low rank', 'duration' => 30, 'rank' => 5000, 'preview' => 'https://cdn.deezer.com/1.mp3', 'artist' => ['name' => 'A'], 'album' => ['title' => 'B', 'cover_medium' => null]],
                // rank insuffisant (< 100 000)
                ['id' => 4, 'title' => 'Below threshold', 'duration' => 30, 'rank' => 99999, 'preview' => 'https://cdn.deezer.com/4.mp3', 'artist' => ['name' => 'A'], 'album' => ['title' => 'B', 'cover_medium' => null]],
                // pas de preview
                ['id' => 2, 'title' => 'No preview', 'duration' => 30, 'rank' => 200000, 'preview' => '', 'artist' => ['name' => 'A'], 'album' => ['title' => 'B', 'cover_medium' => null]],
                // durée trop courte
                ['id' => 3, 'title' => 'Too short', 'duration' => 20, 'rank' => 200000, 'preview' => 'https://cdn.deezer.com/3.mp3', 'artist' => ['name' => 'A'], 'album' => ['title' => 'B', 'cover_medium' => null]],
                // titre karaoke exclu
                ['id' => 5, 'title' => 'Song Karaoke Version', 'duration' => 30, 'rank' => 200000, 'preview' => 'https://cdn.deezer.com/5.mp3', 'artist' => ['name' => 'A'], 'album' => ['title' => 'B', 'cover_medium' => null]],
            ],
        ]),
    ]);

    $this->artisan('themes:sync')->assertSuccessful();

    expect(ThemeTrack::count())->toBe(0);
});

test('themes:sync marque is_top les tracks avec rank >= 500000', function () {
    Http::fake([
        'api.deezer.com/chart/*' => Http::response(['data' => []]),
        'api.deezer.com/search*' => Http::response([
            'data' => [
                ['id' => 10, 'title' => 'Mega Hit', 'duration' => 30, 'rank' => 600000, 'preview' => 'https://cdn.deezer.com/10.mp3', 'artist' => ['name' => 'Star'], 'album' => ['title' => 'Album', 'cover_medium' => null]],
                ['id' => 11, 'title' => 'Regular Hit', 'duration' => 30, 'rank' => 200000, 'preview' => 'https://cdn.deezer.com/11.mp3', 'artist' => ['name' => 'Artist'], 'album' => ['title' => 'Album', 'cover_medium' => null]],
            ],
        ]),
    ]);

    $this->artisan('themes:sync')->assertSuccessful();

    expect(ThemeTrack::where('is_top', true)->count())->toBeGreaterThan(0);
    expect(ThemeTrack::where('deezer_track_id', 10)->first()->is_top)->toBeTrue();
    expect(ThemeTrack::where('deezer_track_id', 11)->first()->is_top)->toBeFalse();
});

test('StartGameAction respecte top_only en ne prenant que les is_top tracks', function () {
    Queue::fake();
    Event::fake();
    Http::fake(['api.deezer.com/*' => Http::response(['preview' => 'https://cdn.deezer.com/fake.mp3', 'album' => ['cover_medium' => 'https://cdn.deezer.com/fake.jpg']])]);

    $theme = Theme::create(['name' => 'Top Theme', 'emoji' => '⭐']);
    ThemeTrack::create(['theme_id' => $theme->id, 'deezer_track_id' => 301, 'title' => 'Top Track', 'artist' => 'Star', 'preview_url' => 'https://x.com/top.mp3', 'position' => 0, 'is_top' => true]);
    ThemeTrack::create(['theme_id' => $theme->id, 'deezer_track_id' => 302, 'title' => 'Regular Track', 'artist' => 'Regular', 'preview_url' => 'https://x.com/reg.mp3', 'position' => 1, 'is_top' => false]);

    $room = Room::create(['code' => 'TOP001', 'status' => RoomStatus::Waiting, 'max_players' => 4, 'round_duration' => 30, 'total_rounds' => 5, 'top_only' => true]);
    $room->themes()->attach($theme->id);
    addPlayer($room);

    app(StartGameAction::class)->execute($room->fresh());

    // Only 1 is_top track available → only 1 round created
    expect($room->rounds()->count())->toBe(1);
    expect($room->rounds()->first()->themeTrack->deezer_track_id)->toBe(301);
});

// ---------------------------------------------------------------------------
// Replay
// ---------------------------------------------------------------------------

test('replayGame crée une nouvelle room avec les mêmes paramètres et dispatche RoomReplayed', function () {
    Event::fake([RoomReplayed::class]);

    $theme = Theme::create(['name' => 'Replay Theme', 'emoji' => '🔄']);
    ThemeTrack::create(['theme_id' => $theme->id, 'deezer_track_id' => 999, 'title' => 'T', 'artist' => 'A', 'preview_url' => 'https://x.com/t.mp3', 'position' => 0]);

    $room = Room::create([
        'code' => 'REPLAY',
        'status' => RoomStatus::Finished,
        'max_players' => 6,
        'round_duration' => 45,
        'total_rounds' => 8,
        'top_only' => false,
    ]);
    $room->themes()->attach($theme->id);

    $player = GamePlayer::create([
        'room_id' => $room->id,
        'guest_name' => 'Lucas',
        'status' => GamePlayerStatus::Active,
        'score' => 200,
        'joined_at' => now(),
    ]);

    session(['game_player_id' => $player->id]);

    Livewire::test(GameStage::class, ['code' => $room->code])
        ->call('replayGame');

    Event::assertDispatched(RoomReplayed::class, function ($e) use ($room) {
        return $e->roomId === $room->id;
    });

    // New room created with same settings
    $newRoom = Room::where('code', '!=', 'REPLAY')->latest()->first();
    expect($newRoom)->not->toBeNull()
        ->and($newRoom->max_players)->toBe(6)
        ->and($newRoom->round_duration)->toBe(45)
        ->and($newRoom->total_rounds)->toBe(8);
});
