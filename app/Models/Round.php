<?php

namespace App\Models;

use App\Enums\RoundStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'room_id',
        'playlist_track_id',
        'round_number',
        'status',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoundStatus::class,
            'round_number' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function playlistTrack(): BelongsTo
    {
        return $this->belongsTo(PlaylistTrack::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
