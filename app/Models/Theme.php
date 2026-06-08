<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'emoji',
        'deezer_genre_id',
        'tracks_count',
    ];

    protected function casts(): array
    {
        return [
            'deezer_genre_id' => 'integer',
            'tracks_count' => 'integer',
        ];
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(ThemeTrack::class)->orderBy('position');
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'room_themes');
    }
}
