<?php

use App\Actions\CreateRoomAction;
use App\Actions\EvaluateAnswerAction;
use App\Actions\JoinRoomAction;
use App\Actions\StartGameAction;
use App\Actions\StartRoundAction;
use App\Enums\GamePlayerStatus;
use App\Enums\RoomStatus;
use App\Enums\RoundStatus;
use App\Events\GameStarted;
use App\Events\PlayerJoined;
use App\Events\RoundEnded;
use App\Events\RoundStarted;
use App\Jobs\ScheduleRoundEnd;
use App\Livewire\GameStage;
use App\Livewire\Lobby;
use App\Models\Answer;
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

function makeTheme(): Theme
{
    static $i = 0;
    return Theme::create(['name' => 'Theme ' . (++$i), 'emoji' => '🎵']);
}

function addTrack(Theme $theme, int $position = 0): ThemeTrack
{
    return ThemeTrack::create([
        'theme_id' => $theme->id,
        'deezer_track_id' => random_int(1, 999999),
        'title' => 'Track ' . $position,
        'artist' => 'Artist',
        'preview_url' => 'https://example.com/preview.mp3',
        'position' => $position,
    ]);
}

function makeWaitingRoom(array $themeIds = [], int $maxPlayers = 8): Room
{
    static $i = 0;
    $code = strtoupper(substr(md5('room' . (++$i)), 0, 6));
    $room = Room::create([
        'code' => $code,
        'status' => RoomStatus::Waiting,
        'max_players' => $maxPlayers,
        'round_duration' => 30,
        'total_rounds' => 10,
    ]);
    if ($themeIds) {
        $room->themes()->attach($themeIds);
    }
    return $room;
}

// ---------------------------------------------------------------------------
// CreateRoomAction
// ---------------------------------------------------------------------------

test('CreateRoomAction crée un GamePlayer guest si user=null', function () {
    $theme = makeTheme();

    $hostPlayer = (new CreateRoomAction)->execute(null, [$theme->id], ['guest_name' => 'HostGuest']);

    expect($hostPlayer->user_id)->toBeNull()
        ->and($hostPlayer->guest_name)->toBe('HostGuest')
        ->and($hostPlayer->status)->toBe(GamePlayerStatus::Active);
});

test('CreateRoomAction crée une Room et un GamePlayer host', function () {
    $user = User::factory()->create();
    $theme = makeTheme();

    $hostPlayer = (new CreateRoomAction)->execute($user, [$theme->id], []);

    expect($hostPlayer)->toBeInstanceOf(GamePlayer::class)
        ->and($hostPlayer->user_id)->toBe($user->id)
        ->and($hostPlayer->status)->toBe(GamePlayerStatus::Active)
        ->and($hostPlayer->joined_at)->not->toBeNull();

    expect(Room::count())->toBe(1)
        ->and(GamePlayer::count())->toBe(1);
});

test('CreateRoomAction retourne le GamePlayer avec la relation room chargée', function () {
    $user = User::factory()->create();
    $theme = makeTheme();

    $hostPlayer = (new CreateRoomAction)->execute($user, [$theme->id], []);

    expect($hostPlayer->relationLoaded('room'))->toBeTrue()
        ->and($hostPlayer->room->status)->toBe(RoomStatus::Waiting);

    $hostPlayer->room->load('themes');
    expect($hostPlayer->room->themes->contains('id', $theme->id))->toBeTrue();
});

test('CreateRoomAction applique les params max_players, round_duration, total_rounds', function () {
    $user = User::factory()->create();
    $theme = makeTheme();

    $hostPlayer = (new CreateRoomAction)->execute($user, [$theme->id], [
        'max_players' => 4,
        'round_duration' => 20,
        'total_rounds' => 5,
    ]);

    expect($hostPlayer->room->max_players)->toBe(4)
        ->and($hostPlayer->room->round_duration)->toBe(20)
        ->and($hostPlayer->room->total_rounds)->toBe(5);
});

// ---------------------------------------------------------------------------
// JoinRoomAction
// ---------------------------------------------------------------------------

test('JoinRoomAction crée un GamePlayer anonyme', function () {
    $room = makeWaitingRoom();

    $player = (new JoinRoomAction)->execute($room, null, 'GuestUser');

    expect($player)->toBeInstanceOf(GamePlayer::class)
        ->and($player->guest_name)->toBe('GuestUser')
        ->and($player->user_id)->toBeNull()
        ->and($player->status)->toBe(GamePlayerStatus::Active);
});

test('JoinRoomAction autorise de rejoindre une room en cours (playing)', function () {
    $room = makeWaitingRoom();
    $room->update(['status' => RoomStatus::Playing]);

    $player = (new JoinRoomAction)->execute($room->fresh(), null, 'LateGuest');

    expect($player)->toBeInstanceOf(GamePlayer::class)
        ->and($player->guest_name)->toBe('LateGuest')
        ->and($player->status)->toBe(GamePlayerStatus::Active);
});

test('JoinRoomAction lève DomainException si room terminée (finished)', function () {
    $room = makeWaitingRoom();
    $room->update(['status' => RoomStatus::Finished]);

    expect(fn () => (new JoinRoomAction)->execute($room->fresh(), null, 'Guest'))
        ->toThrow(\DomainException::class, 'Room not available');
});

test('JoinRoomAction lève DomainException si room pleine', function () {
    $room = makeWaitingRoom(maxPlayers: 1);

    GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => null,
        'guest_name' => 'PlayerOne',
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now(),
    ]);

    expect(fn () => (new JoinRoomAction)->execute($room, null, 'PlayerTwo'))
        ->toThrow(\DomainException::class, 'Room full');
});

// ---------------------------------------------------------------------------
// RoomController@store
// ---------------------------------------------------------------------------

test('store stocke game_player_id en session et redirige vers rooms.lobby', function () {
    $user = User::factory()->create();
    $theme = makeTheme();

    $response = $this->actingAs($user)->post(route('rooms.store'), [
        'theme_ids' => [$theme->id],
    ]);

    $response->assertSessionHas('game_player_id');

    $room = Room::first();
    $response->assertRedirect(route('rooms.lobby', $room->code));
});

test('store crée une room guest si non authentifié avec un pseudo', function () {
    $theme = makeTheme();

    $response = $this->post(route('rooms.store'), [
        'theme_ids' => [$theme->id],
        'guest_name' => 'GuestHost',
    ]);

    $response->assertSessionHas('game_player_id');

    $room = Room::first();
    $response->assertRedirect(route('rooms.lobby', $room->code));

    $hostPlayer = GamePlayer::first();
    expect($hostPlayer->user_id)->toBeNull()
        ->and($hostPlayer->guest_name)->toBe('GuestHost');
});

test('store échoue si non authentifié et pas de pseudo', function () {
    $theme = makeTheme();

    $this->post(route('rooms.store'), ['theme_ids' => [$theme->id]])
        ->assertSessionHasErrors('guest_name');
});

test('store échoue si aucun thème sélectionné', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('rooms.store'), ['theme_ids' => []])
        ->assertSessionHasErrors('theme_ids');
});

// ---------------------------------------------------------------------------
// RoomController@join
// ---------------------------------------------------------------------------

test('join stocke game_player_id en session et redirige vers rooms.lobby', function () {
    $room = makeWaitingRoom();

    $response = $this->post(route('rooms.join.post'), [
        'code' => $room->code,
        'guest_name' => 'GuestPlayer',
    ]);

    $response->assertRedirect(route('rooms.lobby', $room->code));
    $response->assertSessionHas('game_player_id');
});

test('join échoue si guest_name absent et non authentifié', function () {
    $room = makeWaitingRoom();

    $this->post(route('rooms.join.post'), ['code' => $room->code])
        ->assertSessionHasErrors('guest_name');
});

test('join fonctionne sans guest_name si authentifié', function () {
    $user = User::factory()->create();
    $room = makeWaitingRoom();

    $response = $this->actingAs($user)->post(route('rooms.join.post'), [
        'code' => $room->code,
    ]);

    $response->assertRedirect(route('rooms.lobby', $room->code));
    $response->assertSessionHas('game_player_id');
});

// ---------------------------------------------------------------------------
// RoomController@lobby — direct access guard
// ---------------------------------------------------------------------------

test('lobby redirige vers /join?code= si pas de session', function () {
    $room = makeWaitingRoom();

    $this->get(route('rooms.lobby', $room->code))
        ->assertRedirect(route('rooms.join', ['code' => $room->code]));
});

test('lobby redirige vers /join si session appartient à une autre room', function () {
    $room1 = makeWaitingRoom();
    $room2 = makeWaitingRoom();

    $player = GamePlayer::create([
        'room_id' => $room1->id,
        'user_id' => null,
        'guest_name' => 'Guest',
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now(),
    ]);

    $this->withSession(['game_player_id' => $player->id])
        ->get(route('rooms.lobby', $room2->code))
        ->assertRedirect(route('rooms.join', ['code' => $room2->code]));
});

test('lobby affiche la vue si session valide pour cette room', function () {
    $user = User::factory()->create();
    $room = makeWaitingRoom();

    $player = GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now(),
    ]);

    $this->withSession(['game_player_id' => $player->id])
        ->get(route('rooms.lobby', $room->code))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Lobby Livewire component
// ---------------------------------------------------------------------------

test('Lobby redirige vers /join?code= si pas de game_player_id en session', function () {
    $room = makeWaitingRoom();

    Livewire::test(Lobby::class, ['code' => $room->code])
        ->assertRedirect(route('rooms.join', ['code' => $room->code]));
});

test('Lobby se monte correctement avec un game_player_id valide en session', function () {
    $user = User::factory()->create();
    $room = makeWaitingRoom();

    $player = GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now(),
    ]);

    session(['game_player_id' => $player->id]);

    Livewire::test(Lobby::class, ['code' => $room->code])
        ->assertSet('gamePlayerId', $player->id)
        ->assertSet('isHost', true);
});

test('Lobby isHost est false pour un joueur non-host', function () {
    $host = User::factory()->create();
    $guest = User::factory()->create();
    $room = makeWaitingRoom();

    GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => $host->id,
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now()->subSecond(),
    ]);

    $guestPlayer = GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => $guest->id,
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now(),
    ]);

    session(['game_player_id' => $guestPlayer->id]);

    Livewire::test(Lobby::class, ['code' => $room->code])
        ->assertSet('isHost', false);
});

// ---------------------------------------------------------------------------
// PlayerJoined broadcast
// ---------------------------------------------------------------------------

test('JoinRoomAction dispatche PlayerJoined sur le bon channel', function () {
    Event::fake([PlayerJoined::class]);

    $user = User::factory()->create();
    $room = makeWaitingRoom();

    $guest = User::factory()->create();
    (new JoinRoomAction)->execute($room, $guest, null);

    Event::assertDispatched(PlayerJoined::class, function (PlayerJoined $event) use ($room) {
        return $event->roomCode === $room->code;
    });
});

test('JoinRoomAction dispatche PlayerJoined pour un joueur anonyme', function () {
    Event::fake([PlayerJoined::class]);

    $room = makeWaitingRoom();

    (new JoinRoomAction)->execute($room, null, 'SuperGuest');

    Event::assertDispatched(PlayerJoined::class, function (PlayerJoined $event) use ($room) {
        return $event->roomCode === $room->code
            && $event->gamePlayer->guest_name === 'SuperGuest';
    });
});

test('PlayerJoined broadcast sur le bon canal avec le bon payload', function () {
    $room = makeWaitingRoom();
    $player = makeActivePlayer($room, guestName: 'TestPlayer');

    $event = new PlayerJoined($player, $room->code);

    expect($event->broadcastAs())->toBe('PlayerJoined')
        ->and($event->broadcastOn()[0]->name)->toBe('presence-room.' . $room->code)
        ->and($event->broadcastWith()['id'])->toBe($player->id)
        ->and($event->broadcastWith()['display_name'])->toBe('TestPlayer');
});

// ---------------------------------------------------------------------------
// StartGameAction
// ---------------------------------------------------------------------------

test('StartGameAction passe la room en playing et dispatche GameStarted + RoundStarted', function () {
    Queue::fake();
    Event::fake([GameStarted::class, RoundStarted::class]);
    Http::fake(['api.deezer.com/*' => Http::response(['preview' => 'https://cdn.deezer.com/fake.mp3', 'album' => ['cover_medium' => 'https://cdn.deezer.com/fake.jpg']])]);

    $theme = makeTheme();
    addTrack($theme, 0);

    $room = makeWaitingRoom([$theme->id]);
    $room->update(['total_rounds' => 1]);

    GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => null,
        'guest_name' => 'Host',
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now()->subSecond(),
    ]);

    app(StartGameAction::class)->execute($room->fresh());

    expect($room->fresh()->status)->toBe(RoomStatus::Playing);
    Event::assertDispatched(GameStarted::class);
    Event::assertDispatched(RoundStarted::class);
});

test('StartGameAction lève DomainException si room pas en waiting', function () {
    $room = makeWaitingRoom();
    $room->update(['status' => RoomStatus::Playing]);

    expect(fn () => app(StartGameAction::class)->execute($room->fresh()))
        ->toThrow(\DomainException::class, 'Room is not waiting');
});

test('StartGameAction démarre avec 1 seul joueur actif', function () {
    Queue::fake();
    Event::fake();
    Http::fake(['api.deezer.com/*' => Http::response(['preview' => 'https://cdn.deezer.com/fake.mp3', 'album' => ['cover_medium' => 'https://cdn.deezer.com/fake.jpg']])]);

    $theme = makeTheme();
    addTrack($theme);
    $room = makeWaitingRoom([$theme->id]);

    GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => null,
        'guest_name' => 'Solo',
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now(),
    ]);

    app(StartGameAction::class)->execute($room->fresh());

    expect($room->fresh()->status)->toBe(RoomStatus::Playing);
});

// ---------------------------------------------------------------------------
// Lobby::startGame
// ---------------------------------------------------------------------------

test('Lobby::startGame redirige le host vers rooms.play', function () {
    Queue::fake();
    Event::fake([GameStarted::class, RoundStarted::class]);
    Http::fake(['api.deezer.com/*' => Http::response(['preview' => 'https://cdn.deezer.com/fake.mp3', 'album' => ['cover_medium' => 'https://cdn.deezer.com/fake.jpg']])]);

    $user = User::factory()->create();
    $theme = makeTheme();
    addTrack($theme, 0);

    $room = makeWaitingRoom([$theme->id]);
    $room->update(['total_rounds' => 1]);

    $host = GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now()->subSecond(),
    ]);
    GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => null,
        'guest_name' => 'Guest',
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now(),
    ]);

    session(['game_player_id' => $host->id]);

    Livewire::test(Lobby::class, ['code' => $room->code])
        ->call('startGame')
        ->assertRedirect(route('rooms.play', $room->code));

    expect($room->fresh()->status)->toBe(RoomStatus::Playing);
});

test('Lobby::startGame ne fait rien pour un non-host', function () {
    Event::fake([GameStarted::class, RoundStarted::class]);

    $user = User::factory()->create();
    $theme = makeTheme();
    addTrack($theme, 0);
    $room = makeWaitingRoom([$theme->id]);

    GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now()->subSecond(),
    ]);
    $guest = GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => null,
        'guest_name' => 'Guest',
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now(),
    ]);

    session(['game_player_id' => $guest->id]);

    Livewire::test(Lobby::class, ['code' => $room->code])
        ->call('startGame')
        ->assertNoRedirect();

    expect($room->fresh()->status)->toBe(RoomStatus::Waiting);
    Event::assertNotDispatched(GameStarted::class);
});

test('Lobby::mount redirige vers rooms.play si room déjà en playing', function () {
    $user = User::factory()->create();
    $room = makeWaitingRoom();
    $room->update(['status' => RoomStatus::Playing]);

    $host = GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now(),
    ]);

    session(['game_player_id' => $host->id]);

    Livewire::test(Lobby::class, ['code' => $room->code])
        ->assertRedirect(route('rooms.play', $room->code));
});

// ---------------------------------------------------------------------------
// RoomController@start
// ---------------------------------------------------------------------------

test('POST /rooms/{code}/start démarre la partie et redirige le host', function () {
    Queue::fake();
    Event::fake([GameStarted::class, RoundStarted::class]);
    Http::fake(['api.deezer.com/*' => Http::response(['preview' => 'https://cdn.deezer.com/fake.mp3', 'album' => ['cover_medium' => 'https://cdn.deezer.com/fake.jpg']])]);

    $user = User::factory()->create();
    $theme = makeTheme();
    addTrack($theme, 0);

    $room = makeWaitingRoom([$theme->id]);
    $room->update(['total_rounds' => 1]);

    GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now()->subSecond(),
    ]);
    GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => null,
        'guest_name' => 'Guest',
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('rooms.start', $room->code))
        ->assertRedirect(route('rooms.play', $room->code));

    expect($room->fresh()->status)->toBe(RoomStatus::Playing);
    Event::assertDispatched(GameStarted::class);
});

test('POST /rooms/{code}/start retourne 403 pour un non-host', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $room = makeWaitingRoom();

    GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => $owner->id,
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now()->subSecond(),
    ]);
    GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => $other->id,
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now(),
    ]);

    $this->actingAs($other)
        ->post(route('rooms.start', $room->code))
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Helpers for gameplay tests
// ---------------------------------------------------------------------------

function makeActivePlayer(Room $room, ?User $user = null, ?string $guestName = null, int $offsetSeconds = 0): GamePlayer
{
    return GamePlayer::create([
        'room_id' => $room->id,
        'user_id' => $user?->id,
        'guest_name' => $guestName,
        'status' => GamePlayerStatus::Active,
        'score' => 0,
        'joined_at' => now()->subSeconds($offsetSeconds),
    ]);
}

function makePlayingRound(Room $room, ThemeTrack $track): Round
{
    return Round::create([
        'room_id' => $room->id,
        'theme_track_id' => $track->id,
        'round_number' => 1,
        'status' => RoundStatus::Playing,
        'started_at' => now()->subSeconds(5),
    ]);
}

// ---------------------------------------------------------------------------
// ScheduleRoundEnd job
// ---------------------------------------------------------------------------

test('StartRoundAction dispatche ScheduleRoundEnd avec le bon délai', function () {
    Queue::fake();
    Event::fake([RoundStarted::class]);
    Http::fake(['api.deezer.com/*' => Http::response(['preview' => 'https://cdn.deezer.com/fake.mp3', 'album' => ['cover_medium' => 'https://cdn.deezer.com/fake.jpg']])]);

    $theme = makeTheme();
    $track = addTrack($theme, 0);
    $room = makeWaitingRoom([$theme->id]);

    $round = Round::create([
        'room_id' => $room->id,
        'theme_track_id' => $track->id,
        'round_number' => 1,
        'status' => RoundStatus::Waiting,
        'started_at' => null,
    ]);

    (new StartRoundAction)->execute($round);

    Queue::assertPushed(ScheduleRoundEnd::class, function ($job) use ($round) {
        return $job->round->id === $round->id;
    });
});

test('ScheduleRoundEnd déclenche EndRoundAction si round encore playing', function () {
    Event::fake([RoundEnded::class]);
    Queue::fake();

    $theme = makeTheme();
    $track = addTrack($theme, 0);
    $room = makeWaitingRoom([$theme->id]);
    makeActivePlayer($room, offsetSeconds: 2);
    makeActivePlayer($room, guestName: 'Guest');
    $round = makePlayingRound($room, $track);

    (new ScheduleRoundEnd($round))->handle();

    expect($round->fresh()->status)->toBe(RoundStatus::Revealed);
    Event::assertDispatched(RoundEnded::class);
});

test('ScheduleRoundEnd est idempotent si round déjà revealed', function () {
    Event::fake([RoundEnded::class]);
    Queue::fake();

    $theme = makeTheme();
    $track = addTrack($theme, 0);
    $room = makeWaitingRoom([$theme->id]);
    $round = Round::create([
        'room_id' => $room->id,
        'theme_track_id' => $track->id,
        'round_number' => 1,
        'status' => RoundStatus::Revealed,
        'started_at' => now()->subSeconds(10),
        'ended_at' => now()->subSeconds(5),
    ]);

    (new ScheduleRoundEnd($round))->handle();

    expect($round->fresh()->status)->toBe(RoundStatus::Revealed);
    Event::assertNotDispatched(RoundEnded::class);
});

// ---------------------------------------------------------------------------
// EvaluateAnswerAction — multiple attempts
// ---------------------------------------------------------------------------

test('EvaluateAnswerAction autorise une 2e tentative si max_attempts=2 et 1ère réponse fausse', function () {
    Event::fake();
    Queue::fake();

    $theme = makeTheme();
    $track = addTrack($theme, 0);
    $room = makeWaitingRoom([$theme->id]);
    $room->update(['max_attempts' => 2]);

    $player1 = makeActivePlayer($room, offsetSeconds: 2);
    makeActivePlayer($room, guestName: 'Guest');
    $round = makePlayingRound($room, $track);

    $result = (new EvaluateAnswerAction)->execute($player1, $round, 'mauvaise réponse');

    expect($result['correct'])->toBeFalse()
        ->and($result['attempts_remaining'])->toBe(1)
        ->and($result['correct_answer'])->toBeNull();

    expect($round->fresh()->status)->toBe(RoundStatus::Playing);
});

test('EvaluateAnswerAction lève DomainException quand max_attempts épuisées', function () {
    Queue::fake();

    $theme = makeTheme();
    $track = addTrack($theme, 0);
    $room = makeWaitingRoom([$theme->id]);
    $room->update(['max_attempts' => 1]);

    $player1 = makeActivePlayer($room, offsetSeconds: 2);
    makeActivePlayer($room, guestName: 'Guest');
    $round = makePlayingRound($room, $track);

    Answer::create([
        'round_id' => $round->id,
        'game_player_id' => $player1->id,
        'answer_text' => 'wrong',
        'is_correct' => false,
        'response_time_ms' => 1000,
    ]);

    expect(fn () => (new EvaluateAnswerAction)->execute($player1, $round, 'autre réponse'))
        ->toThrow(\DomainException::class, 'No attempts remaining');
});

test('EvaluateAnswerAction déclenche EndRound quand tous les joueurs ont épuisé leurs tentatives', function () {
    Event::fake([RoundEnded::class]);
    Queue::fake();

    $theme = makeTheme();
    $track = addTrack($theme, 0);
    $room = makeWaitingRoom([$theme->id]);
    $room->update(['max_attempts' => 1]);

    $player1 = makeActivePlayer($room, offsetSeconds: 2);
    $player2 = makeActivePlayer($room, guestName: 'Guest');
    $round = makePlayingRound($room, $track);

    (new EvaluateAnswerAction)->execute($player1, $round, 'wrong');
    (new EvaluateAnswerAction)->execute($player2, $round, 'wrong');

    expect($round->fresh()->status)->toBe(RoundStatus::Revealed);
    Event::assertDispatched(RoundEnded::class);
});

test('EvaluateAnswerAction retourne attempts_remaining=null si max_attempts non configuré', function () {
    Event::fake();
    Queue::fake();

    $theme = makeTheme();
    $track = addTrack($theme, 0);
    $room = makeWaitingRoom([$theme->id]);

    $player1 = makeActivePlayer($room, offsetSeconds: 2);
    makeActivePlayer($room, guestName: 'Guest');
    $round = makePlayingRound($room, $track);

    $result = (new EvaluateAnswerAction)->execute($player1, $round, 'wrong answer');

    expect($result['attempts_remaining'])->toBeNull()
        ->and($result['correct_answer'])->toBeNull();
});

// ---------------------------------------------------------------------------
// Disconnection — Lobby::playerLeft
// ---------------------------------------------------------------------------

test('Lobby::playerLeft passe le joueur en disconnected et rafraîchit la liste', function () {
    $user = User::factory()->create();
    $room = makeWaitingRoom();

    $host = makeActivePlayer($room, $user, offsetSeconds: 2);
    $guest = makeActivePlayer($room, guestName: 'Guest');

    session(['game_player_id' => $host->id]);

    Livewire::test(Lobby::class, ['code' => $room->code])
        ->call('playerLeft', $guest->id);

    expect($guest->fresh()->status)->toBe(GamePlayerStatus::Disconnected);
});

// ---------------------------------------------------------------------------
// Disconnection — GameStage::playerLeft triggers EndRound
// ---------------------------------------------------------------------------

test('GameStage::playerLeft déclenche EndRound si tous les joueurs actifs ont répondu', function () {
    Event::fake([RoundEnded::class]);
    Queue::fake();
    Http::fake(['api.deezer.com/*' => Http::response(['preview' => 'https://cdn.deezer.com/fake.mp3', 'album' => ['cover_medium' => 'https://cdn.deezer.com/fake.jpg']])]);

    $user = User::factory()->create();
    $theme = makeTheme();
    $track = addTrack($theme, 0);
    $room = makeWaitingRoom([$theme->id]);
    $room->update(['status' => RoomStatus::Playing, 'current_round_number' => 1, 'started_at' => now()]);

    $player1 = makeActivePlayer($room, $user, offsetSeconds: 2);
    $player2 = makeActivePlayer($room, guestName: 'Guest');
    $round = makePlayingRound($room, $track);

    Answer::create([
        'round_id' => $round->id,
        'game_player_id' => $player1->id,
        'answer_text' => $track->title,
        'answer_type' => 'title',
        'is_correct' => true,
        'response_time_ms' => 2000,
    ]);
    Answer::create([
        'round_id' => $round->id,
        'game_player_id' => $player1->id,
        'answer_text' => $track->artist,
        'answer_type' => 'artist',
        'is_correct' => true,
        'response_time_ms' => 3000,
    ]);

    session(['game_player_id' => $player1->id]);

    Livewire::test(GameStage::class, ['code' => $room->code])
        ->call('playerLeft', $player2->id);

    expect($round->fresh()->status)->toBe(RoundStatus::Revealed);
    Event::assertDispatched(RoundEnded::class);
});

// ---------------------------------------------------------------------------
// BroadcastAuthController — presence channel
// ---------------------------------------------------------------------------

test('POST /broadcasting/auth retourne 403 sans session', function () {
    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'presence-room.ABCDEF',
        'socket_id'    => '123.456',
    ])->assertForbidden();
});

test('POST /broadcasting/auth retourne 403 si joueur dans une autre room', function () {
    $room1    = makeWaitingRoom();
    $room2    = makeWaitingRoom();

    $player = makeActivePlayer($room1, guestName: 'Guest');

    $this->withSession(['game_player_id' => $player->id])
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-room.' . $room2->code,
            'socket_id'    => '123.456',
        ])->assertForbidden();
});

test('POST /broadcasting/auth retourne le token Pusher pour un guest valide', function () {
    $room     = makeWaitingRoom();
    $player   = makeActivePlayer($room, guestName: 'GuestHost');

    $response = $this->withSession(['game_player_id' => $player->id])
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-room.' . $room->code,
            'socket_id'    => '123.456',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['auth', 'channel_data']);

    $channelData = json_decode($response->json('channel_data'), true);
    expect($channelData['user_id'])->toBe($player->id)
        ->and($channelData['user_info']['display_name'])->toBe('GuestHost');
});

test('POST /broadcasting/auth retourne le token pour un user authentifié', function () {
    $user     = User::factory()->create();
    $room     = makeWaitingRoom();
    $player   = makeActivePlayer($room, $user);

    $response = $this->actingAs($user)
        ->withSession(['game_player_id' => $player->id])
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-room.' . $room->code,
            'socket_id'    => '123.456',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['auth', 'channel_data']);

    $channelData = json_decode($response->json('channel_data'), true);
    expect($channelData['user_id'])->toBe($player->id);
});
