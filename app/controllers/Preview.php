<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Member A: Checking 1 preview of the site map (DEC-38, DEC-51).
 *
 * - Every site map page has a route here. Most are placeholders.
 * - C's four screens work like the real thing (C agreed, Oct 20): matching game,
 *   listening quiz, passport and admin audio. They use sample words (B's seed,
 *   DEC-37) from the Preview_store library and are scored by C's real Game_rules.
 * - There is NO database and NO real login. "?as=admin" only pretends to be a
 *   role so the menu and access rules can be shown. Real login is Google + JWT
 *   (DEC-05) and the real role check is in the API (DEC-23).
 * Delete this controller, its views, preview_helper.php and Preview_store.php
 * when the React app and the real API exist.
 */
class Preview extends Controller {

	// Only these values are accepted; anything else counts as "visitor" or "not found".
	private $roles   = ['visitor', 'learner', 'contributor', 'admin'];
	private $regions = [   // code => [name, area]; codes from DEC-43, areas from B's seed
		'tagalog'    => ['Tagalog', 'Luzon'],
		'cebuano'    => ['Cebuano (Bisaya)', 'Central Visayas'],
		'hiligaynon' => ['Hiligaynon', 'Western Visayas'],
	];

	public function __construct()
	{
		parent::__construct();
		// Nothing is autoloaded (DEC-11): load what the pages need.
		$this->call->helper(['url', 'preview']);
		$this->call->library('session');        // remembers demo progress per browser
		$this->call->library('preview_store');  // sample words + progress
		$this->call->library('game_rules');     // C's scoring rules (merged in PR #1)
	}

	// ================= pages, in site map order =================

	// GET /preview  - Map (everyone)
	public function index()
	{
		$links = [];
		foreach ($this->regions as $code => $r) {
			$links[] = ['Open ' . $r[0], 'regions/' . $code];
		}
		$this->_page('Map', 'Choose a region', '/', 'everyone', NULL, 'B / team',
			'The Leaflet map with three pins goes here. Each pin opens its word list.', $links);
	}

	// GET /preview/regions/{region}  - Word list (everyone)
	public function region($code)
	{
		$name  = $this->_region($code)[0];
		$links = [
			['Play matching game',  'play/matching/' . $code],
			['Play listening quiz', 'play/listening/' . $code],
		];
		// DEC-46: "Submit a word" is hidden from visitors and Learners.
		if (in_array($this->_role(), ['contributor', 'admin'], TRUE)) {
			$links[] = ['Submit a new word', 'submit'];
		}
		$this->_page('Map', 'Word list: ' . $name, '/regions/' . $code, 'everyone', NULL, 'B',
			'Table of approved words: Word, Translation, Example sentence, Audio.', $links);
	}

	// GET and POST /preview/play/matching/{region}  - signed in (C's screen 1)
	public function matching($code)
	{
		$region = $this->_region($code);
		$this->_need('signed_in', 'Map');
		$words  = $this->preview_store->words($code);
		$data   = ['active' => 'Map', 'title' => 'Matching game', 'code' => $code, 'region' => $region, 'result' => NULL];

		if (count($words) < Game_rules::MATCHING_PAIRS) {
			$data['unavailable'] = 'This region needs at least ' . Game_rules::MATCHING_PAIRS . ' approved words.';
			return $this->_render('unavailable', $data);
		}

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$round = $this->preview_store->take_round('matching', $code);
			if ($round === NULL) {                       // already scored, or session expired
				// LavaLust's redirect() does not exit (its exit line uses ??), so exit here.
				redirect(pv_url('play/matching/' . $code, $this->_role()));
				exit;
			}
			$sent = isset($_POST['pair']) && is_array($_POST['pair']) ? $_POST['pair'] : [];
			$data['result'] = $this->_score('matching', $code, $round, $sent);
			return $this->_render('matching', $data);
		}

		// New round: 4 random words. Each meaning gets a random token; the key
		// (which token belongs to which word) stays in the session, not the page.
		$ids   = (array) array_rand($words, Game_rules::MATCHING_PAIRS);
		shuffle($ids);
		$key = $labels = $left = [];
		foreach ($ids as $id) {
			$token          = bin2hex(random_bytes(4));
			$key[$id]       = $token;
			$labels[$token] = $words[$id]['translation'];
			$left[$id]      = $words[$id]['word'];
		}
		$right = $labels;
		$this->_shuffle_keep_keys($right);               // meanings in a different order
		$this->preview_store->save_round('matching', $code, ['key' => $key, 'labels' => $labels, 'items' => $left]);

		$data['left']  = $left;
		$data['right'] = $right;
		$this->_render('matching', $data);
	}

	// GET and POST /preview/play/listening/{region}  - signed in (C's screen 2)
	public function listening($code)
	{
		$region = $this->_region($code);
		$this->_need('signed_in', 'Map');
		$words  = $this->preview_store->words_with_audio($code);
		$data   = ['active' => 'Map', 'title' => 'Listening quiz', 'code' => $code, 'region' => $region, 'result' => NULL];

		if (count($words) < Game_rules::LISTENING_QUESTIONS) {
			$data['unavailable'] = 'This region needs at least ' . Game_rules::LISTENING_QUESTIONS
				. ' approved words with audio (it has ' . count($words) . '). An admin can upload audio on the Audio files page.';
			return $this->_render('unavailable', $data);
		}

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$round = $this->preview_store->take_round('listening', $code);
			if ($round === NULL) {
				redirect(pv_url('play/listening/' . $code, $this->_role()));
				exit;
			}
			$sent = isset($_POST['answer']) && is_array($_POST['answer']) ? $_POST['answer'] : [];
			$data['result'] = $this->_score('listening', $code, $round, $sent);
			return $this->_render('listening', $data);
		}

		// New quiz: 5 words with audio; each question offers the right meaning
		// plus 3 other meanings from the same region, each with a random token.
		$all  = $this->preview_store->words($code);
		$ids  = (array) array_rand($words, Game_rules::LISTENING_QUESTIONS);
		shuffle($ids);
		$key = $labels = $items = $questions = [];
		foreach ($ids as $id) {
			$others = array_diff(array_keys($all), [$id]);
			shuffle($others);
			$options = [];
			foreach (array_merge([$id], array_slice($others, 0, 3)) as $opt_id) {
				$token           = bin2hex(random_bytes(4));
				$options[$token] = $all[$opt_id]['translation'];
				$labels[$token]  = $all[$opt_id]['translation'];
				if ($opt_id === $id) $key[$id] = $token;
			}
			$this->_shuffle_keep_keys($options);
			$items[$id]     = $all[$id]['word'];
			$questions[$id] = ['word' => $all[$id]['word'], 'options' => $options];
		}
		$this->preview_store->save_round('listening', $code, ['key' => $key, 'labels' => $labels, 'items' => $items]);

		$data['questions'] = $questions;
		$this->_render('listening', $data);
	}

	// GET /preview/passport  - signed in (C's screen 3)
	public function passport()
	{
		$this->_need('signed_in', 'Passport');
		$today = $this->_today();
		$rows  = [];
		$stamps = 0;
		foreach ($this->regions as $code => $r) {
			$attempts = $this->preview_store->attempts($code);
			$n_words  = count($this->preview_store->words($code));
			$n_audio  = count($this->preview_store->words_with_audio($code));
			$stamp    = $this->game_rules->earns_stamp($attempts);
			$stamps  += $stamp ? 1 : 0;
			$rows[] = [
				'name'      => $r[0],
				'available' => $n_words . ' words, ' . $n_audio . ' with audio',
				'matching'  => $n_words < Game_rules::MATCHING_PAIRS ? 'Needs ' . Game_rules::MATCHING_PAIRS . ' words' : $this->_best($attempts, 'matching'),
				'listening' => $n_audio < Game_rules::LISTENING_QUESTIONS ? 'Needs ' . Game_rules::LISTENING_QUESTIONS . ' audio' : $this->_best($attempts, 'listening'),
				'stamp'     => $stamp ? 'Earned' : 'Not yet',
			];
		}
		// Word of the Day: picked from the date alone (C's rule, no scheduler, DEC-06).
		$wotd_id = $this->game_rules->word_of_the_day($this->preview_store->all_ids(), $today);
		$wotd    = $this->preview_store->word($wotd_id);

		$this->_render('passport', [
			'active' => 'Passport', 'title' => 'My passport', 'rows' => $rows,
			'points' => $this->preview_store->total_points(), 'streak' => $this->preview_store->streak(),
			'stamps' => $stamps, 'total_regions' => count($this->regions),
			'wotd' => $wotd, 'wotd_region' => $this->regions[$wotd['region']][0],
		]);
	}

	// POST /preview/passport/reset  - clear this browser's demo progress
	public function reset()
	{
		$this->_need('signed_in', 'Passport');
		$this->preview_store->reset();
		redirect(pv_url('passport', $this->_role()));
		exit;
	}

	// GET /preview/submit  - Contributor and Admin (D's screen, placeholder)
	public function submit()
	{
		$this->_page('Submit a word', 'Submit a word', '/submit', 'Contributor and Admin', 'contributor', 'D',
			'Form: word, language, suggested translation (from MyMemory). Goes to the approval queue.',
			[['Send (returns to map)', '']]);
	}

	// Admin pages of B and D stay placeholders.
	public function approvals() { $this->_admin('Pending approvals', '/admin/approvals', 'D', 'Approve or reject submitted words; the contributor gets an email (Brevo).'); }
	public function regions()   { $this->_admin('Regions', '/admin/regions', 'B', 'List of regions; the add and edit form opens on this page.'); }
	public function words()     { $this->_admin('Words', '/admin/words', 'B', 'List of words; the add and edit form opens on this page.'); }

	// GET and POST /preview/admin/audio  - Admin only (C's screen 4)
	public function audio()
	{
		$this->_need('admin', 'Audio files');
		$data = ['active' => 'Audio files', 'title' => 'Audio files', 'message' => NULL, 'error' => NULL];

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$word = $this->preview_store->word(isset($_POST['word_id']) ? (int) $_POST['word_id'] : 0);
			$err  = $word === NULL ? 'Unknown word.' : $this->_check_audio_file();
			if ($err !== NULL) {
				$data['error'] = 'Upload failed. Nothing was saved. ' . $err;
			} else {
				$this->preview_store->mark_audio($word['id']);
				$data['message'] = 'Checked ' . $_FILES['audio']['name'] . ' (' . ceil($_FILES['audio']['size'] / 1024)
					. ' KB) for ' . $word['word'] . '. Preview only: the file is not kept; Cloudinary comes later.';
			}
		}

		// Region filter (?region=...) and the word whose upload panel is open (?upload=id).
		$filter = isset($_GET['region']) && isset($this->regions[$_GET['region']]) ? $_GET['region'] : '';
		$rows   = [];
		$missing = 0;
		foreach ($this->regions as $code => $r) {
			if ($filter !== '' && $filter !== $code) continue;
			foreach ($this->preview_store->words($code) as $id => $w) {
				$has = $this->preview_store->has_audio($id);
				$missing += $has ? 0 : 1;
				$rows[] = ['id' => $id, 'word' => $w['word'], 'region' => $r[0], 'has' => $has];
			}
		}
		$open = isset($_GET['upload']) ? $this->preview_store->word((int) $_GET['upload']) : NULL;

		$data += ['rows' => $rows, 'missing' => $missing, 'filter' => $filter, 'open' => $open,
		          'region_names' => array_map(function ($r) { return $r[0]; }, $this->regions)];
		$this->_render('audio', $data);
	}

	// ================= Checking 1 overview =================

	// Files the overview may show. Fixed list: the URL can only pick a name,
	// never a path, so no other file on the computer can be read this way.
	private $docs = [
		'sitemap'          => ['docs/sitemap/SalitaQuest_Sitemap.drawio.png', 'Site map', 'B', 'PR #11'],
		'wire-a'           => ['docs/wireframes/SalitaQuest_A_Wireframes.pdf', 'Sign-in shell, menu by role, system screens', 'A', 'branch a/checking-1-preview'],
		'wire-b'           => ['docs/wireframes/SalitaQuest_B_Wireframes.pdf', 'Word list, admin regions, admin words', 'B', 'PR #10'],
		'wire-c'           => ['docs/wireframes/SalitaQuest_C_Wireframes.pdf', 'Matching, listening, passport, admin audio', 'C', 'branch a/checking-1-preview'],
		'wire-d-submit'    => ['docs/wireframes/D_submit_a_word.png', 'Submit a word', 'D', 'PR #7'],
		'wire-d-approvals' => ['docs/wireframes/D_pending_approvals.png', 'Pending approvals', 'D', 'PR #7'],
		'erd'              => ['docs/erd/Erd_SalitaQuest.drawio.png', 'Entity-Relationship Diagram', 'B', 'PR #9'],
	];

	// GET /preview/checking-1  - the four things the Checking 1 sheet asks to see
	public function checking1()
	{
		$docs = [];
		foreach ($this->docs as $name => $d) {
			$docs[$name] = ['label' => $d[1], 'owner' => $d[2], 'from' => $d[3],
			                'ext' => pathinfo($d[0], PATHINFO_EXTENSION), 'path' => $d[0],
			                'exists' => is_file(ROOT_DIR . $d[0])];
		}

		// Routes: read routes.php the same way "php lava route:list" does (a regex on the text).
		$text = file_get_contents(APP_DIR . 'config/routes.php');
		preg_match_all("/\\\$router\s*->\s*(get|post)\s*\(\s*'([^']+)'\s*,\s*'([^']+)'/i", $text, $m, PREG_SET_ORDER);
		$routes = [];
		foreach ($m as $r) {
			$routes[] = ['method' => strtoupper($r[1]), 'uri' => $r[2], 'handler' => $r[3]];
		}

		$this->_render('checking1', [
			'active'      => NULL,
			'title'       => 'Checking 1',
			'docs'        => $docs,
			'routes'      => $routes,
			'migrations'  => array_values(array_filter(scandir(APP_DIR . 'migrations'), function ($f) { return substr($f, -4) === '.php'; })),
			'controllers' => array_values(array_filter(scandir(APP_DIR . 'controllers'), function ($f) { return substr($f, -4) === '.php'; })),
			'lava'        => trim(strtok(file_get_contents(ROOT_DIR . 'changelog.txt'), "\n")),
			'env'         => config_item('environment'),
		]);
	}

	// GET /preview/doc/{name}  - send one diagram from docs/ to the browser
	public function doc($name)
	{
		if ( ! isset($this->docs[$name]) || ! is_file(ROOT_DIR . $this->docs[$name][0])) {
			$this->_screen('not_found', 404);
		}
		$file = ROOT_DIR . $this->docs[$name][0];
		$type = pathinfo($file, PATHINFO_EXTENSION) === 'pdf' ? 'application/pdf' : 'image/png';
		header('Content-Type: ' . $type);
		header('Content-Length: ' . filesize($file));
		header('X-Content-Type-Options: nosniff');
		readfile($file);
		exit;
	}

	// ================= shared code =================

	// Score a round with Game_rules, save the attempt, update the streak and stamp.
	private function _score($game, $code, array $round, array $sent)
	{
		$result = $this->game_rules->score_round($round['key'], $sent);

		$had_stamp = $this->game_rules->earns_stamp($this->preview_store->attempts($code));
		$this->preview_store->add_attempt($code, $game, $result['score'], $result['max_score']);
		$result['new_stamp'] = ! $had_stamp && $this->game_rules->earns_stamp($this->preview_store->attempts($code));

		// Failed attempts still count for the streak (DEC-13).
		$s = $this->preview_store->streak();
		$this->preview_store->save_streak($this->game_rules->update_streak($s['last_active_date'], $s['current'], $s['longest'], $this->_today()));

		// What the player chose versus the right answer, shown after checking.
		$review = [];
		foreach ($round['key'] as $id => $right) {
			$chosen   = isset($sent[$id]) ? (string) $sent[$id] : '';
			$review[] = [
				'item'    => $round['items'][$id],
				'chosen'  => isset($round['labels'][$chosen]) ? $round['labels'][$chosen] : '(no answer)',
				'right'   => $round['labels'][$right],
				'correct' => $chosen === (string) $right,
			];
		}
		$result['review']  = $review;
		$result['percent'] = (int) floor($result['score'] * 100 / $result['max_score']);
		return $result;
	}

	// Best attempt of one game in one region, as text for the passport table.
	private function _best(array $attempts, $game)
	{
		$best = NULL;
		foreach ($attempts as $a) {
			if ($a['game'] === $game && ($best === NULL || $a['score'] > $best['score'])) $best = $a;
		}
		if ($best === NULL) return 'Not played yet';
		$word = $this->game_rules->is_pass($best['score'], $best['max_score']) ? 'Passed ' : 'Failed ';
		return $word . $best['score'] . '/' . $best['max_score'];
	}

	// Checks for an uploaded audio file. Returns an error text, or NULL if it is fine.
	// Limits (mp3/m4a/wav, 2 MB) are C's proposal in the wireframe.
	private function _check_audio_file()
	{
		$f = isset($_FILES['audio']) ? $_FILES['audio'] : NULL;
		if ($f === NULL || $f['error'] === UPLOAD_ERR_NO_FILE) return 'Choose a file first.';
		if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], TRUE)) return 'The file is larger than 2 MB.';
		if ($f['error'] !== UPLOAD_ERR_OK || ! is_uploaded_file($f['tmp_name'])) return 'The file did not arrive. Try again.';
		$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
		if ( ! in_array($ext, ['mp3', 'm4a', 'wav'], TRUE)) return 'Only mp3, m4a or wav files are allowed.';
		if ($f['size'] > 2 * 1024 * 1024) return 'The file is larger than 2 MB.';
		return NULL;   // PHP deletes the temporary file after the request: nothing is stored
	}

	// Today's date in the Philippines (streak and Word of the Day, DEC-13).
	private function _today()
	{
		return (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
	}

	private function _shuffle_keep_keys(array &$arr)
	{
		$keys = array_keys($arr);
		shuffle($keys);
		$out = [];
		foreach ($keys as $k) $out[$k] = $arr[$k];
		$arr = $out;
	}

	private function _admin($title, $route, $owner, $note)
	{
		$this->_page($title, $title, $route, 'Admin only', 'admin', $owner, $note, [['Back to map', '']]);
	}

	// Demo role from ?as=..., checked against the list; default is a signed-out visitor.
	private function _role()
	{
		$as = isset($_GET['as']) ? (string) $_GET['as'] : 'visitor';
		return in_array($as, $this->roles, TRUE) ? $as : 'visitor';
	}

	// Unknown region code -> "Page not found" (the code itself is never printed raw).
	private function _region($code)
	{
		if ( ! isset($this->regions[$code])) {
			$this->_screen('not_found', 404);
		}
		return $this->regions[$code];
	}

	// Access rule (DEC-46). Stops with a system screen if the demo role may not open the page.
	private function _need($need, $active)
	{
		$role = $this->_role();
		if ($need === NULL) return;                                    // public page
		if ($role === 'visitor') $this->_screen('sign_in', 401, $active);
		if ($need === 'contributor' && ! in_array($role, ['contributor', 'admin'], TRUE)) $this->_screen('not_allowed', 403, $active);
		if ($need === 'admin' && $role !== 'admin') $this->_screen('not_allowed', 403, $active);
	}

	// Placeholder page: header (sidebar), body, footer.
	private function _page($active, $title, $route, $access, $need, $owner, $note, $links)
	{
		$this->_need($need, $active);
		$this->_render('page', compact('active', 'title', 'route', 'access', 'owner', 'note', 'links'));
	}

	// System screens from the site map: sign in, not allowed, page not found.
	private function _screen($screen, $code, $active = NULL)
	{
		http_response_code($code);   // real status code, like the API will send
		$this->_render('screen', ['active' => $active, 'screen' => $screen, 'title' => 'Salita Quest']);
		exit;                        // stop here so the normal page is not drawn
	}

	private function _render($body, $data)
	{
		$role = $this->_role();
		$data['role'] = $role;
		$data['menu'] = $this->_menu($role);
		$this->call->view('preview/header', $data);
		$this->call->view('preview/' . $body, $data);
		$this->call->view('preview/footer', $data);
	}

	// Sidebar links per role, as in A's wireframe page 2: [label, path].
	private function _menu($role)
	{
		$menu = [['Map', '']];
		if ($role !== 'visitor') $menu[] = ['Passport', 'passport'];
		if (in_array($role, ['contributor', 'admin'], TRUE)) $menu[] = ['Submit a word', 'submit'];
		if ($role === 'admin') {
			$menu[] = ['Pending approvals', 'admin/approvals'];
			$menu[] = ['Regions', 'admin/regions'];
			$menu[] = ['Words', 'admin/words'];
			$menu[] = ['Audio files', 'admin/audio'];
		}
		return $menu;
	}
}
