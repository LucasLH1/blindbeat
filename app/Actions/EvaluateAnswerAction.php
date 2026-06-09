<?php

namespace App\Actions;

use App\Enums\AnswerType;
use App\Enums\RoundStatus;
use App\Events\PlayerAnsweredCorrectly;
use App\Events\PlayerAnsweredWrong;
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

        $round->load('themeTrack', 'room');

        $trackTitle  = $round->track_title ?? $round->themeTrack?->title ?? '';
        $trackArtist = $round->track_artist ?? $round->themeTrack?->artist ?? '';
        $maxAttempts = $round->room->max_attempts;

        $foundTitle  = $round->answers()
            ->where('game_player_id', $gamePlayer->id)
            ->where('answer_type', AnswerType::Title->value)
            ->where('is_correct', true)
            ->exists();

        $foundArtist = $round->answers()
            ->where('game_player_id', $gamePlayer->id)
            ->where('answer_type', AnswerType::Artist->value)
            ->where('is_correct', true)
            ->exists();

        if ($foundTitle && $foundArtist) {
            throw new \DomainException('Already answered correctly');
        }

        $wrongCount = $round->answers()
            ->where('game_player_id', $gamePlayer->id)
            ->where('is_correct', false)
            ->count();

        if ($maxAttempts !== null && $wrongCount >= $maxAttempts) {
            throw new \DomainException('No attempts remaining');
        }

        $responseTimeMs  = (int) $round->started_at->diffInMilliseconds(now());
        $roundDurationMs = $round->room->round_duration * 1000;
        $speedBonus      = (int) max(0, 500 * (1 - $responseTimeMs / $roundDurationMs));
        $basePoints      = 500;
        $points          = $basePoints + $speedBonus;

        $isCorrect    = false;
        $answerType   = null;
        $pointsEarned = 0;

        if (!$foundTitle && self::isCorrectAnswer($answerText, $trackTitle)) {
            $isCorrect    = true;
            $answerType   = AnswerType::Title;
            $foundTitle   = true;
            $pointsEarned = $points;
        } elseif (!$foundArtist && self::isCorrectAnswer($answerText, $trackArtist)) {
            $isCorrect    = true;
            $answerType   = AnswerType::Artist;
            $foundArtist  = true;
            $pointsEarned = $points;
        }

        Answer::create([
            'round_id'         => $round->id,
            'game_player_id'   => $gamePlayer->id,
            'answer_text'      => $answerText,
            'answer_type'      => $answerType?->value,
            'points_earned'    => $pointsEarned,
            'is_correct'       => $isCorrect,
            'response_time_ms' => $responseTimeMs,
        ]);

        if ($isCorrect) {
            $gamePlayer->increment('score', $pointsEarned);
            PlayerAnsweredCorrectly::dispatch(
                $gamePlayer,
                $round->room_id,
                $pointsEarned,
                $responseTimeMs,
                $answerType->value,
                $foundTitle,
                $foundArtist,
            );
        } else {
            PlayerAnsweredWrong::dispatch($gamePlayer, $round->room_id, $answerText);
        }

        if ($round->shouldEnd()) {
            (new EndRoundAction)->execute($round);
        }

        $newWrongCount      = $wrongCount + ($isCorrect ? 0 : 1);
        $attemptsRemaining  = $maxAttempts !== null ? max(0, $maxAttempts - $newWrongCount) : null;
        $playerDone         = ($foundTitle && $foundArtist) || $attemptsRemaining === 0;

        return [
            'correct'            => $isCorrect,
            'answer_type'        => $answerType?->value,
            'points_earned'      => $pointsEarned,
            'found_title'        => $foundTitle,
            'found_artist'       => $foundArtist,
            'attempts_remaining' => $attemptsRemaining,
            'correct_answer'     => $playerDone ? [
                'title'     => $trackTitle,
                'artist'    => $trackArtist,
                'cover_url' => $round->themeTrack?->cover_url,
            ] : null,
        ];
    }

    /**
     * Multi-level fuzzy matching between a player's answer and a reference string
     * (title or artist). Returns true on the first level that matches.
     */
    public static function isCorrectAnswer(string $answer, string $correct): bool
    {
        $normAnswer  = self::normalizeBase($answer);
        $normCorrect = self::normalizeBase($correct);

        if ($normAnswer === '' || $normCorrect === '') {
            return false;
        }

        $cleaned = self::cleanTitle($normCorrect);

        // Level 0 — containment in the original normalised string (no cleanup).
        if (str_contains($normCorrect, $normAnswer)) {
            return true;
        }

        // Level 1 — exact match after cleanup.
        if ($normAnswer === $cleaned) {
            return true;
        }

        if ($cleaned === '') {
            return false;
        }

        // Level 2 — answer is a substring of the cleaned title.
        if (str_contains($cleaned, $normAnswer)) {
            return true;
        }

        // Level 3 — cleaned title is a substring of the answer.
        if (str_contains($normAnswer, $cleaned)) {
            return true;
        }

        // Level 4 — acronym of the cleaned title equals the answer.
        $acronym = self::acronym($cleaned);
        if ($acronym !== '' && $normAnswer === $acronym) {
            return true;
        }

        // Level 5 — Levenshtein distance ≤ 2 (cleaned title must be ≥ 4 chars).
        if (mb_strlen($cleaned) >= 4 && levenshtein($normAnswer, $cleaned) <= 2) {
            return true;
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function normalizeBase(string $text): string
    {
        $text = mb_strtolower(trim($text));

        if (class_exists('Normalizer')) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_D);
            $text = preg_replace('/\p{Mn}/u', '', $text);
        } elseif (function_exists('iconv')) {
            $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        }

        $text = preg_replace('/[.,!?\'"`\-_]/u', '', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private static function cleanTitle(string $normalized): string
    {
        $text = preg_replace('/\(.*?\)/u', '', $normalized);
        $text = preg_replace('/\[.*?\]/u', '', $text);
        $text = preg_replace('/\s+(feat|ft|featuring)\s+.*/iu', '', $text);
        $text = preg_replace('/\b(single|remix|radio\s+edit|acoustic|live|version|remastered?)\b/iu', '', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private static function acronym(string $text): string
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return implode('', array_map(fn ($w) => mb_substr($w, 0, 1), $words));
    }
}
