<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupScore extends Model
{
    use HasUuids;

    protected $fillable = [
        'group_id',
        'user_id',
        'total_normalized_points',
        'games_played',
        'best_normalized_score',
        'last_played_at',
    ];

    protected function casts(): array
    {
        return [
            'total_normalized_points' => 'float',
            'games_played' => 'integer',
            'best_normalized_score' => 'float',
            'last_played_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
