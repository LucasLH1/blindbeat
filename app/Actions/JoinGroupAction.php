<?php

namespace App\Actions;

use App\Enums\GroupRole;
use App\Events\GroupMemberJoined;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupScore;
use App\Models\User;

class JoinGroupAction
{
    public function execute(User $user, string $code): GroupMember
    {
        $group = Group::where('code', strtoupper($code))->firstOrFail();

        $alreadyMember = GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyMember) {
            throw new \DomainException('Vous êtes déjà membre de ce groupe');
        }

        $member = GroupMember::create([
            'group_id'  => $group->id,
            'user_id'   => $user->id,
            'role'      => GroupRole::Member,
            'joined_at' => now(),
        ]);

        GroupScore::firstOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id],
            [
                'total_normalized_points' => 0,
                'games_played'            => 0,
                'best_normalized_score'   => 0,
            ],
        );

        GroupMemberJoined::dispatch($group, $user);

        return $member;
    }
}
