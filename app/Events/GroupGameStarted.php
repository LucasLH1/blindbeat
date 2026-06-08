<?php

namespace App\Events;

use App\Models\Group;
use App\Models\Room;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupGameStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Group $group,
        public readonly Room $room,
        public readonly string $launchedBy,
    ) {}

    public function broadcastAs(): string
    {
        return 'GroupGameStarted';
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('group.' . $this->group->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'group_id'    => $this->group->id,
            'group_name'  => $this->group->name,
            'room_code'   => $this->room->code,
            'room_id'     => $this->room->id,
            'launched_by' => $this->launchedBy,
            'theme_names' => $this->room->themes()->pluck('name')->toArray(),
        ];
    }
}
