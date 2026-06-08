<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThemeTrack extends Model
{
    use HasUuids;

    protected $fillable = [
        'theme_id',
        'deezer_track_id',
        'title',
        'artist',
        'album',
        'preview_url',
        'cover_url',
        'position',
        'rank',
        'is_top',
    ];

    protected function casts(): array
    {
        return [
            'deezer_track_id' => 'integer',
            'position' => 'integer',
            'rank' => 'integer',
            'is_top' => 'boolean',
        ];
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }
}
