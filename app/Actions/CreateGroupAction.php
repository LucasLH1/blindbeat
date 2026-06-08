<?php

namespace App\Actions;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupScore;
use App\Models\User;
use Illuminate\Support\Str;

class CreateGroupAction
{
    public function execute(User $user, string $name): Group
    {
        $group = Group::create([
            'name'       => $name,
            'code'       => $this->generateUniqueCode(),
            'created_by' => $user->id,
        ]);

        GroupMember::create([
            'group_id'  => $group->id,
            'user_id'   => $user->id,
            'role'      => GroupRole::Owner,
            'joined_at' => now(),
        ]);

        GroupScore::create([
            'group_id'                => $group->id,
            'user_id'                 => $user->id,
            'total_normalized_points' => 0,
            'games_played'            => 0,
            'best_normalized_score'   => 0,
        ]);

        return $group;
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Group::where('code', $code)->exists());

        return $code;
    }
}
