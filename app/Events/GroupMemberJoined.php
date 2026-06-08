<?php

namespace App\Events;

use App\Models\Group;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMemberJoined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Group $group,
        public readonly User $user,
    ) {}

    public function broadcastAs(): string
    {
        return 'GroupMemberJoined';
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('group.' . $this->group->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'name'    => $this->user->name,
        ];
    }
}
