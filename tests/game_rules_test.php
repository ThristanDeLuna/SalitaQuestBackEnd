<?php
/**
 * Plain-PHP tests for app/libraries/Game_rules.php. No framework, no database.
 * Run from the project root:   php tests/game_rules_test.php
 * Exits with code 1 if any test fails.
 */
define('PREVENT_DIRECT_ACCESS', true);
require __DIR__ . '/../app/libraries/Game_rules.php';

$rules  = new Game_rules();
$passed = 0;
$failed = 0;

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

echo "score_round (matching: 4 pairs)\n";
$key = [1 => 101, 2 => 102, 3 => 103, 4 => 104];
$r = $rules->score_round($key, [1 => 101, 2 => 102, 3 => 103, 4 => 104]);
check('all correct = 40/40, passed',       [$r['score'], $r['max_score'], $r['passed']], [40, 40, true]);
$r = $rules->score_round($key, [1 => 101, 2 => 102, 3 => 999, 4 => 104]);
check('3 of 4 = 30/40, passed',            [$r['score'], $r['passed']], [30, true]);
$r = $rules->score_round($key, [1 => 101, 2 => 102, 3 => 999, 4 => 998]);
check('2 of 4 = 20/40, failed (50%)',      [$r['score'], $r['passed']], [20, false]);
$r = $rules->score_round($key, []);
check('no answers = 0, failed',            [$r['score'], $r['correct'], $r['passed']], [0, 0, false]);
$r = $rules->score_round($key, [1 => '101', 2 => '102']);
check('string ids match int ids (JSON)',   $r['correct'], 2);
$r = $rules->score_round($key, [1 => 101, 2 => 102, 3 => 103, 4 => 104, 99 => 555]);
check('extra unknown item is ignored',     [$r['score'], $r['total']], [40, 4]);

echo "score_round (listening: 5 questions)\n";
$key = [10 => 1, 11 => 2, 12 => 3, 13 => 4, 14 => 1];
$r = $rules->score_round($key, [10 => 1, 11 => 2, 12 => 3, 13 => 4, 14 => 1]);
check('all correct = 50/50',               [$r['score'], $r['max_score']], [50, 50]);
$r = $rules->score_round($key, [10 => 1, 11 => 2, 12 => 3, 13 => 9, 14 => 9]);
check('3 of 5 = 30/50, passed (exactly 60%)', [$r['score'], $r['passed']], [30, true]);
$r = $rules->score_round($key, [10 => 1, 11 => 2, 12 => 9, 13 => 9, 14 => 9]);
check('2 of 5 = 20/50, failed',            [$r['score'], $r['passed']], [20, false]);
$r = $rules->score_round([], []);
check('empty key never passes',            [$r['max_score'], $r['passed']], [0, false]);

echo "is_pass (boundary)\n";
check('24/40 is exactly 60% -> pass',      $rules->is_pass(24, 40), true);
check('23/40 is below 60% -> fail',        $rules->is_pass(23, 40), false);
check('max 0 -> fail',                     $rules->is_pass(0, 0), false);

echo "earns_stamp\n";
$pass_m = ['game' => 'matching',  'score' => 40, 'max_score' => 40];
$fail_m = ['game' => 'matching',  'score' => 10, 'max_score' => 40];
$pass_l = ['game' => 'listening', 'score' => 50, 'max_score' => 50];
$fail_l = ['game' => 'listening', 'score' => 10, 'max_score' => 50];
check('both games passed -> stamp',        $rules->earns_stamp([$pass_m, $pass_l]), true);
check('only matching passed -> no stamp',  $rules->earns_stamp([$pass_m]), false);
check('only listening passed -> no stamp', $rules->earns_stamp([$pass_l]), false);
check('failed attempts do not count',      $rules->earns_stamp([$fail_m, $fail_l]), false);
check('fail then pass later -> stamp',     $rules->earns_stamp([$fail_m, $pass_m, $fail_l, $pass_l]), true);
check('same game twice is not both games', $rules->earns_stamp([$pass_m, $pass_m]), false);
check('no attempts -> no stamp',           $rules->earns_stamp([]), false);

echo "update_streak\n";
check('first ever day',                    $rules->update_streak(null, 0, 0, '2026-10-16'),
      ['current' => 1, 'longest' => 1, 'last_active_date' => '2026-10-16']);
check('same day again: unchanged',         $rules->update_streak('2026-10-16', 3, 5, '2026-10-16'),
      ['current' => 3, 'longest' => 5, 'last_active_date' => '2026-10-16']);
check('next day: +1',                      $rules->update_streak('2026-10-16', 3, 3, '2026-10-17'),
      ['current' => 4, 'longest' => 4, 'last_active_date' => '2026-10-17']);
check('next day below best: longest kept', $rules->update_streak('2026-10-16', 2, 9, '2026-10-17'),
      ['current' => 3, 'longest' => 9, 'last_active_date' => '2026-10-17']);
check('missed a day: reset to 1',          $rules->update_streak('2026-10-16', 7, 7, '2026-10-18'),
      ['current' => 1, 'longest' => 7, 'last_active_date' => '2026-10-18']);
check('month boundary counts as next day', $rules->update_streak('2026-10-31', 1, 1, '2026-11-01'),
      ['current' => 2, 'longest' => 2, 'last_active_date' => '2026-11-01']);
check('year boundary counts as next day',  $rules->update_streak('2026-12-31', 4, 4, '2027-01-01'),
      ['current' => 5, 'longest' => 5, 'last_active_date' => '2027-01-01']);
check('stored date in the future: no change', $rules->update_streak('2026-10-20', 2, 2, '2026-10-16'),
      ['current' => 2, 'longest' => 2, 'last_active_date' => '2026-10-20']);

echo "word_of_the_day\n";
$ids = [30, 10, 20];
$a = $rules->word_of_the_day($ids, '2026-10-16');
check('same date, same word',              $rules->word_of_the_day($ids, '2026-10-16'), $a);
check('order of ids does not matter',      $rules->word_of_the_day([20, 30, 10], '2026-10-16'), $a);
check('next day is the next word',         $rules->word_of_the_day($ids, '2026-10-17'), [10, 20, 30][(array_search($a, [10, 20, 30]) + 1) % 3]);
check('cycles back after 3 days',          $rules->word_of_the_day($ids, '2026-10-19'), $a);
check('no words -> null',                  $rules->word_of_the_day([], '2026-10-16'), null);
check('one word -> always that word',      $rules->word_of_the_day([7], '2026-10-16'), 7);

echo "medal (bronze = pass, silver = 80%, gold = full marks)\n";
check('40/40 = gold',               $rules->medal(40, 40), 'gold');
check('50/50 = gold',               $rules->medal(50, 50), 'gold');
check('30/40 (75%) = bronze',       $rules->medal(30, 40), 'bronze');
check('24/40 (60%) = bronze',       $rules->medal(24, 40), 'bronze');
check('20/40 (50%) = no medal',     $rules->medal(20, 40), null);
check('40/50 (80%) = silver',       $rules->medal(40, 50), 'silver');
check('30/50 (60%) = bronze',       $rules->medal(30, 50), 'bronze');
check('0/0 empty round = no medal', $rules->medal(0, 0), null);

echo "stars_for (best score per game per region, added up)\n";
check('no attempts = 0 stars', $rules->stars_for([]), 0);
$ana = [
    ['game' => 'matching',  'region_code' => 'tagalog', 'score' => 30],
    ['game' => 'listening', 'region_code' => 'tagalog', 'score' => 40],
    ['game' => 'matching',  'region_code' => 'cebuano', 'score' => 40],
];
check("Ana's sample = 110",    $rules->stars_for($ana), 110);
$replay = array_merge($ana, [
    ['game' => 'matching', 'region_code' => 'tagalog', 'score' => 20], // worse replay
    ['game' => 'matching', 'region_code' => 'tagalog', 'score' => 40], // better replay
    ['game' => 'matching', 'region_code' => 'tagalog', 'score' => 40], // same again
]);
check('only the best replay counts (+10)', $rules->stars_for($replay), 120);
check('failed attempt still counts as best', $rules->stars_for([
    ['game' => 'matching', 'region_code' => 'tagalog', 'score' => 10],
]), 10);
check('unknown game ignored', $rules->stars_for([
    ['game' => 'flashcards', 'region_code' => 'tagalog', 'score' => 99],
]), 0);
$full = [];
foreach (['tagalog', 'cebuano', 'hiligaynon'] as $reg) {
    $full[] = ['game' => 'matching',  'region_code' => $reg, 'score' => 40];
    $full[] = ['game' => 'listening', 'region_code' => $reg, 'score' => 50];
}
check('full marks everywhere = 270', $rules->stars_for($full), 270);

echo "region_ready and can_play_region (soft lock)\n";
check('4 words, 5 audio = ready',      $rules->region_ready(4, 5), true);
check('3 words = coming soon',         $rules->region_ready(3, 5), false);
check('4 audio only = coming soon',    $rules->region_ready(10, 4), false);

$ready = ['tagalog', 'cebuano', 'hiligaynon'];
check('new player: may start any one region', $rules->can_play_region('cebuano', [], 0, $ready), true);
check('0 stamps, Tagalog started: Cebuano locked', $rules->can_play_region('cebuano', ['tagalog'], 0, $ready), false);
check('0 stamps, Tagalog started: Tagalog still open', $rules->can_play_region('tagalog', ['tagalog'], 0, $ready), true);
check('1 stamp, Tagalog started: Cebuano opens', $rules->can_play_region('cebuano', ['tagalog'], 1, $ready), true);
check('1 stamp, two started: third locked', $rules->can_play_region('hiligaynon', ['tagalog', 'cebuano'], 1, $ready), false);
check('2 stamps, two started: third opens', $rules->can_play_region('hiligaynon', ['tagalog', 'cebuano'], 2, $ready), true);
check('coming-soon region never playable', $rules->can_play_region('hiligaynon', [], 3, ['tagalog', 'cebuano']), false);
check('coming-soon region does not use a slot', $rules->can_play_region('cebuano', ['hiligaynon'], 0, ['tagalog', 'cebuano']), true);
check('admin skips the lock', $rules->can_play_region('hiligaynon', ['tagalog', 'cebuano'], 0, $ready, true), true);
check('admin still blocked on coming soon', $rules->can_play_region('hiligaynon', [], 0, ['tagalog'], true), false);

echo "\n$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
