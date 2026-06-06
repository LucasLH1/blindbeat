<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'round_id',
        'game_player_id',
        'answer_text',
        'is_correct',
        'response_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'response_time_ms' => 'integer',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }
}
