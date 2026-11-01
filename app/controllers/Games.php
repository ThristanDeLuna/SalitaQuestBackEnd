<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Member C: the Matching game (get a round, submit answers).
 * Thin controller: it checks the request, calls the models and libraries, and answers.
 * The rules live in Game_rules and Round_builder; all queries live in the models.
 */
class Games extends ApiController   // no require: MY_ApiController is loaded at boot (DEC-87)
{
    public function __construct()
    {
        parent::__construct();                          // loads db and the Api class
        $this->config->load('games');                   // app/config/games.php: game_regions

        // Load everything here, BEFORE any transaction: each model's constructor
        // calls call->database(), which opens a new connection and makes it the
        // shared one. Loading a model inside a transaction would leave it behind.
        $this->call->model('Word_model');
        $this->call->model('Audio_model');
        $this->call->model('Game_attempt_model');
        $this->call->model('Stamp_model');
        $this->call->model('Streak_model');
        $this->call->library('game_rules');
        $this->call->library('round_builder');
    }

    // GET /api/games/matching/{region} : one round. Words and meanings only, never the pairing.
    public function matching_round($region)
    {
        $user  = $this->require_login();                // any role
        $state = $this->open_region($region, $user);

        try {
            $round = $this->round_builder->matching($this->round_words($state, $region), Game_rules::MATCHING_PAIRS);
        } catch (InvalidArgumentException $e) {
            // 4+ words but fewer than 4 different meanings: no fair round can be made yet
            $this->deny('This region is coming soon', 'coming_soon');
        }

        $this->api->respond([
            'game'     => 'matching',
            'region'   => $region,
            'words'    => $round['words'],
            'meanings' => $round['meanings'],
        ]);
    }

    // POST /api/games/matching/{region}/submit   body: {"pairs": [{"word_id": 1, "meaning": "..."}, x4]}
    public function matching_submit($region)
    {
        $user  = $this->require_login();
        $state = $this->open_region($region, $user);
        $body  = $this->api->body();

        // ---- validate (422 names the field) ----
        $pairs = $body['pairs'] ?? null;
        if (!is_array($pairs) || !array_is_list($pairs) || count($pairs) !== Game_rules::MATCHING_PAIRS) {
            $this->api->respond_error('pairs must be a list of ' . Game_rules::MATCHING_PAIRS . ' items', 422);
        }
        $translations = array_column($state['words'][$region], 'translation', 'id'); // word_id => right meaning
        $submitted    = [];                                                          // word_id => player's meaning
        foreach ($pairs as $i => $p) {
            if (!is_array($p)) {
                $this->api->respond_error("pairs[$i] must be an object", 422);
            }
            $word_id = $p['word_id'] ?? null;
            $meaning = $p['meaning'] ?? null;
            if (!is_int($word_id)) {
                $this->api->respond_error("pairs[$i].word_id must be an integer", 422);
            }
            if (!is_string($meaning) || mb_strlen($meaning) > 255) {
                $this->api->respond_error("pairs[$i].meaning must be a string of at most 255 characters", 422);
            }
            if (array_key_exists($word_id, $submitted)) {
                $this->api->respond_error("pairs[$i].word_id is repeated", 422);
            }
            if (!array_key_exists($word_id, $translations)) {
                $this->api->respond_error("pairs[$i].word_id is not a word of this region", 422);
            }
            $submitted[$word_id] = $meaning;
        }

        // ---- score ----
        $key    = array_intersect_key($translations, $submitted); // the answer key, built from the database
        $result = $this->game_rules->score_round($key, $submitted);
        $medal  = $this->game_rules->medal($result['score'], $result['max_score']);
        $today  = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');

        // ---- reads first ----
        // In this framework a failed READ (get/get_all) shows an error page and
        // exits instead of throwing, so our catch below could not roll it back.
        // So every read happens here, before the transaction, and the
        // transaction holds only writes, which do throw.
        $region_attempts = array_values(array_filter($state['attempts'], function ($a) use ($region) {
            return $a['region_code'] === $region;
        }));
        $region_attempts[] = ['game' => 'matching', 'score' => $result['score'], 'max_score' => $result['max_score']];
        $wins_stamp = $this->game_rules->earns_stamp($region_attempts)
                   && !$this->Stamp_model->has_stamp($user['id'], $region);

        $row    = $this->Streak_model->find_by_user($user['id']);
        $streak = $this->game_rules->update_streak(
            $row ? $row['last_active_date'] : null,
            $row ? $row['current_streak'] : 0,
            $row ? $row['longest_streak'] : 0,
            $today
        );

        // ---- one transaction, writes only ----
        $stamp_earned = false;
        try {
            $this->db->transaction();
            $this->Game_attempt_model->create($user['id'], $region, 'matching',
                                              $result['score'], $result['max_score'], $result['passed']);
            if ($wins_stamp) {
                // 'duplicate' means another request already gave it: not an error
                $stamp_earned = isset($this->Stamp_model->award($user['id'], $region)['id']);
            }
            $saved = $row ? null : $this->Streak_model->create($user['id'], $streak['current'], $streak['longest'], $streak['last_active_date']);
            if ($row || isset($saved['error'])) {
                $this->Streak_model->save_streak($user['id'], $streak['current'], $streak['longest'], $streak['last_active_date']);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            try { $this->db->roll_back(); } catch (Throwable $ignored) {} // the transaction may not have started
            error_log('Games::matching_submit failed for user ' . $user['id'] . ': ' . $e->getMessage());
            $this->api->respond_error('Could not save your game. Please try again.', 500);
        }

        // ---- answer ----
        $results = [];
        foreach ($submitted as $word_id => $meaning) {
            $results[] = [
                'word_id'       => $word_id,
                'your_meaning'  => $meaning,
                'right_meaning' => $key[$word_id],
                'correct'       => (string) $meaning === (string) $key[$word_id],
            ];
        }

        $this->api->respond([
            'game'         => 'matching',
            'region'       => $region,
            'score'        => $result['score'],
            'max_score'    => $result['max_score'],
            'correct'      => $result['correct'],
            'total'        => $result['total'],
            'passed'       => $result['passed'],
            'medal'        => $medal,
            'stamp_earned' => $stamp_earned,
            'streak'       => ['current' => $streak['current'], 'longest' => $streak['longest']],
            'results'      => $results,
        ]);
    }

    // 404 unknown region, 403 coming_soon or locked; otherwise the region state
    private function open_region($region, array $user)
    {
        if (!in_array($region, config_item('game_regions'), true)) {
            $this->api->respond_error('Unknown region', 404);
        }
        $state = $this->region_state($user);

        if (!in_array($region, $state['ready_regions'], true)) {
            $this->deny('This region is coming soon', 'coming_soon');
        }
        if (!$this->game_rules->can_play_region($region, $state['started_regions'], $state['stamp_count'],
                                                $state['ready_regions'], $state['skip_lock'])) {
            $this->deny('Earn a stamp to open another region', 'locked');
        }
        return $state;
    }

    // Everything can_play_region() needs, plus the words and audio we already read
    private function region_state(array $user)
    {
        $words = [];
        $audio = [];
        $ready = [];
        foreach (config_item('game_regions') as $code) {
            $words[$code] = $this->Word_model->for_region($code);
            $rows         = $words[$code] ? $this->Audio_model->for_words(array_column($words[$code], 'id')) : [];
            $audio[$code] = array_column($rows, 'url', 'word_id');          // word_id => url
            if ($this->game_rules->region_ready(count($words[$code]), count($audio[$code]))) {
                $ready[] = $code;
            }
        }

        $attempts = $this->Game_attempt_model->for_user($user['id']);
        return [
            'words'           => $words,
            'audio'           => $audio,
            'ready_regions'   => $ready,
            'started_regions' => array_values(array_unique(array_column($attempts, 'region_code'))),
            'stamp_count'     => count($this->Stamp_model->for_user($user['id'])),
            'skip_lock'       => $user['role'] === 'admin',   // Admins skip the lock, not "coming soon"
            'attempts'        => $attempts,
        ];
    }

    // The region's words in the shape Round_builder wants, with audio_url where there is audio
    private function round_words(array $state, $region)
    {
        $rows = [];
        foreach ($state['words'][$region] as $w) {
            $rows[] = [
                'id'          => $w['id'],
                'word'        => $w['word'],
                'translation' => $w['translation'],
                'audio_url'   => $state['audio'][$region][$w['id']] ?? null,
            ];
        }
        return $rows;
    }

    // 403 with a reason the frontend can show: 'coming_soon' or 'locked'
    private function deny($message, $reason)
    {
        $this->api->respond(['error' => $message, 'status' => 403, 'reason' => $reason], 403);
    }
}
