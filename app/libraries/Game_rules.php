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
}
