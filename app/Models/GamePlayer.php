<?php

namespace App\Models;

use App\Enums\GamePlayerStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamePlayer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'room_id',
        'user_id',
        'guest_name',
        'status',
        'score',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GamePlayerStatus::class,
            'score' => 'integer',
            'joined_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function displayName(): string
    {
        return $this->guest_name ?? $this->user?->name ?? 'Inconnu';
    }
}
