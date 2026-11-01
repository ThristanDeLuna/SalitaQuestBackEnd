<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Game_rules: the scoring, stamp, streak and Word-of-the-Day rules for Salita Quest.
 *
 * Every method is a "pure function": the result depends only on the arguments.
 * No database, no session, no clock. That makes the rules easy to test and easy
 * to explain at the defense. Controllers fetch data, call these methods, then save.
 *
 * Load in a controller with: $this->call->library('game_rules');
 * Then use:                  $this->game_rules->score_round($key, $answers);
 */
class Game_rules
{
    const POINTS_PER_CORRECT  = 10; // each correct pair or answer
    const MATCHING_PAIRS      = 4;  // pairs in one matching round   (max 40 points)
    const LISTENING_QUESTIONS = 5;  // questions in one listening quiz (max 50 points)
    const PASS_PERCENT        = 60; // minimum % in a game for it to count toward a stamp
    const SILVER_PERCENT      = 80; // minimum % for a silver medal (gold needs full marks)
    const SAILOR_DAYS         = 3;  // streak length for the "3-day Sailor" badge
    const ISLAND_HOPPER_REGIONS = 3; // regions played for the "Island Hopper" badge
    const MIN_WORDS           = 4;  // a region needs this many words (matching has 4 pairs)
    const MIN_AUDIO_WORDS     = 5;  // ...and this many words with audio (listening has 5 questions)

    // Every badge, in the fixed order the Passport shows them (badges_for() uses the same order)
    const BADGE_IDS = ['first_stamp', 'sailor_3', 'gold_catcher', 'sharp_ears', 'island_hopper'];

    /**
     * Score one round of either game.
     *
     * Both games work the same way: the server holds an answer key and the player
     * submits answers. The key is never sent to the browser, so the player cannot
     * read the answers from the response.
     *
     * @param array $answer_key Correct answers, e.g. [word_id => translation_id]
     *                          (matching) or [question_id => option_id] (listening).
     * @param array $submitted  The player's answers, same shape as the key.
     * @return array score, max_score, correct, total, passed
     */
    public function score_round(array $answer_key, array $submitted)
    {
        $total   = count($answer_key);
        $correct = 0;

        foreach ($answer_key as $item_id => $right_answer) {
            // Only items that are in the key count. Extra or unknown items the
            // player sends are ignored, and a missing answer is simply wrong.
            if (array_key_exists($item_id, $submitted) && (string) $submitted[$item_id] === (string) $right_answer) {
                $correct++;
            }
        }

        $score     = $correct * self::POINTS_PER_CORRECT;
        $max_score = $total * self::POINTS_PER_CORRECT;

        return [
            'score'     => $score,
            'max_score' => $max_score,
            'correct'   => $correct,
            'total'     => $total,
            'passed'    => $this->is_pass($score, $max_score),
        ];
    }

    /**
     * Did this score reach the pass mark (60%)?
     * Integer maths on purpose: 24/40 is exactly 60%, with no float rounding.
     */
    public function is_pass($score, $max_score)
    {
        if ($max_score <= 0) {
            return false; // an empty round can never pass
        }
        return ($score * 100) >= (self::PASS_PERCENT * $max_score);
    }

    /**
     * Which medal does one score earn? (DEC-59)
     * bronze = a pass (60%+), silver = 80%+, gold = full marks, null = not a pass.
     * Same integer maths as is_pass(): no float rounding.
     *
     * @return string|null 'gold', 'silver', 'bronze' or null
     */
    public function medal($score, $max_score)
    {
        if (!$this->is_pass($score, $max_score)) {
            return null; // below the pass mark: no medal
        }
        if ($score >= $max_score) {
            return 'gold';
        }
        if (($score * 100) >= (self::SILVER_PERCENT * $max_score)) {
            return 'silver';
        }
        return 'bronze';
    }

    /**
     * Stars = the best score per game per region, added up (DEC-59).
     * Replaying a game only raises stars if the new score beats the old best,
     * so repeat plays cannot farm points. Maximum is 270 with 3 regions
     * (3 x (40 + 50)).
     *
     * @param array $attempts Rows for ONE user, all regions, each with
     *                        'game', 'region_code' and 'score'.
     * @return int Total stars
     */
    public function stars_for(array $attempts)
    {
        $best = []; // key "game|region" => highest score seen

        foreach ($attempts as $a) {
            $game = isset($a['game']) ? $a['game'] : null;
            if ($game !== 'matching' && $game !== 'listening') {
                continue; // ignore anything that is not one of our two games
            }
            $key   = $game . '|' . (isset($a['region_code']) ? $a['region_code'] : '');
            $score = (int) $a['score'];
            if (!isset($best[$key]) || $score > $best[$key]) {
                $best[$key] = $score;
            }
        }

        return array_sum($best);
    }

    /**
     * The best attempt per region and game, for the Passport (DEC-59).
     * Same "best score" idea as stars_for(), but kept per region with its medal.
     * On a tie the first attempt seen is kept.
     *
     * @param array $attempts Rows for ONE user, each with 'game', 'region_code',
     *                        'score' and 'max_score'. Other games are ignored.
     * @return array region_code => ['matching' => best|null, 'listening' => best|null],
     *               where best = ['best' => int, 'max' => int, 'medal' => string|null].
     *               Only regions that appear in the attempts.
     */
    public function best_scores(array $attempts)
    {
        $out = [];

        foreach ($attempts as $a) {
            $game = isset($a['game']) ? $a['game'] : null;
            if ($game !== 'matching' && $game !== 'listening') {
                continue; // ignore anything that is not one of our two games
            }
            $region = isset($a['region_code']) ? (string) $a['region_code'] : '';
            if (!isset($out[$region])) {
                $out[$region] = ['matching' => null, 'listening' => null];
            }
            $score = (int) $a['score'];
            // Only a strictly higher score replaces the best, so a tie keeps the first
            if ($out[$region][$game] === null || $score > $out[$region][$game]['best']) {
                $out[$region][$game] = ['best' => $score, 'max' => (int) $a['max_score']];
            }
        }

        // The medal of each best attempt
        foreach ($out as $region => $games) {
            foreach ($games as $game => $b) {
                if ($b !== null) {
                    $out[$region][$game]['medal'] = $this->medal($b['best'], $b['max']);
                }
            }
        }

        return $out;
    }

    /**
     * Which badges has the player earned? (DEC-58) Nothing is stored: the list
     * is worked out again from the data every time.
     *   first_stamp   - at least one region stamp
     *   sailor_3      - best streak reached 3 days (uses the LONGEST streak,
     *                   so a badge is never lost when the streak breaks)
     *   gold_catcher  - full marks in any game
     *   sharp_ears    - passed a listening quiz
     *   island_hopper - played games in 3 different regions
     *
     * @param array $attempts       Rows for ONE user, each with 'game',
     *                              'region_code', 'score' and 'max_score'.
     * @param int   $stamp_count    Stamps earned.
     * @param int   $longest_streak Best streak so far.
     * @return array Badge ids earned, in a fixed order.
     */
    public function badges_for(array $attempts, $stamp_count, $longest_streak)
    {
        $gold    = false;
        $ears    = false;
        $regions = [];

        foreach ($attempts as $a) {
            $score = (int) $a['score'];
            $max   = (int) $a['max_score'];

            if ($this->medal($score, $max) === 'gold') {
                $gold = true;
            }
            if (isset($a['game']) && $a['game'] === 'listening' && $this->is_pass($score, $max)) {
                $ears = true;
            }
            if (isset($a['region_code']) && $a['region_code'] !== '') {
                $regions[$a['region_code']] = true;
            }
        }

        $earned = [];
        if ((int) $stamp_count >= 1)                            { $earned[] = 'first_stamp'; }
        if ((int) $longest_streak >= self::SAILOR_DAYS)         { $earned[] = 'sailor_3'; }
        if ($gold)                                              { $earned[] = 'gold_catcher'; }
        if ($ears)                                              { $earned[] = 'sharp_ears'; }
        if (count($regions) >= self::ISLAND_HOPPER_REGIONS)     { $earned[] = 'island_hopper'; }

        return $earned;
    }

    /**
     * Daily quests (DEC-58). Each quest is a yes/no worked out from TODAY's
     * attempts only. Nothing is stored. Every quest is always possible for a
     * player who has at least one open region, so none can become impossible.
     *   play_one   - played any game today
     *   pass_one   - passed any game today
     *   both_games - played both Matching and Listening today (any region)
     *
     * @param array $todays_attempts Rows for ONE user, already limited to today
     *                               (Asia/Manila date) by the caller. Each has
     *                               'game', 'score' and 'max_score'.
     * @return array quest id => true/false, in a fixed order
     */
    public function quests_for(array $todays_attempts)
    {
        $played = ['matching' => false, 'listening' => false];
        $passed = false;

        foreach ($todays_attempts as $a) {
            $game = isset($a['game']) ? $a['game'] : null;
            if (!isset($played[$game])) {
                continue; // ignore anything that is not one of our two games
            }
            $played[$game] = true;
            if ($this->is_pass((int) $a['score'], (int) $a['max_score'])) {
                $passed = true;
            }
        }

        return [
            'play_one'   => $played['matching'] || $played['listening'],
            'pass_one'   => $passed,
            'both_games' => $played['matching'] && $played['listening'],
        ];
    }

    /**
     * Is a region ready to play, or still "coming soon"?
     * Matching needs 4 words and listening needs 5 words with audio.
     */
    public function region_ready($word_count, $audio_word_count)
    {
        return (int) $word_count >= self::MIN_WORDS && (int) $audio_word_count >= self::MIN_AUDIO_WORDS;
    }

    /**
     * Soft lock: may this player start (or keep playing) this region?
     * Rule: the number of regions you may have started = stamps earned + 1.
     * A region you already started always stays playable. A region that is
     * "coming soon" is never playable and does not count toward the limit.
     * Admins can skip the lock ($skip_lock) but a region still must be ready.
     *
     * @param string $region_code      Region the player wants to open.
     * @param array  $started_regions  Region codes the player already has attempts in.
     * @param int    $stamp_count      Stamps the player has earned.
     * @param array  $ready_regions    Region codes that pass region_ready().
     * @param bool   $skip_lock        True for Admins (safety net for the demo).
     */
    public function can_play_region($region_code, array $started_regions, $stamp_count, array $ready_regions, $skip_lock = false)
    {
        if (!in_array($region_code, $ready_regions, true)) {
            return false; // coming soon
        }
        if ($skip_lock) {
            return true;
        }

        // Only started regions that are ready count toward the limit.
        $started_ready = array_intersect($started_regions, $ready_regions);
        if (in_array($region_code, $started_ready, true)) {
            return true; // already started: stays open
        }

        return count($started_ready) < ((int) $stamp_count + 1);
    }

    /**
     * The status the Passport shows for one region. Checked in this order:
     *   stamped     - the player has the region's stamp
     *   coming_soon - the region is not ready (too few words or audio)
     *   in_progress - the player has played it
     *   not_started - the player may start it (see can_play_region())
     *   locked      - the soft lock keeps it closed for now
     *
     * @return string one of the five values above
     */
    public function region_status($has_stamp, $is_ready, $started, $can_play)
    {
        if ($has_stamp) {
            return 'stamped';
        }
        if (!$is_ready) {
            return 'coming_soon';
        }
        if ($started) {
            return 'in_progress';
        }
        return $can_play ? 'not_started' : 'locked';
    }

    /**
     * Has the player earned the stamp for a region?
     * Rule: at least one passing matching attempt AND one passing listening attempt.
     * A failed attempt is still saved (it counts for the streak) but never counts here.
     *
     * @param array $attempts Rows for ONE user and ONE region, each with
     *                        'game', 'score' and 'max_score'.
     */
    public function earns_stamp(array $attempts)
    {
        $passed = ['matching' => false, 'listening' => false];

        foreach ($attempts as $a) {
            $game = isset($a['game']) ? $a['game'] : null;
            if (isset($passed[$game]) && $this->is_pass((int) $a['score'], (int) $a['max_score'])) {
                $passed[$game] = true;
            }
        }

        return $passed['matching'] && $passed['listening'];
    }

    /**
     * Work out the new streak after the player does something today.
     * No scheduler needed: we compare today's date with the last active date
     * every time the player plays or opens the passport.
     *
     * @param string|null $last_active_date 'Y-m-d' of the last active day, or null if never.
     * @param int         $current          Current streak.
     * @param int         $longest          Best streak so far.
     * @param string      $today            'Y-m-d'. Passed in (not read from the clock)
     *                                      so the caller picks the time zone and tests can fix it.
     * @return array current, longest, last_active_date
     */
    public function update_streak($last_active_date, $current, $longest, $today)
    {
        $current = (int) $current;
        $longest = (int) $longest;

        if ($last_active_date === null || $last_active_date === '') {
            $new_current = 1; // first ever active day
        } else {
            $last = new DateTimeImmutable($last_active_date);
            $now  = new DateTimeImmutable($today);
            // Whole days between the two dates (negative if the stored date is in the future).
            $days = (int) $last->diff($now)->format('%r%a');

            if ($days <= 0) {
                $new_current = max($current, 1); // already counted today
                $today       = $last_active_date; // keep the stored date, never move it backwards
            } elseif ($days === 1) {
                $new_current = $current + 1;     // consecutive day
            } else {
                $new_current = 1;                // missed at least one day: start again
            }
        }

        return [
            'current'          => $new_current,
            'longest'          => max($longest, $new_current),
            'last_active_date' => $today,
        ];
    }

    /**
     * The CURRENT streak to show on the Passport. A read never writes, so the
     * stored streak is not changed here; we only decide whether it still counts.
     *   never played                    -> 0
     *   last active today or yesterday  -> the stored current streak
     *   last active before yesterday    -> 0 (the streak has lapsed)
     *   last active date in the future  -> the stored current streak (clock oddity)
     * Dates are compared the same way as in update_streak().
     *
     * @param string|null $last_active_date 'Y-m-d' or null
     * @param int         $current          Stored current streak.
     * @param string      $today            'Y-m-d' (Asia/Manila), passed in.
     * @return int
     */
    public function streak_for_display($last_active_date, $current, $today)
    {
        if ($last_active_date === null || $last_active_date === '') {
            return 0; // never played
        }
        $last = new DateTimeImmutable($last_active_date);
        $now  = new DateTimeImmutable($today);
        // Whole days between the two dates (negative if the stored date is in the future).
        $days = (int) $last->diff($now)->format('%r%a');

        return $days <= 1 ? (int) $current : 0;
    }

    /**
     * Pick the Word of the Day from the date alone. Nothing is stored and no
     * scheduler runs: every user gets the same word on the same date, and it
     * rotates through the list one word per day.
     *
     * @param array  $word_ids Ids of APPROVED words only (the caller filters).
     * @param string $date     'Y-m-d'
     * @return mixed The chosen id, or null if there are no words.
     */
    public function word_of_the_day(array $word_ids, $date)
    {
        if (count($word_ids) === 0) {
            return null;
        }

        sort($word_ids); // fixed order, so the same date always gives the same word

        $days_since_epoch = (int) floor((new DateTimeImmutable($date . ' 00:00:00 UTC'))->getTimestamp() / 86400);
        $index = $days_since_epoch % count($word_ids);

        return $word_ids[$index];
    }

    /**
     * The week dots on the Passport: which days of THIS week (Monday to Sunday,
     * in Manila) the player played at least once.
     *
     * @param array  $attempts Rows for ONE user, each with 'created_at' in UTC
     *                         ('Y-m-d H:i:s', DEC-82). Rows with a missing or bad
     *                         created_at are skipped, never an error.
     * @param string $today    'Y-m-d', today's date in Asia/Manila, passed in.
     * @return array 7 items, Monday first: ['date' => 'Y-m-d', 'day' => 'M', 'active' => bool]
     * @throws InvalidArgumentException if $today is not a real Y-m-d date
     */
    public function active_days_in_week(array $attempts, $today)
    {
        // Strict parse, like Game_attempt_model::today_for_user(): '!' sets 00:00:00
        $manila = new DateTimeZone('Asia/Manila');
        $day    = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $today, $manila);
        if ($day === false || $day->format('Y-m-d') !== $today) {
            throw new InvalidArgumentException('today must be Y-m-d');
        }

        // The Manila dates on which the player played (date => true)
        $utc    = new DateTimeZone('UTC');
        $played = [];
        foreach ($attempts as $a) {
            $raw = isset($a['created_at']) && is_string($a['created_at']) ? $a['created_at'] : '';
            $at  = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, $utc);
            if ($at === false || $at->format('Y-m-d H:i:s') !== $raw) {
                continue; // missing or not a real date-time: skip the row
            }
            $played[$at->setTimezone($manila)->format('Y-m-d')] = true;
        }

        // Monday of this week: 'N' is 1 (Monday) to 7 (Sunday)
        $monday = $day->modify('-' . ((int) $day->format('N') - 1) . ' days');
        $letters = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
        $week    = [];
        foreach ($letters as $i => $letter) {
            $date   = $monday->modify("+$i days")->format('Y-m-d');
            $week[] = ['date' => $date, 'day' => $letter, 'active' => isset($played[$date])];
        }
        return $week;
    }
}
