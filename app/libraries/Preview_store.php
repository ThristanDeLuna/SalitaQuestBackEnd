<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Member A: sample data and demo progress for the Checking 1 preview ONLY.
 *
 * - Words: B's 15 starter words (docs/seed_regions_and_draft_words.sql, DEC-37),
 *   written here because the words table is not merged yet. Checking 2 replaces
 *   this with real queries.
 * - Progress (attempts, points, streak, uploaded audio): kept in the LavaLust
 *   session, so one browser = one demo user. Closing the browser can reset it.
 *
 * Delete this file together with Preview.php when the real API exists.
 */
class Preview_store {

	// id => [region_code, word, translation, example_sentence]  (from B's seed)
	private $words = [
		1  => ['tagalog', 'Kumusta', 'Hello; how are you', 'Kumusta ka na?'],
		2  => ['tagalog', 'Salamat', 'Thank you', 'Salamat sa tulong mo.'],
		3  => ['tagalog', 'Tubig', 'Water', 'Gusto ko ng tubig.'],
		4  => ['tagalog', 'Maganda', 'Beautiful', 'Maganda ang umaga.'],
		5  => ['tagalog', 'Kain', 'Eat', 'Kain na tayo.'],
		6  => ['cebuano', 'Kumusta', 'Hello; how are you', 'Kumusta ka?'],
		7  => ['cebuano', 'Salamat', 'Thank you', 'Salamat kaayo.'],
		8  => ['cebuano', 'Tubig', 'Water', 'Gusto ko og tubig.'],
		9  => ['cebuano', 'Nindot', 'Beautiful', 'Nindot kaayo ang balay.'],
		10 => ['cebuano', 'Kaon', 'Eat', 'Kaon na ta.'],
		11 => ['hiligaynon', 'Kumusta', 'Hello; how are you', 'Kumusta ka?'],
		12 => ['hiligaynon', 'Salamat', 'Thank you', 'Salamat gid.'],
		13 => ['hiligaynon', 'Tubig', 'Water', 'Gusto ko sang tubig.'],
		14 => ['hiligaynon', 'Matahom', 'Beautiful', 'Matahom ang bulak.'],
		15 => ['hiligaynon', 'Kaon', 'Eat', 'Kaon na kita.'],
	];

	// Words that "have audio" at the start of a demo. Hiligaynon has only one on
	// purpose, so its listening quiz shows the "Not available yet" state.
	private $start_audio = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];

	// The LavaLust Session library; the controller loads it before using this class.
	private function s()
	{
		return lava_instance()->session;
	}

	private function get($key, $default)
	{
		$v = $this->s()->userdata($key);
		return $v === NULL ? $default : $v;
	}

	// ---------- words and audio ----------

	// All words of one region: id => [word, translation, example]
	public function words($region)
	{
		$out = [];
		foreach ($this->words as $id => $w) {
			if ($w[0] === $region) $out[$id] = ['word' => $w[1], 'translation' => $w[2], 'example' => $w[3]];
		}
		return $out;
	}

	public function word($id)
	{
		return isset($this->words[$id]) ? ['id' => $id, 'region' => $this->words[$id][0], 'word' => $this->words[$id][1], 'translation' => $this->words[$id][2]] : NULL;
	}

	public function all_ids()
	{
		return array_keys($this->words);
	}

	public function audio_ids()
	{
		return $this->get('pv_audio', $this->start_audio);
	}

	public function has_audio($id)
	{
		return in_array((int) $id, $this->audio_ids(), true);
	}

	// Called after a file passes the checks on the Audio files page.
	public function mark_audio($id)
	{
		$ids = $this->audio_ids();
		if ( ! in_array((int) $id, $ids, true)) $ids[] = (int) $id;
		$this->s()->set_userdata('pv_audio', $ids);
	}

	// Words of a region that have audio (the listening quiz needs 5).
	public function words_with_audio($region)
	{
		return array_filter($this->words($region), function ($id) { return $this->has_audio($id); }, ARRAY_FILTER_USE_KEY);
	}

	// ---------- one game round: the answer key stays on the server ----------

	// $round = ['key' => [item_id => token of the right answer], 'labels' => [token => text], ...]
	// Only the tokens and texts go to the browser, never which token is right.
	public function save_round($game, $region, array $round)
	{
		$this->s()->set_userdata('pv_key_' . $game . '_' . $region, $round);
	}

	// Read the round once and delete it, so the same round cannot be scored twice.
	public function take_round($game, $region)
	{
		$name = 'pv_key_' . $game . '_' . $region;
		$key  = $this->get($name, NULL);
		$this->s()->unset_userdata($name);
		return $key;
	}

	// ---------- attempts, points, streak ----------

	public function add_attempt($region, $game, $score, $max_score)
	{
		$all   = $this->get('pv_attempts', []);
		$all[] = ['region' => $region, 'game' => $game, 'score' => $score, 'max_score' => $max_score];
		$this->s()->set_userdata('pv_attempts', $all);
	}

	public function attempts($region = NULL)
	{
		$all = $this->get('pv_attempts', []);
		if ($region === NULL) return $all;
		return array_values(array_filter($all, function ($a) use ($region) { return $a['region'] === $region; }));
	}

	// Total points = sum of all scores (failed attempts still give their points).
	public function total_points()
	{
		return array_sum(array_column($this->attempts(), 'score'));
	}

	public function streak()
	{
		return $this->get('pv_streak', ['current' => 0, 'longest' => 0, 'last_active_date' => NULL]);
	}

	public function save_streak(array $streak)
	{
		$this->s()->set_userdata('pv_streak', $streak);
	}

	// "Reset demo" button on the passport.
	public function reset()
	{
		$this->s()->unset_userdata(['pv_audio', 'pv_attempts', 'pv_streak',
			'pv_key_matching_tagalog', 'pv_key_matching_cebuano', 'pv_key_matching_hiligaynon',
			'pv_key_listening_tagalog', 'pv_key_listening_cebuano', 'pv_key_listening_hiligaynon']);
	}
}
