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

echo "badges_for\n";
check('nothing yet = no badges', $rules->badges_for([], 0, 0), []);
check('one stamp = First Stamp', $rules->badges_for([], 1, 0), ['first_stamp']);
check('longest streak 3 = 3-day Sailor', $rules->badges_for([], 0, 3), ['sailor_3']);
check('longest streak 2 = no Sailor',    $rules->badges_for([], 0, 2), []);
check('full marks = Gold Catcher', $rules->badges_for([
    ['game' => 'matching', 'region_code' => 'tagalog', 'score' => 40, 'max_score' => 40],
], 0, 0), ['gold_catcher']);
check('30/40 is not gold', $rules->badges_for([
    ['game' => 'matching', 'region_code' => 'tagalog', 'score' => 30, 'max_score' => 40],
], 0, 0), []);
check('passed listening = Sharp Ears', $rules->badges_for([
    ['game' => 'listening', 'region_code' => 'tagalog', 'score' => 30, 'max_score' => 50],
], 0, 0), ['sharp_ears']);
check('failed listening = no Sharp Ears', $rules->badges_for([
    ['game' => 'listening', 'region_code' => 'tagalog', 'score' => 20, 'max_score' => 50],
], 0, 0), []);
check('passed matching alone is not Sharp Ears', $rules->badges_for([
    ['game' => 'matching', 'region_code' => 'tagalog', 'score' => 30, 'max_score' => 40],
], 0, 0), []);
check('3 regions played = Island Hopper', $rules->badges_for([
    ['game' => 'matching', 'region_code' => 'tagalog',    'score' => 10, 'max_score' => 40],
    ['game' => 'matching', 'region_code' => 'cebuano',    'score' => 10, 'max_score' => 40],
    ['game' => 'matching', 'region_code' => 'hiligaynon', 'score' => 10, 'max_score' => 40],
], 0, 0), ['island_hopper']);
check('2 regions only = no Island Hopper', $rules->badges_for([
    ['game' => 'matching',  'region_code' => 'tagalog', 'score' => 10, 'max_score' => 40],
    ['game' => 'listening', 'region_code' => 'cebuano', 'score' => 10, 'max_score' => 50],
], 0, 0), []);
check('everything earned, fixed order', $rules->badges_for([
    ['game' => 'matching',  'region_code' => 'tagalog',    'score' => 40, 'max_score' => 40],
    ['game' => 'listening', 'region_code' => 'cebuano',    'score' => 50, 'max_score' => 50],
    ['game' => 'matching',  'region_code' => 'hiligaynon', 'score' => 10, 'max_score' => 40],
], 3, 5), ['first_stamp', 'sailor_3', 'gold_catcher', 'sharp_ears', 'island_hopper']);

echo "quests_for (today's attempts)\n";
check('no attempts today: no quest done', $rules->quests_for([]),
      ['play_one' => false, 'pass_one' => false, 'both_games' => false]);
check('one failed matching: play only', $rules->quests_for([
    ['game' => 'matching', 'score' => 10, 'max_score' => 40],
]), ['play_one' => true, 'pass_one' => false, 'both_games' => false]);
check('one passed matching: play and pass', $rules->quests_for([
    ['game' => 'matching', 'score' => 30, 'max_score' => 40],
]), ['play_one' => true, 'pass_one' => true, 'both_games' => false]);
check('both games, both failed: play and both', $rules->quests_for([
    ['game' => 'matching',  'score' => 10, 'max_score' => 40],
    ['game' => 'listening', 'score' => 10, 'max_score' => 50],
]), ['play_one' => true, 'pass_one' => false, 'both_games' => true]);
check('both games, one passed: all three', $rules->quests_for([
    ['game' => 'matching',  'score' => 10, 'max_score' => 40],
    ['game' => 'listening', 'score' => 40, 'max_score' => 50],
]), ['play_one' => true, 'pass_one' => true, 'both_games' => true]);
check('two matching rounds is not both games', $rules->quests_for([
    ['game' => 'matching', 'score' => 40, 'max_score' => 40],
    ['game' => 'matching', 'score' => 40, 'max_score' => 40],
]), ['play_one' => true, 'pass_one' => true, 'both_games' => false]);
check('unknown game ignored', $rules->quests_for([
    ['game' => 'flashcards', 'score' => 99, 'max_score' => 99],
]), ['play_one' => false, 'pass_one' => false, 'both_games' => false]);

echo "BADGE_IDS (the fixed list and order)\n";
$all = $rules->badges_for([
    ['game' => 'matching',  'region_code' => 'tagalog',    'score' => 40, 'max_score' => 40],
    ['game' => 'listening', 'region_code' => 'cebuano',    'score' => 50, 'max_score' => 50],
    ['game' => 'matching',  'region_code' => 'hiligaynon', 'score' => 10, 'max_score' => 40],
], 3, 5);
check('every earned badge is in BADGE_IDS', array_values(array_diff($all, Game_rules::BADGE_IDS)), []);
check('everything earned = BADGE_IDS in the same order', $all, Game_rules::BADGE_IDS);
$some = $rules->badges_for([['game' => 'listening', 'region_code' => 'tagalog', 'score' => 50, 'max_score' => 50]], 1, 0);
check('a subset keeps the BADGE_IDS order', $some, array_values(array_intersect(Game_rules::BADGE_IDS, $some)));
check('that subset is first_stamp, gold_catcher, sharp_ears', $some, ['first_stamp', 'gold_catcher', 'sharp_ears']);

echo "streak_for_display (a read never writes)\n";
check('never played (null) = 0',            $rules->streak_for_display(null, 0, '2026-10-16'), 0);
check('never played (empty) = 0',           $rules->streak_for_display('', 5, '2026-10-16'), 0);
check('played today = stored streak',       $rules->streak_for_display('2026-10-16', 4, '2026-10-16'), 4);
check('played yesterday = stored streak',   $rules->streak_for_display('2026-10-15', 4, '2026-10-16'), 4);
check('two days ago = 0 (lapsed)',          $rules->streak_for_display('2026-10-14', 4, '2026-10-16'), 0);
check('a week ago = 0 (lapsed)',            $rules->streak_for_display('2026-10-09', 9, '2026-10-16'), 0);
check('stored date in the future = stored', $rules->streak_for_display('2026-10-20', 2, '2026-10-16'), 2);
check('month boundary: yesterday',          $rules->streak_for_display('2026-10-31', 3, '2026-11-01'), 3);
check('year boundary: yesterday',           $rules->streak_for_display('2026-12-31', 6, '2027-01-01'), 6);
check('string streak is returned as int',   $rules->streak_for_display('2026-10-16', '7', '2026-10-16'), 7);

echo "best_scores (best attempt per region and game, with its medal)\n";
check('no attempts = []', $rules->best_scores([]), []);
check('one region, matching only: listening is null', $rules->best_scores([
    ['game' => 'matching', 'region_code' => 'tagalog', 'score' => 30, 'max_score' => 40],
]), ['tagalog' => ['matching' => ['best' => 30, 'max' => 40, 'medal' => 'bronze'], 'listening' => null]]);
check('replays keep the highest, not the first or the latest', $rules->best_scores([
    ['game' => 'matching', 'region_code' => 'tagalog', 'score' => 20, 'max_score' => 40],
    ['game' => 'matching', 'region_code' => 'tagalog', 'score' => 40, 'max_score' => 40],
    ['game' => 'matching', 'region_code' => 'tagalog', 'score' => 30, 'max_score' => 40],
])['tagalog']['matching'], ['best' => 40, 'max' => 40, 'medal' => 'gold']);
check('a lower later score does not replace the best', $rules->best_scores([
    ['game' => 'listening', 'region_code' => 'tagalog', 'score' => 40, 'max_score' => 50],
    ['game' => 'listening', 'region_code' => 'tagalog', 'score' => 10, 'max_score' => 50],
])['tagalog']['listening'], ['best' => 40, 'max' => 50, 'medal' => 'silver']);
check('a tie keeps the first one seen', $rules->best_scores([
    ['game' => 'listening', 'region_code' => 'tagalog', 'score' => 30, 'max_score' => 50],
    ['game' => 'listening', 'region_code' => 'tagalog', 'score' => 30, 'max_score' => 40],
])['tagalog']['listening'], ['best' => 30, 'max' => 50, 'medal' => 'bronze']);
$medals = [];
foreach ([[50, 'gold'], [40, 'silver'], [30, 'bronze'], [20, null]] as [$score, $want]) {
    $b = $rules->best_scores([['game' => 'listening', 'region_code' => 'x', 'score' => $score, 'max_score' => 50]]);
    $medals[] = $b['x']['listening']['medal'] === $want;
}
check('medal of the best: gold 50/50, silver 40/50, bronze 30/50, null 20/50', $medals, [true, true, true, true]);
check('two regions kept separate', $rules->best_scores([
    ['game' => 'matching',  'region_code' => 'tagalog', 'score' => 40, 'max_score' => 40],
    ['game' => 'listening', 'region_code' => 'cebuano', 'score' => 30, 'max_score' => 50],
    ['game' => 'matching',  'region_code' => 'cebuano', 'score' => 10, 'max_score' => 40],
]), [
    'tagalog' => ['matching' => ['best' => 40, 'max' => 40, 'medal' => 'gold'], 'listening' => null],
    'cebuano' => ['matching' => ['best' => 10, 'max' => 40, 'medal' => null],
                  'listening' => ['best' => 30, 'max' => 50, 'medal' => 'bronze']],
]);
check('unknown game ignored (no region appears for it)', $rules->best_scores([
    ['game' => 'flashcards', 'region_code' => 'tagalog', 'score' => 99, 'max_score' => 99],
    ['game' => 'matching',   'region_code' => 'cebuano', 'score' => 40, 'max_score' => 40],
]), ['cebuano' => ['matching' => ['best' => 40, 'max' => 40, 'medal' => 'gold'], 'listening' => null]]);

echo "region_status (all 16 combinations)\n";
// [has_stamp, is_ready, started, can_play] => expected
$table = [
    [false, false, false, false, 'coming_soon'], [false, false, false, true, 'coming_soon'],
    [false, false, true,  false, 'coming_soon'], [false, false, true,  true, 'coming_soon'],
    [false, true,  false, false, 'locked'],      [false, true,  false, true, 'not_started'],
    [false, true,  true,  false, 'in_progress'], [false, true,  true,  true, 'in_progress'],
    [true,  false, false, false, 'stamped'],     [true,  false, false, true, 'stamped'],
    [true,  false, true,  false, 'stamped'],     [true,  false, true,  true, 'stamped'],
    [true,  true,  false, false, 'stamped'],     [true,  true,  false, true, 'stamped'],
    [true,  true,  true,  false, 'stamped'],     [true,  true,  true,  true, 'stamped'],
];
$got = []; $want = [];
foreach ($table as [$s, $r, $st, $c, $expected]) {
    $got[]  = $rules->region_status($s, $r, $st, $c);
    $want[] = $expected;
}
check('16 rows: stamped > coming_soon > in_progress > not_started > locked', $got, $want);
check('the table has 16 different inputs', count(array_unique(array_map(fn($row) => json_encode(array_slice($row, 0, 4)), $table))), 16);

echo "active_days_in_week (Manila dates, Monday to Sunday)\n";
// 2026-10-26 is a Monday, 2026-11-01 a Sunday
$dates = fn($week) => array_column($week, 'date');
$on    = fn($week) => array_column(array_filter($week, fn($d) => $d['active']), 'date');
$week  = $rules->active_days_in_week([], '2026-10-28');
check('no attempts: 7 days, none active', [count($week), $on($week)], [7, []]);
check('items are date, day, active; letters M T W T F S S',
      [array_keys($week[0]), array_column($week, 'day')], [['date', 'day', 'active'], ['M', 'T', 'W', 'T', 'F', 'S', 'S']]);
$oct_week = ['2026-10-26', '2026-10-27', '2026-10-28', '2026-10-29', '2026-10-30', '2026-10-31', '2026-11-01'];
check('today is Monday: the week starts today',    $dates($rules->active_days_in_week([], '2026-10-26')), $oct_week);
check('today is Sunday: the week ends today',      $dates($rules->active_days_in_week([], '2026-11-01')), $oct_week);
check('today mid-week (Wednesday): same week',     $dates($rules->active_days_in_week([], '2026-10-28')), $oct_week);
check('this week crosses Oct -> Nov',              [$dates($rules->active_days_in_week([], '2026-10-29'))[5], $dates($rules->active_days_in_week([], '2026-10-29'))[6]], ['2026-10-31', '2026-11-01']);
check('a week crossing a year boundary',           $dates($rules->active_days_in_week([], '2027-01-01')),
      ['2026-12-28', '2026-12-29', '2026-12-30', '2026-12-31', '2027-01-01', '2027-01-02', '2027-01-03']);
check('2026-10-25 16:30 UTC is Monday 00:30 in Manila', $on($rules->active_days_in_week([['created_at' => '2026-10-25 16:30:00']], '2026-10-28')), ['2026-10-26']);
check('2026-10-26 15:59:59 UTC is still Monday in Manila', $on($rules->active_days_in_week([['created_at' => '2026-10-26 15:59:59']], '2026-10-28')), ['2026-10-26']);
check('2026-10-26 16:00:00 UTC is Tuesday in Manila',    $on($rules->active_days_in_week([['created_at' => '2026-10-26 16:00:00']], '2026-10-28')), ['2026-10-27']);
check('attempts outside the week are ignored', $on($rules->active_days_in_week([
    ['created_at' => '2026-10-25 15:59:59'],   // Sunday 23:59:59 Manila, the week before
    ['created_at' => '2026-11-01 16:00:00'],   // Monday 00:00 Manila, the week after
    ['created_at' => '2025-10-28 03:00:00'],   // same weekday, a year earlier
], '2026-10-28')), []);
check('several attempts on one day = one active day', $on($rules->active_days_in_week([
    ['created_at' => '2026-10-28 01:00:00'], ['created_at' => '2026-10-28 05:00:00'], ['created_at' => '2026-10-28 09:00:00'],
], '2026-10-28')), ['2026-10-28']);
check('Monday, Wednesday and Sunday active', $on($rules->active_days_in_week([
    ['created_at' => '2026-10-26 02:00:00'], ['created_at' => '2026-10-28 02:00:00'], ['created_at' => '2026-11-01 02:00:00'],
], '2026-10-30')), ['2026-10-26', '2026-10-28', '2026-11-01']);
check('bad created_at rows are skipped, never an error', $on($rules->active_days_in_week([
    ['created_at' => 'yesterday'], ['created_at' => '2026-10-28'], ['created_at' => '2026-02-30 10:00:00'],
    ['created_at' => '2026-10-27 25:00:00'],   // not a real time (would roll over to Wednesday)
    ['created_at' => null], ['created_at' => 12345], [], ['created_at' => '2026-10-29 02:00:00'],
], '2026-10-28')), ['2026-10-29']);
$bad_today = [];
foreach (['', '2026-10-32', '2026-02-30', '28-10-2026', '2026-10-28 00:00:00', 'today', null] as $t) {
    try { $rules->active_days_in_week([], $t); $bad_today[] = false; }
    catch (InvalidArgumentException $e) { $bad_today[] = true; }
}
check('a bad $today throws InvalidArgumentException (7 cases)', $bad_today, array_fill(0, 7, true));

echo "\n$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
