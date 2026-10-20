<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!-- System screens from the site map (grey boxes). $screen is set by the controller, never from the URL. -->
<?php if ($screen === 'sign_in'): ?>
	<h1>Sign in to continue</h1>
	<div class="placeholder">This page needs a Salita Quest account.<br><small>(HTTP 401)</small></div>
	<a class="btn dark" href="<?= e(pv_url('', 'learner')) ?>">G&nbsp; Sign in with Google</a>
<?php elseif ($screen === 'not_allowed'): ?>
	<h1>Not allowed</h1>
	<div class="placeholder">You do not have access to this page. Your account role cannot open it.<br><small>(HTTP 403)</small></div>
	<a class="btn dark" href="<?= e(pv_url('', $role)) ?>">Go to map</a>
<?php else: ?>
	<h1>Page not found</h1>
	<div class="placeholder">Check the address, or go back to the map.<br><small>(HTTP 404)</small></div>
	<a class="btn dark" href="<?= e(pv_url('', $role)) ?>">Go to map</a>
<?php endif; ?>
