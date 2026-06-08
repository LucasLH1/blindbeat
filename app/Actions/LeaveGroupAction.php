<?php

namespace App\Actions;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupScore;
use App\Models\User;

class LeaveGroupAction
{
    public function execute(User $user, Group $group): void
    {
        $member = GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $member) {
            throw new \DomainException('Vous n\'êtes pas membre de ce groupe');
        }

        $isOwner = $member->role === GroupRole::Owner;

        $otherMembers = $group->groupMembers()
            ->where('user_id', '!=', $user->id)
            ->orderBy('joined_at')
            ->get();

        // Owner alone → delete the whole group (cascade removes members, scores, pivots).
        if ($isOwner && $otherMembers->isEmpty()) {
            $group->delete();
            return;
        }

        // Owner leaving with others present → transfer ownership to the oldest member.
        if ($isOwner) {
            $heir = $otherMembers->first();
            $heir->update(['role' => GroupRole::Owner]);
            $group->update(['created_by' => $heir->user_id]);
        }

        $member->delete();

        GroupScore::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->delete();
    }
}
