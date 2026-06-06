<?php

namespace App\Actions;

use App\Enums\GamePlayerStatus;
use App\Enums\RoundStatus;
use App\Models\Answer;
use App\Models\GamePlayer;
use App\Models\Round;

class EvaluateAnswerAction
{
    public function execute(GamePlayer $gamePlayer, Round $round, string $answerText): array
    {
        if ($round->status !== RoundStatus::Playing) {
            throw new \DomainException('Round is not playing');
        }

        $round->load('playlistTrack', 'room');

        $maxAttempts = $round->room->max_attempts;

        // Use closures to get fresh query builders and avoid mutation between calls
        $playerAnswers = fn () => $round->answers()->where('game_player_id', $gamePlayer->id);

        if ($playerAnswers()->where('is_correct', true)->exists()) {
            throw new \DomainException('Already answered correctly');
        }

        $attemptCount = $playerAnswers()->count();

        if ($maxAttempts !== null && $attemptCount >= $maxAttempts) {
            throw new \DomainException('No attempts remaining');
        }

        $isCorrect = $this->normalize($answerText) === $this->normalize($round->playlistTrack->title)
            || $this->normalize($answerText) === $this->normalize($round->playlistTrack->artist);

        $responseTimeMs = (int) $round->started_at->diffInMilliseconds(now());

        $pointsEarned = 0;
        if ($isCorrect) {
            $roundDurationMs = $round->room->round_duration * 1000;
            $speedBonus = (int) max(0, 500 * (1 - $responseTimeMs / $roundDurationMs));
            $pointsEarned = 1000 + $speedBonus;
            $gamePlayer->increment('score', $pointsEarned);
        }

        Answer::create([
            'round_id' => $round->id,
            'game_player_id' => $gamePlayer->id,
            'answer_text' => $answerText,
            'is_correct' => $isCorrect,
            'response_time_ms' => $responseTimeMs,
        ]);

        $newAttemptCount = $attemptCount + 1;
        $attemptsRemaining = $maxAttempts !== null ? max(0, $maxAttempts - $newAttemptCount) : null;
        $playerDone = $isCorrect || $attemptsRemaining === 0;

        // End round when all active players are done
        $activePlayers = $round->room->gamePlayers()
            ->where('status', GamePlayerStatus::Active)
            ->pluck('id');

        $donePlayers = $activePlayers->filter(
            fn ($id) => $this->isPlayerDone($id, $round, $maxAttempts)
        )->count();

        if ($donePlayers >= $activePlayers->count()) {
            (new EndRoundAction)->execute($round);
        }

        return [
            'correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'attempts_remaining' => $attemptsRemaining,
            'correct_answer' => $playerDone ? [
                'title' => $round->playlistTrack->title,
                'artist' => $round->playlistTrack->artist,
                'cover_url' => $round->playlistTrack->cover_url,
            ] : null,
        ];
    }

    private function isPlayerDone(string $playerId, Round $round, ?int $maxAttempts): bool
    {
        // Use separate queries to avoid builder mutation
        if ($round->answers()->where('game_player_id', $playerId)->where('is_correct', true)->exists()) {
            return true;
        }

        return $maxAttempts !== null
            && $round->answers()->where('game_player_id', $playerId)->count() >= $maxAttempts;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

        if (class_exists('Normalizer')) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_D);
            $text = preg_replace('/\p{Mn}/u', '', $text);
        }

        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
