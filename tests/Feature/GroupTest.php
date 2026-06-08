<?php

use App\Actions\CreateGroupAction;
use App\Actions\JoinGroupAction;
use App\Actions\LaunchGroupGameAction;
use App\Actions\LeaveGroupAction;
use App\Actions\RecordGroupScoreAction;
use App\Enums\GroupRole;
use App\Enums\RoomStatus;
use App\Events\GroupGameStarted;
use App\Events\GroupMemberJoined;
use App\Models\GamePlayer;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupScore;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Support\Facades\Event;

// ---------------------------------------------------------------------------
// CreateGroupAction
// ---------------------------------------------------------------------------

test('CreateGroupAction crée le groupe, le membre owner et un score vide', function () {
    $user = User::factory()->create();

    $group = (new CreateGroupAction)->execute($user, 'Les Mélomanes');

    expect($group->name)->toBe('Les Mélomanes')
        ->and($group->code)->toHaveLength(6)
        ->and($group->created_by)->toBe($user->id);

    $member = GroupMember::where('group_id', $group->id)->where('user_id', $user->id)->first();
    expect($member->role)->toBe(GroupRole::Owner);

    $score = GroupScore::where('group_id', $group->id)->where('user_id', $user->id)->first();
    expect($score)->not->toBeNull()
        ->and($score->total_normalized_points)->toBe(0.0)
        ->and($score->games_played)->toBe(0);
});

// ---------------------------------------------------------------------------
// JoinGroupAction
// ---------------------------------------------------------------------------

test('JoinGroupAction ajoute un membre, crée son score et dispatch l\'event', function () {
    Event::fake([GroupMemberJoined::class]);

    $owner = User::factory()->create();
    $group = (new CreateGroupAction)->execute($owner, 'Groupe');

    $newcomer = User::factory()->create();
    $member = (new JoinGroupAction)->execute($newcomer, $group->code);

    expect($member->role)->toBe(GroupRole::Member)
        ->and(GroupScore::where('group_id', $group->id)->where('user_id', $newcomer->id)->exists())->toBeTrue();

    Event::assertDispatched(GroupMemberJoined::class);
});

test('JoinGroupAction rejette un membre déjà présent', function () {
    $owner = User::factory()->create();
    $group = (new CreateGroupAction)->execute($owner, 'Groupe');

    expect(fn () => (new JoinGroupAction)->execute($owner, $group->code))
        ->toThrow(\DomainException::class, 'Vous êtes déjà membre de ce groupe');
});

// ---------------------------------------------------------------------------
// LeaveGroupAction
// ---------------------------------------------------------------------------

test('LeaveGroupAction supprime le groupe si owner seul', function () {
    $owner = User::factory()->create();
    $group = (new CreateGroupAction)->execute($owner, 'Groupe');

    (new LeaveGroupAction)->execute($owner, $group);

    expect(Group::find($group->id))->toBeNull()
        ->and(GroupMember::where('group_id', $group->id)->exists())->toBeFalse()
        ->and(GroupScore::where('group_id', $group->id)->exists())->toBeFalse();
});

test('LeaveGroupAction transfère l\'ownership au plus ancien membre', function () {
    $owner = User::factory()->create();
    $group = (new CreateGroupAction)->execute($owner, 'Groupe');

    $second = User::factory()->create();
    (new JoinGroupAction)->execute($second, $group->code);

    (new LeaveGroupAction)->execute($owner, $group);

    $group->refresh();
    $newOwnerMember = GroupMember::where('group_id', $group->id)->where('user_id', $second->id)->first();

    expect(Group::find($group->id))->not->toBeNull()
        ->and($newOwnerMember->role)->toBe(GroupRole::Owner)
        ->and($group->created_by)->toBe($second->id)
        ->and(GroupMember::where('group_id', $group->id)->where('user_id', $owner->id)->exists())->toBeFalse();
});

test('LeaveGroupAction rejette un non-membre', function () {
    $owner = User::factory()->create();
    $group = (new CreateGroupAction)->execute($owner, 'Groupe');
    $stranger = User::factory()->create();

    expect(fn () => (new LeaveGroupAction)->execute($stranger, $group))
        ->toThrow(\DomainException::class);
});

// ---------------------------------------------------------------------------
// LaunchGroupGameAction
// ---------------------------------------------------------------------------

test('LaunchGroupGameAction crée une room liée au groupe et dispatch l\'event', function () {
    Event::fake([GroupGameStarted::class]);

    $owner = User::factory()->create();
    $group = (new CreateGroupAction)->execute($owner, 'Groupe');
    $theme = Theme::create(['name' => 'Theme', 'emoji' => '🎵']);

    $room = (new LaunchGroupGameAction)->execute($owner, $group, [$theme->id], ['total_rounds' => 5]);

    expect($room->groups()->where('groups.id', $group->id)->exists())->toBeTrue()
        ->and($room->gamePlayers()->where('user_id', $owner->id)->exists())->toBeTrue();

    Event::assertDispatched(GroupGameStarted::class);
});

test('LaunchGroupGameAction rejette un non-membre', function () {
    $owner = User::factory()->create();
    $group = (new CreateGroupAction)->execute($owner, 'Groupe');
    $stranger = User::factory()->create();
    $theme = Theme::create(['name' => 'Theme', 'emoji' => '🎵']);

    expect(fn () => (new LaunchGroupGameAction)->execute($stranger, $group, [$theme->id], []))
        ->toThrow(\DomainException::class);
});

// ---------------------------------------------------------------------------
// RecordGroupScoreAction
// ---------------------------------------------------------------------------

test('RecordGroupScoreAction normalise et cumule les scores des membres', function () {
    Event::fake();

    $owner = User::factory()->create();
    $group = (new CreateGroupAction)->execute($owner, 'Groupe');
    $theme = Theme::create(['name' => 'Theme', 'emoji' => '🎵']);

    $room = (new LaunchGroupGameAction)->execute($owner, $group, [$theme->id], ['total_rounds' => 10]);

    // total_rounds = 10 → max possible = 15000. Score 7500 → normalized 500.
    $room->gamePlayers()->where('user_id', $owner->id)->update(['score' => 7500]);

    (new RecordGroupScoreAction)->execute($room->fresh());

    $score = GroupScore::where('group_id', $group->id)->where('user_id', $owner->id)->first();

    expect($score->total_normalized_points)->toBe(500.0)
        ->and($score->games_played)->toBe(1)
        ->and($score->best_normalized_score)->toBe(500.0)
        ->and($score->last_played_at)->not->toBeNull();
});

test('RecordGroupScoreAction est silencieuse si la room n\'est liée à aucun groupe', function () {
    Event::fake();
    $theme = Theme::create(['name' => 'Theme', 'emoji' => '🎵']);
    $host = User::factory()->create();

    $hostPlayer = (new \App\Actions\CreateRoomAction)->execute($host, [$theme->id], ['total_rounds' => 5]);

    (new RecordGroupScoreAction)->execute($hostPlayer->room);

    expect(GroupScore::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Controller / routes
// ---------------------------------------------------------------------------

test('GroupController@show interdit l\'accès à un non-membre', function () {
    $owner = User::factory()->create();
    $group = (new CreateGroupAction)->execute($owner, 'Groupe');
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('groups.show', $group))->assertForbidden();
});

test('GroupController@store crée un groupe et redirige', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('groups.store'), ['name' => 'Test'])
        ->assertRedirect();

    expect(Group::where('name', 'Test')->exists())->toBeTrue();
});
