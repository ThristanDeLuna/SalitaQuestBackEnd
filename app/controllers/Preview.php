<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Member A: Checking 1 preview of the site map (DEC-38, DEC-51).
 * Placeholder pages only: no database, no real login.
 * "?as=admin" only pretends to be a role so the menu can be shown.
 * The real pages will be React (DEC-01); the real role check is in the API (DEC-23).
 */
class Preview extends Controller {

	// Only these values are accepted; anything else counts as "visitor" or "not found".
	private $roles   = ['visitor', 'learner', 'contributor', 'admin'];
	private $regions = [   // region codes from DEC-43; static until Checking 2
		'tagalog'    => 'Tagalog',
		'cebuano'    => 'Cebuano (Bisaya)',
		'hiligaynon' => 'Hiligaynon',
	];

	public function __construct()
	{
		parent::__construct();
		$this->call->helper(['url', 'preview']);   // nothing is autoloaded (DEC-11)
	}

	// GET /preview  - Map (everyone)
	public function index()
	{
		$links = [];
		foreach ($this->regions as $code => $name) {
			$links[] = ['Open ' . $name, 'regions/' . $code];
		}
		$this->_page('Map', 'Choose a region', '/', 'everyone', null, 'B / team',
			'The Leaflet map with three pins goes here. Each pin opens its word list.', $links);
	}

	// GET /preview/regions/{region}  - Word list (everyone)
	public function region($code)
	{
		$name  = $this->_region($code);
		$links = [
			['Play matching game',  'play/matching/' . $code],
			['Play listening quiz', 'play/listening/' . $code],
		];
		// DEC-46: "Submit a word" is hidden from visitors and Learners.
		if (in_array($this->_role(), ['contributor', 'admin'], true)) {
			$links[] = ['Submit a new word', 'submit'];
		}
		$this->_page('Map', 'Word list: ' . $name, '/regions/' . $code, 'everyone', null, 'B',
			'Table of approved words: Word, Translation, Example sentence, Audio.', $links);
	}

	// GET /preview/play/matching/{region}  - signed in
	public function matching($code)
	{
		$name = $this->_region($code);
		$this->_page('Map', 'Matching game: ' . $name, '/play/matching/' . $code, 'signed in', 'signed_in', 'C',
			'4 pairs, 10 points each. The server checks the answers.', [
				['Switch to listening quiz', 'play/listening/' . $code],
				['View passport', 'passport'],
			]);
	}

	// GET /preview/play/listening/{region}  - signed in
	public function listening($code)
	{
		$name = $this->_region($code);
		$this->_page('Map', 'Listening quiz: ' . $name, '/play/listening/' . $code, 'signed in', 'signed_in', 'C',
			'5 questions, 10 points each. Audio comes from Cloudinary.', [
				['View passport', 'passport'],
			]);
	}

	// GET /preview/passport  - signed in
	public function passport()
	{
		$this->_page('Passport', 'My passport', '/passport', 'signed in', 'signed_in', 'C',
			'Points, streak, stamps and progress per region.', [['Back to map', '']]);
	}

	// GET /preview/submit  - Contributor and Admin
	public function submit()
	{
		$this->_page('Submit a word', 'Submit a word', '/submit', 'Contributor and Admin', 'contributor', 'D',
			'Form: word, language, suggested translation (from MyMemory). Goes to the approval queue.',
			[['Send (returns to map)', '']]);
	}

	// Admin only pages
	public function approvals() { $this->_admin('Pending approvals', '/admin/approvals', 'D', 'Approve or reject submitted words; the contributor gets an email (Brevo).'); }
	public function regions()   { $this->_admin('Regions', '/admin/regions', 'B', 'List of regions; the add and edit form opens on this page.'); }
	public function words()     { $this->_admin('Words', '/admin/words', 'B', 'List of words; the add and edit form opens on this page.'); }
	public function audio()     { $this->_admin('Audio files', '/admin/audio', 'C', 'Upload one recording per word to Cloudinary.'); }

	// ---------- shared code ----------

	private function _admin($title, $route, $owner, $note)
	{
		$this->_page($title, $title, $route, 'Admin only', 'admin', $owner, $note, [['Back to map', '']]);
	}

	// Demo role from ?as=..., checked against the list; default is a signed-out visitor.
	private function _role()
	{
		$as = isset($_GET['as']) ? (string) $_GET['as'] : 'visitor';
		return in_array($as, $this->roles, true) ? $as : 'visitor';
	}

	// Unknown region code -> "Page not found".
	private function _region($code)
	{
		if ( ! isset($this->regions[$code])) {
			$this->_screen('not_found', 404);
		}
		return $this->regions[$code];
	}

	// Access rule for one page (DEC-46). Returns the screen to show, or null if allowed.
	private function _blocked($need, $role)
	{
		if ($need === null) return null;                              // public page
		if ($role === 'visitor') return 'sign_in';                    // must sign in first
		if ($need === 'contributor' && ! in_array($role, ['contributor', 'admin'], true)) return 'not_allowed';
		if ($need === 'admin' && $role !== 'admin') return 'not_allowed';
		return null;                                                  // signed-in pages: any role
	}

	// Render one page: header (sidebar), body, footer.
	private function _page($active, $title, $route, $access, $need, $owner, $note, $links)
	{
		$role    = $this->_role();
		$blocked = $this->_blocked($need, $role);
		if ($blocked !== null) {
			$this->_screen($blocked, $blocked === 'sign_in' ? 401 : 403, $active);
		}
		$this->_render('page', compact('active', 'title', 'route', 'access', 'owner', 'note', 'links'));
	}

	// System screens from the site map: sign in, not allowed, page not found.
	private function _screen($screen, $code, $active = null)
	{
		http_response_code($code);   // real status code, like the API will send
		$this->_render('screen', ['active' => $active, 'screen' => $screen, 'title' => 'Salita Quest']);
		exit;                        // stop so the normal page is not drawn
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
		if (in_array($role, ['contributor', 'admin'], true)) $menu[] = ['Submit a word', 'submit'];
		if ($role === 'admin') {
			$menu[] = ['Pending approvals', 'admin/approvals'];
			$menu[] = ['Regions', 'admin/regions'];
			$menu[] = ['Words', 'admin/words'];
			$menu[] = ['Audio files', 'admin/audio'];
		}
		return $menu;
	}
}