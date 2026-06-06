<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaylistTrack extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'playlist_id',
        'deezer_track_id',
        'title',
        'artist',
        'album',
        'preview_url',
        'cover_url',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'deezer_track_id' => 'integer',
            'position' => 'integer',
        ];
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }
}
