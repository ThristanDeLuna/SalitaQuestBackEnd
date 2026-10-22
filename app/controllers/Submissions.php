<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Member D: submissions (STUBS for Checking 1).
 * Every method only returns fixed JSON. No database, no login check yet.
 * Later: extend A's ApiController (DEC-26), add role checks, call the models,
 * and answer with the Api library's respond() / respond_error() (DEC-28).
 */
class Submissions extends Controller {

	// Plain JSON helper for the stubs. Replace with the Api library later.
	private function _json($data, $code = 200)
	{
		http_response_code($code);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		exit;
	}

	// POST /api/submissions  (Contributor or Admin): send a word for review.
	public function create()
	{
		$this->_json([
			'stub'   => true,
			'id'     => 0,
			'status' => 'pending',
		], 201);
	}

	// GET /api/submissions  (Contributor or Admin): my own submissions.
	public function mine()
	{
		$this->_json(['stub' => true, 'data' => []]);
	}

	// GET /api/admin/submissions?status=pending  (Admin): the approval queue.
	public function admin_list()
	{
		$status = isset($_GET['status']) ? $_GET['status'] : 'pending';
		$this->_json(['stub' => true, 'status' => $status, 'data' => []]);
	}

	// POST /api/admin/submissions/{id}/approve  (Admin): adds a row to words, then emails.
	public function approve($id)
	{
		$this->_json(['stub' => true, 'id' => (int) $id, 'status' => 'approved']);
	}

	// POST /api/admin/submissions/{id}/reject  (Admin): marks rejected, then emails.
	public function reject($id)
	{
		$this->_json(['stub' => true, 'id' => (int) $id, 'status' => 'rejected']);
	}
}
