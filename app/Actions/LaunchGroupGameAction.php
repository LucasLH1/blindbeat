<?php

namespace App\Actions;

use App\Events\GroupGameStarted;
use App\Models\Group;
use App\Models\Room;
use App\Models\User;

class LaunchGroupGameAction
{
    public function execute(User $user, Group $group, array $themeIds, array $params): Room
    {
        $isMember = $group->groupMembers()
            ->where('user_id', $user->id)
            ->exists();

        if (! $isMember) {
            throw new \DomainException('Vous n\'êtes pas membre de ce groupe');
        }

        // The launching user is authenticated — pass the User directly so the host
        // GamePlayer carries a user_id (required by RecordGroupScoreAction).
        $hostPlayer = app(CreateRoomAction::class)->execute($user, $themeIds, $params);
        $room = $hostPlayer->room;

        $group->rooms()->attach($room->id);

        GroupGameStarted::dispatch($group, $room, $user->name);

        return $room;
    }
}
