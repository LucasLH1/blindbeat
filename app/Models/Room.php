<?php

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'playlist_id',
        'code',
        'status',
        'current_round_number',
        'max_players',
        'round_duration',
        'total_rounds',
        'max_attempts',
        'started_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'current_round_number' => 'integer',
            'max_players' => 'integer',
            'round_duration' => 'integer',
            'total_rounds' => 'integer',
            'max_attempts' => 'integer',
            'started_at' => 'datetime',
        ];
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function gamePlayers(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->orderBy('round_number');
    }
}
