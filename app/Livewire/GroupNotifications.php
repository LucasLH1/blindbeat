<?php

namespace App\Livewire;

use App\Enums\RoomStatus;
use App\Models\Room;
use Illuminate\View\View;
use Livewire\Component;

class GroupNotifications extends Component
{
    public array $notifications = [];
    public array $groupIds = [];

    public function mount(): void
    {
        $this->groupIds = auth()->user()?->groups()->pluck('groups.id')->toArray() ?? [];
    }

    public function addNotification(array $data): void
    {
        $roomCode = $data['room_code'] ?? null;

        if (! $roomCode) {
            return;
        }

        // Dédoublonne : un seul toast par room.
        foreach ($this->notifications as $n) {
            if (($n['room_code'] ?? null) === $roomCode) {
                return;
            }
        }

        $this->notifications[] = [
            'room_code'   => $roomCode,
            'room_id'     => $data['room_id'] ?? '',
            'launched_by' => $data['launched_by'] ?? '',
            'group_name'  => $data['group_name'] ?? '',
        ];
    }

    public function dismiss(string $roomCode): void
    {
        $this->notifications = array_values(array_filter(
            $this->notifications,
            fn ($n) => $n['room_code'] !== $roomCode,
        ));
    }

    public function joinRoom(string $roomCode): void
    {
        $this->redirect(route('rooms.join') . '?code=' . $roomCode);
    }

    /**
     * Polled every 30s (wire:poll) — drop toasts whose room has finished.
     */
    public function pruneFinished(): void
    {
        $this->notifications = array_values(array_filter(
            $this->notifications,
            function ($n) {
                $room = Room::find($n['room_id'] ?? null);

                return $room && $room->status !== RoomStatus::Finished;
            },
        ));
    }

    public function render(): View
    {
        return view('livewire.group-notifications');
    }
}
