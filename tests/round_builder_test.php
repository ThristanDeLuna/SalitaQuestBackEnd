<?php
/**
 * Plain-PHP tests for app/libraries/Round_builder.php. No framework, no database.
 * Run from the project root:   php tests/round_builder_test.php
 * Exits with code 1 if any test fails.
 *
 * The rounds are random, so each case runs RUNS times and checks rules that
 * must always hold (counts, no repeats, no leaks), never exact values.
 */
define('PREVENT_DIRECT_ACCESS', true);
require __DIR__ . '/../app/libraries/Round_builder.php';

const RUNS = 100;

$builder = new Round_builder();
$passed  = 0;
$failed  = 0;

function check($name, $actual, $expected)
{
    global $passed, $failed;
    if ($actual === $expected) {
        $passed++;
        echo "  ok   $name\n";
    } else {
        $failed++;
        echo "  FAIL $name\n       expected: " . json_encode($expected) . "\n       actual:   " . json_encode($actual) . "\n";
    }
}

// Does calling $fn throw InvalidArgumentException?
function throws_invalid($fn)
{
    try {
        $fn();
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
}

// $n sample words: id 1..n, word "salita1", meaning "meaning 1", every one with audio.
function sample_words($n)
{
    $words = [];
    for ($i = 1; $i <= $n; $i++) {
        $words[] = ['id' => $i, 'word' => "salita$i", 'translation' => "meaning $i", 'audio_url' => "https://example.test/$i.mp3"];
    }
    return $words;
}

// Same comparison as the library: no outer spaces, lower case.
function key_of($text)
{
    return mb_strtolower(trim($text), 'UTF-8');
}

// Check the rules every round must follow. Returns '' when fine, else what went wrong.
// $words is the input, so we can look up each fish's real translation here.
function round_problem(array $round, array $words, $pairs, $fish_count)
{
    $by_id = [];
    foreach ($words as $w) {
        $by_id[(int) $w['id']] = $w;
    }

    if (count($round['meanings']) !== $pairs)     return 'meanings: ' . count($round['meanings']);
    if (count($round['words']) !== $fish_count)   return 'fish: ' . count($round['words']);

    $ids = array_column($round['words'], 'word_id');
    if (count(array_unique($ids)) !== count($ids)) return 'duplicate word_id';

    $meaning_keys = array_map('key_of', $round['meanings']);
    if (count(array_unique($meaning_keys)) !== count($meaning_keys)) return 'duplicate meaning';

    // A fish is a target when its translation is on screen. Exactly $pairs fish
    // must be targets, and together they must cover every meaning once.
    $target_keys = [];
    foreach ($round['words'] as $f) {
        $k = key_of($by_id[$f['word_id']]['translation']);
        if (in_array($k, $meaning_keys, true)) {
            $target_keys[] = $k;
        }
    }
    sort($target_keys);
    $sorted_meanings = $meaning_keys;
    sort($sorted_meanings);
    if ($target_keys !== $sorted_meanings) return 'targets and meanings do not line up (or a decoy shares a meaning)';

    return '';
}

// Run one case RUNS times; return the first problem found, or ''.
function run_case($builder, array $words, $pairs, $fish_count, $max_decoys = 2)
{
    for ($i = 0; $i < RUNS; $i++) {
        $problem = round_problem($builder->matching($words, $pairs, $max_decoys), $words, $pairs, $fish_count);
        if ($problem !== '') {
            return "run $i: $problem";
        }
    }
    return '';
}

echo "matching: counts (each case " . RUNS . " runs)\n";
check('10 words: 4 meanings, 6 fish, no repeats', run_case($builder, sample_words(10), 4, 6), '');
check('6 words: 6 fish (4 targets + 2 decoys)',  run_case($builder, sample_words(6), 4, 6), '');
check('5 words: 5 fish (1 decoy)',               run_case($builder, sample_words(5), 4, 5), '');
check('4 words: 4 fish (no decoys)',             run_case($builder, sample_words(4), 4, 4), '');
$all_threw = true;
for ($i = 0; $i < RUNS; $i++) {
    $all_threw = $all_threw && throws_invalid(function () use ($builder) { $builder->matching(sample_words(3)); });
}
check('3 words: always throws InvalidArgumentException', $all_threw, true);
check('0 words: throws', throws_invalid(function () use ($builder) { $builder->matching([]); }), true);
check('10 words, 3 pairs, 0 decoys: 3 fish',     run_case($builder, sample_words(10), 3, 3, 0), '');
check('10 words, 4 pairs, 5 decoys: 9 fish',     run_case($builder, sample_words(10), 4, 9, 5), '');

echo "matching: duplicate meanings (different letter case and spaces)\n";
// 7 words, 5 different meanings: love x3, water, fire, earth, sky
$dupes = [
    ['id' => 1, 'word' => 'mahal',  'translation' => 'Love'],
    ['id' => 2, 'word' => 'irog',   'translation' => 'love '],
    ['id' => 3, 'word' => 'sinta',  'translation' => ' LOVE'],
    ['id' => 4, 'word' => 'tubig',  'translation' => 'water'],
    ['id' => 5, 'word' => 'apoy',   'translation' => 'fire'],
    ['id' => 6, 'word' => 'lupa',   'translation' => 'earth'],
    ['id' => 7, 'word' => 'langit', 'translation' => 'sky'],
];
$ok = '';
for ($i = 0; $i < RUNS && $ok === ''; $i++) {
    $r = $builder->matching($dupes);
    // Fish count is not fixed here: it depends on whether "love" is a target.
    $ok = round_problem($r, $dupes, 4, count($r['words']));
    if ($ok === '' && (count($r['words']) < 4 || count($r['words']) > 6)) $ok = 'fish: ' . count($r['words']);
}
check('never two targets with the same meaning; no decoy shares one', $ok, '');

// Only 3 different meanings among 6 words: love, Love, water, WATER, fire, fire
$three = [
    ['id' => 1, 'word' => 'a', 'translation' => 'love'],
    ['id' => 2, 'word' => 'b', 'translation' => 'Love'],
    ['id' => 3, 'word' => 'c', 'translation' => 'water'],
    ['id' => 4, 'word' => 'd', 'translation' => 'WATER'],
    ['id' => 5, 'word' => 'e', 'translation' => 'fire'],
    ['id' => 6, 'word' => 'f', 'translation' => 'fire'],
];
$all_threw = true;
for ($i = 0; $i < RUNS; $i++) {
    $all_threw = $all_threw && throws_invalid(function () use ($builder, $three) { $builder->matching($three); });
}
check('only 3 different meanings: always throws', $all_threw, true);

// 5 words, 4 meanings: the word left over always shares a target's meaning,
// so it can never be a decoy. The round has 4 fish, not 5.
$no_safe_decoy = [
    ['id' => 1, 'word' => 'mahal', 'translation' => 'love'],
    ['id' => 2, 'word' => 'irog',  'translation' => 'Love'],
    ['id' => 3, 'word' => 'tubig', 'translation' => 'water'],
    ['id' => 4, 'word' => 'apoy',  'translation' => 'fire'],
    ['id' => 5, 'word' => 'lupa',  'translation' => 'earth'],
];
check('no safe decoy left: 4 fish, rules still hold', run_case($builder, $no_safe_decoy, 4, 4), '');

echo "matching: nothing leaks the answer\n";
$r = $builder->matching(sample_words(10));
check('result keys are exactly words, meanings', array_keys($r), ['words', 'meanings']);
$fish_keys_ok = true;
foreach ($r['words'] as $f) {
    $fish_keys_ok = $fish_keys_ok && array_keys($f) === ['word_id', 'word', 'audio_url'];
}
check('every fish has exactly word_id, word, audio_url', $fish_keys_ok, true);
check('meanings is a plain list (keys 0..3)', array_keys($r['meanings']), [0, 1, 2, 3]);
check('meanings are plain strings', array_map('gettype', $r['meanings']), ['string', 'string', 'string', 'string']);

echo "matching: field values\n";
$mixed = sample_words(6);
unset($mixed[0]['audio_url'], $mixed[1]['audio_url']);  // ids 1 and 2: no audio
$mixed[2]['audio_url'] = '';                             // id 3: empty audio
$mixed[3]['id'] = '4';                                   // id as a string, like a database row
$audio_ok = true;
for ($i = 0; $i < RUNS; $i++) {
    foreach ($builder->matching($mixed)['words'] as $f) {
        $want = in_array($f['word_id'], [1, 2, 3], true) ? null : "https://example.test/{$f['word_id']}.mp3";
        $audio_ok = $audio_ok && $f['audio_url'] === $want && is_int($f['word_id']);
    }
}
check('no audio_url (or empty) gives null; word_id is always an int', $audio_ok, true);

echo "matching: it is random\n";
$fish_orders    = [];
$meaning_orders = [];
for ($i = 0; $i < RUNS; $i++) {
    $r = $builder->matching(sample_words(10));
    $fish_orders[json_encode(array_column($r['words'], 'word_id'))] = true;
    $meaning_orders[json_encode($r['meanings'])] = true;
}
check('same input gives different fish over ' . RUNS . ' runs',     count($fish_orders) > 1, true);
check('same input gives different meanings over ' . RUNS . ' runs', count($meaning_orders) > 1, true);

// Decoys must be mixed in, not always last, or their place would give them away.
$words       = sample_words(10);
$decoy_early = false;
for ($i = 0; $i < RUNS && !$decoy_early; $i++) {
    $r = $builder->matching($words);
    foreach (array_slice($r['words'], 0, 4) as $f) {
        if (!in_array($words[$f['word_id'] - 1]['translation'], $r['meanings'], true)) {
            $decoy_early = true; // a decoy sits among the first 4 fish
        }
    }
}
check('decoys are not always the last fish', $decoy_early, true);

echo "\n$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
