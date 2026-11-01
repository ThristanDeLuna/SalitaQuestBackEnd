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

// ---------------------------------------------------------------- listening

// $heard words with audio (ids 1..$heard), then $silent words without audio.
// Silent words alternate between no audio_url key and an empty one.
function listening_words($heard, $silent = 0)
{
    $words = sample_words($heard + $silent);
    for ($i = $heard; $i < $heard + $silent; $i++) {
        if ($i % 2 === 0) {
            unset($words[$i]['audio_url']);
        } else {
            $words[$i]['audio_url'] = '';
        }
    }
    return $words;
}

// Check the rules every listening round must follow. Returns '' when fine, else what went wrong.
function listening_problem(array $round, array $words, $questions, $options)
{
    $by_id = [];
    foreach ($words as $w) {
        $by_id[(int) $w['id']] = $w;
    }

    if (array_keys($round) !== ['questions'])           return 'result keys: ' . json_encode(array_keys($round));
    if (count($round['questions']) !== $questions)       return 'questions: ' . count($round['questions']);
    if (array_keys($round['questions']) !== range(0, $questions - 1)) return 'questions is not a plain list';

    $ids = [];
    foreach ($round['questions'] as $q) {
        if (array_keys($q) !== ['word_id', 'audio_url', 'options']) return 'question keys: ' . json_encode(array_keys($q));
        if (!is_int($q['word_id']))                       return 'word_id is not an int';
        if (!isset($by_id[$q['word_id']]))                 return "word_id {$q['word_id']} is not from the input";
        $w = $by_id[$q['word_id']];
        $ids[] = $q['word_id'];

        $in_audio = isset($w['audio_url']) ? trim((string) $w['audio_url']) : '';
        if ($in_audio === '')                              return "word {$q['word_id']} has no audio but is a question";
        if ($q['audio_url'] !== $w['audio_url'])           return "audio_url of {$q['word_id']} does not match the input";

        if (count($q['options']) !== $options)            return 'options: ' . count($q['options']);
        if (array_keys($q['options']) !== range(0, $options - 1)) return 'options is not a plain list';
        foreach ($q['options'] as $o) {
            if (!is_string($o))                            return 'an option is not a string';
            if ($o === $w['word'])                         return 'the word text is an option';
        }

        $keys = array_map('key_of', $q['options']);
        if (count(array_unique($keys)) !== count($keys))   return 'duplicate options: ' . json_encode($q['options']);
        $right = key_of($w['translation']);
        $hits  = count(array_keys($keys, $right, true));
        if ($hits !== 1)                                   return "right meaning appears $hits times";
        if (!in_array((string) $w['translation'], $q['options'], true)) return "the word's own translation text is missing";

        // Every option must be a meaning of the region.
        $region_keys = array_map(function ($x) { return key_of($x['translation']); }, $words);
        foreach ($keys as $k) {
            if (!in_array($k, $region_keys, true))         return "option '$k' is not a meaning of the region";
        }
    }
    if (count(array_unique($ids)) !== count($ids))         return 'a word is asked twice';

    return '';
}

// Run one listening case RUNS times; return the first problem found, or ''.
function run_listening($builder, array $words, $questions = 5, $options = 4)
{
    for ($i = 0; $i < RUNS; $i++) {
        $problem = listening_problem($builder->listening($words, $questions, $options), $words, $questions, $options);
        if ($problem !== '') {
            return "run $i: $problem";
        }
    }
    return '';
}

echo "listening: counts and rules (each case " . RUNS . " runs)\n";
check('10 audio words: 5 questions, 4 options, all rules', run_listening($builder, listening_words(10)), '');
check('exactly 5 audio words + 5 silent: all rules',      run_listening($builder, listening_words(5, 5)), '');
check('5 audio words only (5 meanings): all rules',       run_listening($builder, listening_words(5)), '');
check('10 audio words, 3 questions, 6 options',           run_listening($builder, listening_words(10), 3, 6), '');

echo "listening: words without audio\n";
// 5 heard words (ids 1..5) and 15 silent ones (ids 6..20).
$mixed   = listening_words(5, 15);
$silent  = [];
foreach (array_slice($mixed, 5) as $w) {
    $silent[key_of($w['translation'])] = (int) $w['id'];
}
$silent_asked  = false;
$silent_option = false;
for ($i = 0; $i < RUNS; $i++) {
    foreach ($builder->listening($mixed)['questions'] as $q) {
        $silent_asked = $silent_asked || in_array($q['word_id'], $silent, true);
        foreach ($q['options'] as $o) {
            $silent_option = $silent_option || isset($silent[key_of($o)]);
        }
    }
}
check('a word without audio is never a question',        $silent_asked, false);
check('a word without audio may give a wrong option',     $silent_option, true);
check('mixed audio: all rules',                           run_listening($builder, $mixed), '');
// A space-only audio_url counts as no audio.
$spaces = listening_words(5, 0);
$spaces[] = ['id' => 6, 'word' => 'salita6', 'translation' => 'meaning 6', 'audio_url' => '   '];
$asked_6 = false;
for ($i = 0; $i < RUNS; $i++) {
    $asked_6 = $asked_6 || in_array(6, array_column($builder->listening($spaces)['questions'], 'word_id'), true);
}
check('a space-only audio_url is never a question',      $asked_6, false);

echo "listening: nothing leaks the answer\n";
$r = $builder->listening(listening_words(10));
check('result keys are exactly questions', array_keys($r), ['questions']);
$keys_ok = true;
foreach ($r['questions'] as $q) {
    $keys_ok = $keys_ok && array_keys($q) === ['word_id', 'audio_url', 'options'];
}
check('every question has exactly word_id, audio_url, options', $keys_ok, true);
check('options are plain strings', array_map('gettype', $r['questions'][0]['options']), ['string', 'string', 'string', 'string']);

echo "listening: it is random\n";
$words      = listening_words(10);
$right_at   = [];   // index of the right option => true
$id_orders  = [];
for ($i = 0; $i < RUNS; $i++) {
    $r = $builder->listening($words);
    $id_orders[json_encode(array_column($r['questions'], 'word_id'))] = true;
    foreach ($r['questions'] as $q) {
        $right_at[array_search($words[$q['word_id'] - 1]['translation'], $q['options'], true)] = true;
    }
}
check('the right option is not always at the same index', count($right_at) > 1, true);
check('the right option shows up at every index over ' . RUNS . ' runs', count($right_at), 4);
check('same input gives different questions over ' . RUNS . ' runs', count($id_orders) > 1, true);

echo "listening: not enough words\n";
$all_threw = true;
for ($i = 0; $i < RUNS; $i++) {
    // 4 heard words: 20 words in all, but only 4 with audio.
    $all_threw = $all_threw && throws_invalid(function () use ($builder) { $builder->listening(listening_words(4, 16)); });
}
check('fewer than 5 audio words: always throws', $all_threw, true);
check('0 words: throws', throws_invalid(function () use ($builder) { $builder->listening([]); }), true);

// 8 heard words but only 3 different meanings: love x3, water x3, fire x2.
$three_meanings = [];
foreach (['Love', 'love ', ' LOVE', 'water', 'Water', 'WATER ', 'fire', 'Fire'] as $i => $t) {
    $three_meanings[] = ['id' => $i + 1, 'word' => "w$i", 'translation' => $t, 'audio_url' => "https://example.test/$i.mp3"];
}
$all_threw = true;
for ($i = 0; $i < RUNS; $i++) {
    $all_threw = $all_threw && throws_invalid(function () use ($builder, $three_meanings) { $builder->listening($three_meanings); });
}
check('only 3 different meanings: always throws', $all_threw, true);

echo "listening: duplicate meanings (different letter case and spaces)\n";
// 9 words, 6 different meanings: love x3 (all with audio), water x2, fire, earth, sky, sun.
$dupes = [
    ['id' => 1, 'word' => 'mahal',  'translation' => 'Love',   'audio_url' => 'https://example.test/1.mp3'],
    ['id' => 2, 'word' => 'irog',   'translation' => 'love ',  'audio_url' => 'https://example.test/2.mp3'],
    ['id' => 3, 'word' => 'sinta',  'translation' => ' LOVE',  'audio_url' => 'https://example.test/3.mp3'],
    ['id' => 4, 'word' => 'tubig',  'translation' => 'water',  'audio_url' => 'https://example.test/4.mp3'],
    ['id' => 5, 'word' => 'danum',  'translation' => 'Water'],
    ['id' => 6, 'word' => 'apoy',   'translation' => 'fire',   'audio_url' => 'https://example.test/6.mp3'],
    ['id' => 7, 'word' => 'lupa',   'translation' => 'earth'],
    ['id' => 8, 'word' => 'langit', 'translation' => 'sky',    'audio_url' => ''],
    ['id' => 9, 'word' => 'araw',   'translation' => 'sun'],
];
check('two words with the same meaning are never both options', run_listening($builder, $dupes), '');

echo "\n$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
