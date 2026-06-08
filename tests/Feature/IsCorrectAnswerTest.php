<?php

use App\Actions\EvaluateAnswerAction;

// Shorthand
function isCorrect(string $answer, string $correct): bool
{
    return EvaluateAnswerAction::isCorrectAnswer($answer, $correct);
}

// ---------------------------------------------------------------------------
// Cas exacts (level 0 / 1)
// ---------------------------------------------------------------------------

test('exact match — même casse', function () {
    expect(isCorrect('Nirvana', 'Nirvana'))->toBeTrue();
});

test('exact match — casse différente', function () {
    expect(isCorrect('nirvana', 'Nirvana'))->toBeTrue();
});

test('exact match — accents ignorés (réponse sans accent)', function () {
    expect(isCorrect('beyonce', 'Beyoncé'))->toBeTrue();
});

test('exact match — accents ignorés (titre sans accent)', function () {
    expect(isCorrect('Beyoncé', 'Beyonce'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Nettoyage du titre (parenthèses, feat, suffixes)
// ---------------------------------------------------------------------------

test('supprime le suffixe "- Single"', function () {
    expect(isCorrect('lose yourself', 'Lose Yourself - Single'))->toBeTrue();
});

test('supprime le contenu entre parenthèses (Remastered)', function () {
    expect(isCorrect('smells like teen spirit', 'Smells Like Teen Spirit (Remastered)'))->toBeTrue();
});

test('supprime feat. + artiste', function () {
    expect(isCorrect('nirvana', 'Nirvana feat. Dave Grohl'))->toBeTrue();
});

test('supprime ft. + artiste', function () {
    expect(isCorrect('despacito', 'Despacito ft. Justin Bieber'))->toBeTrue();
});

test('supprime featuring + artiste', function () {
    expect(isCorrect('despacito', 'Despacito featuring Justin Bieber'))->toBeTrue();
});

test('supprime suffixe remix', function () {
    expect(isCorrect('despacito', 'Despacito (Remix)'))->toBeTrue();
});

test('supprime suffixe live', function () {
    expect(isCorrect('bohemian rhapsody', 'Bohemian Rhapsody (Live)'))->toBeTrue();
});

test('supprime suffixe radio edit', function () {
    expect(isCorrect('one more time', 'One More Time (Radio Edit)'))->toBeTrue();
});

test('supprime suffixe acoustic', function () {
    expect(isCorrect('let her go', 'Let Her Go (Acoustic)'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Level 0 — titre original normalisé (sans nettoyage)
// ---------------------------------------------------------------------------

test('level 0 — "by my side" matche "B.M.S (By My Side)" via contenu non nettoyé', function () {
    expect(isCorrect('by my side', 'B.M.S (By My Side)'))->toBeTrue();
});

test('level 0 — contenu des parenthèses matche via titre non nettoyé', function () {
    expect(isCorrect('remastered', 'Smells Like Teen Spirit (Remastered)'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Acronyme (level 4)
// ---------------------------------------------------------------------------

test('acronyme — "bms" matche "B.M.S (By My Side)"', function () {
    expect(isCorrect('bms', 'B.M.S (By My Side)'))->toBeTrue();
});

test('acronyme — "slts" matche "Smells Like Teen Spirit"', function () {
    expect(isCorrect('slts', 'Smells Like Teen Spirit'))->toBeTrue();
});

test('acronyme — "acdc" matche "AC/DC"', function () {
    // normalizeBase supprime /, acdc → single word, acronym = a  — ne doit PAS matcher
    // mais level 0 : str_contains("acdc", "acdc") = true → match
    expect(isCorrect('acdc', 'AC/DC'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Levenshtein (level 5)
// ---------------------------------------------------------------------------

test('levenshtein — "eminiem" matche "Eminem" (distance 2)', function () {
    expect(isCorrect('eminiem', 'Eminem'))->toBeTrue();
});

test('levenshtein — "lose yourslef" matche "Lose Yourself" (distance 2)', function () {
    expect(isCorrect('lose yourslef', 'Lose Yourself'))->toBeTrue();
});

test('levenshtein — distance 3 ne matche pas', function () {
    expect(isCorrect('emineem', 'Nirvana'))->toBeFalse();
});

test('levenshtein — titre court (< 4 chars) ne déclenche pas le niveau 5', function () {
    // "ace" vs "ice" = distance 1, mais len("ice") = 3 < 4 → pas de levenshtein
    expect(isCorrect('ace', 'ice'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Artiste (même logique que le titre)
// ---------------------------------------------------------------------------

test('artiste exact — "eminem" matche "Eminem"', function () {
    expect(isCorrect('eminem', 'Eminem'))->toBeTrue();
});

test('artiste — accent ignoré', function () {
    expect(isCorrect('deja vu', 'Déjà Vu'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Cas négatifs — ne doivent PAS matcher
// ---------------------------------------------------------------------------

test('réponse aléatoire ne matche pas', function () {
    expect(isCorrect('random', 'Eminem'))->toBeFalse();
});

test('réponse vide ne matche pas', function () {
    expect(isCorrect('', 'Eminem'))->toBeFalse();
});

test('titre vide ne matche pas', function () {
    expect(isCorrect('eminem', ''))->toBeFalse();
});

test('réponse complètement différente ne matche pas', function () {
    expect(isCorrect('bohemian rhapsody', 'Lose Yourself'))->toBeFalse();
});

test('levenshtein distance > 2 ne matche pas sur un titre long', function () {
    expect(isCorrect('lose yourrself now', 'Lose Yourself'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Cas de la spec
// ---------------------------------------------------------------------------

test('"bms" → "B.M.S (By My Side)" ✓', function () {
    expect(isCorrect('bms', 'B.M.S (By My Side)'))->toBeTrue();
});

test('"by my side" → "B.M.S (By My Side)" ✓', function () {
    expect(isCorrect('by my side', 'B.M.S (By My Side)'))->toBeTrue();
});

test('"eminem" → "Eminem" ✓', function () {
    expect(isCorrect('eminem', 'Eminem'))->toBeTrue();
});

test('"eminiem" → "Eminem" ✓ (levenshtein)', function () {
    expect(isCorrect('eminiem', 'Eminem'))->toBeTrue();
});

test('"lose yourself" → "Lose Yourself - Single" ✓', function () {
    expect(isCorrect('lose yourself', 'Lose Yourself - Single'))->toBeTrue();
});

test('"lose yourslef" → "Lose Yourself" ✓ (levenshtein)', function () {
    expect(isCorrect('lose yourslef', 'Lose Yourself'))->toBeTrue();
});

test('"nirvana" → "Nirvana" ✓', function () {
    expect(isCorrect('nirvana', 'Nirvana'))->toBeTrue();
});

test('"smells like teen spirit" → "Smells Like Teen Spirit (Remastered)" ✓', function () {
    expect(isCorrect('smells like teen spirit', 'Smells Like Teen Spirit (Remastered)'))->toBeTrue();
});

test('"random" → "Eminem" ✗', function () {
    expect(isCorrect('random', 'Eminem'))->toBeFalse();
});
