<?php

declare(strict_types=1);

/**
 * Mirrors the Alpine `fuzzy()` helper in resources/views/livewire/partials/script.blade.php.
 * Keep the two in step: the browser is what ranks commands.
 *
 * @return array{score: int, positions: array<int, int>}|null
 */
function paletteFuzzy(string $needle, ?string $haystack): ?array
{
    if ($needle === '') {
        return ['score' => 0, 'positions' => []];
    }

    if ($haystack === null || $haystack === '') {
        return null;
    }

    $hay = mb_strtolower($haystack);
    $needle = mb_strtolower($needle);
    $exact = mb_strpos($hay, $needle);

    if ($exact !== false) {
        $positions = [];
        $length = mb_strlen($needle);
        for ($i = 0; $i < $length; $i++) {
            $positions[] = $exact + $i;
        }

        $score = 120 - $exact;
        if ($exact === 0) {
            $score += 60;
        } elseif ($exact > 0 && preg_match('/[\s\-_\/:.,()\[\]]/u', mb_substr($hay, $exact - 1, 1))) {
            $score += 35;
        }

        return ['score' => $score, 'positions' => $positions];
    }

    $positions = [];
    $score = 0;
    $hayIndex = 0;
    $run = 0;
    $length = mb_strlen($needle);

    for ($i = 0; $i < $length; $i++) {
        $found = mb_strpos($hay, mb_substr($needle, $i, 1), $hayIndex);
        if ($found === false) {
            return null;
        }

        $points = 8;
        if ($found === 0) {
            $points += 25;
        } elseif ($found > 0 && preg_match('/[\s\-_\/:.,()\[\]]/u', mb_substr($hay, $found - 1, 1))) {
            $points += 18;
        }

        if ($found === $hayIndex && $i > 0) {
            $run += 1;
            $points += $run * 6;
        } else {
            $run = 0;
            $points -= min($found - $hayIndex, 8);
        }

        $score += $points;
        $positions[] = $found;
        $hayIndex = $found + 1;
    }

    return ['score' => max($score, 1), 'positions' => $positions];
}

dataset('palette fuzzy cases', [
    'empty needle matches everything' => ['', 'Create User', true],
    'exact prefix' => ['create', 'Create User', true],
    'typo subsequence' => ['crus', 'Create User', true],
    'unrelated term misses' => ['zzzz', 'Create User', false],
    'empty haystack misses a term' => ['user', '', false],
    'non-latin label' => ['فات', 'فاتورة العميل', true],
    'japanese subsequence' => ['請求', '請求書を発行', true],
]);

test('fuzzy matching covers typos empty input and non-latin labels', function (string $needle, string $haystack, bool $matches) {
    $result = paletteFuzzy($needle, $haystack === '' ? '' : $haystack);

    if ($matches) {
        expect($result)->not->toBeNull();
    } else {
        expect($result)->toBeNull();
    }
})->with('palette fuzzy cases');
