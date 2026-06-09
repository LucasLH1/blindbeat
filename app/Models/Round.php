<?php

namespace App\Models;

use App\Enums\AnswerType;
use App\Enums\GamePlayerStatus;
use App\Enums\RoundStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Round extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'room_id',
        'theme_track_id',
        'track_title',
        'track_artist',
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

    public function themeTrack(): BelongsTo
    {
        return $this->belongsTo(ThemeTrack::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    /**
     * Whether the round can be closed: every active player has either found
     * both title and artist, or exhausted their attempts. Also true when no
     * active players remain. Loads the answers collection once and filters in
     * memory — no per-player queries (no N+1).
     */
    public function shouldEnd(): bool
    {
        $maxAttempts = $this->room->max_attempts;

        $activePlayerIds = $this->room->gamePlayers()
            ->where('status', GamePlayerStatus::Active)
            ->pluck('id');

        if ($activePlayerIds->isEmpty()) {
            return true;
        }

        $answers = $this->answers()->get();

        $finished = $activePlayerIds->filter(
            fn ($id) => $this->playerHasFinished($id, $maxAttempts, $answers)
        )->count();

        return $finished >= $activePlayerIds->count();
    }

    /**
     * Whether a single player is done for this round, evaluated against an
     * already-loaded answers collection (caller loads it once).
     */
    public function playerHasFinished(string $playerId, ?int $maxAttempts, Collection $answers): bool
    {
        $playerAnswers = $answers->where('game_player_id', $playerId);

        $foundTitle = $playerAnswers->contains(
            fn ($a) => $a->answer_type === AnswerType::Title && $a->is_correct
        );
        $foundArtist = $playerAnswers->contains(
            fn ($a) => $a->answer_type === AnswerType::Artist && $a->is_correct
        );

        if ($foundTitle && $foundArtist) {
            return true;
        }

        if ($maxAttempts !== null) {
            return $playerAnswers->where('is_correct', false)->count() >= $maxAttempts;
        }

        return false;
    }
}
