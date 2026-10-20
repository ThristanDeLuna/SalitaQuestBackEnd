<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!-- One placeholder page from the site map. Values are set in Preview::_page(). -->
<h1><?= e($title) ?></h1>
<div class="meta">
	Planned route <code><?= e($route) ?></code> &middot; Access: <?= e($access) ?> &middot; Owner: <?= e($owner) ?>
</div>
<div class="placeholder"><?= e($note) ?><br><small>(placeholder &mdash; real content in Checking 2 and 3)</small></div>
<?php foreach ($links as $l): ?>
	<a class="btn" href="<?= e(pv_url($l[1], $role)) ?>"><?= e($l[0]) ?></a>
<?php endforeach; ?>
