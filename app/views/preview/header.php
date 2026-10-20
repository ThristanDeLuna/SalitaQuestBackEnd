<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - Salita Quest preview</title>
<style>
	/* Grayscale, like the wireframes. */
	* { box-sizing: border-box; }
	body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #222; background: #fff; }
	.banner { background: #222; color: #fff; padding: 8px 16px; font-size: 13px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
	.banner a { color: #fff; }
	.banner a.on { font-weight: bold; text-decoration: none; background: #fff; color: #222; padding: 1px 6px; }
	.shell { display: flex; min-height: calc(100vh - 34px); }
	.side { width: 210px; background: #ececec; border-right: 1px solid #333; display: flex; flex-direction: column; }
	.brand { padding: 18px 16px 14px; } .brand b { font-size: 18px; } .brand small { display: block; color: #666; font-size: 11px; margin-top: 2px; }
	.side a.item { display: block; padding: 9px 16px; color: #222; text-decoration: none; font-size: 14px; }
	.side a.item:hover { background: #ddd; }
	.side a.item.on { background: #222; color: #fff; font-weight: bold; }
	.who { margin-top: auto; padding: 16px; font-size: 12px; }
	.btn { display: inline-block; border: 1px solid #333; background: #fff; color: #222; padding: 8px 14px; text-decoration: none; font-size: 13px; font-weight: bold; margin: 4px 6px 4px 0; }
	.btn.dark { background: #ccc; }
	.side .btn { display: block; text-align: center; margin: 8px 0 0; }
	main { flex: 1; padding: 24px 28px; }
	h1 { margin: 0 0 4px; font-size: 26px; }
	h1 + .placeholder { margin-top: 18px; }
	.meta { color: #666; font-size: 12px; margin-bottom: 18px; }
	.meta code { background: #eee; padding: 1px 4px; }
	.placeholder { background: #ececec; border: 1px solid #999; padding: 40px 20px; text-align: center; color: #555; margin-bottom: 18px; }
	.note { color: #666; font-size: 12px; margin-top: 20px; }
	@media (max-width: 640px) { .shell { flex-direction: column; } .side { width: auto; } }
</style>
</head>
<body>
<!-- Demo role switch: NOT a real sign-in. Real login is Google + JWT (DEC-05). -->
<div class="banner">
	<span>Checking 1 preview &middot; demo role (not real sign-in):</span>
	<?php foreach (['visitor', 'learner', 'contributor', 'admin'] as $r): ?>
		<a class="<?= $r === $role ? 'on' : '' ?>" href="<?= e(pv_url('', $r)) ?>"><?= e(ucfirst($r)) ?></a>
	<?php endforeach; ?>
</div>
<div class="shell">
	<nav class="side">
		<div class="brand"><b>Salita Quest</b><small><?= $role === 'visitor' ? 'Not signed in' : e(ucfirst($role)) ?></small></div>
		<?php foreach ($menu as $m): ?>
			<a class="item <?= $m[0] === $active ? 'on' : '' ?>" href="<?= e(pv_url($m[1], $role)) ?>"><?= e($m[0]) ?></a>
		<?php endforeach; ?>
		<div class="who">
			<?php if ($role === 'visitor'): ?>
				Not signed in<br><small>Sign in to play and get stamps</small>
				<a class="btn" href="<?= e(pv_url('', 'learner')) ?>">G&nbsp; Sign in with Google</a>
			<?php else: ?>
				Signed in as Ana (demo)
				<a class="btn" href="<?= e(pv_url('', 'visitor')) ?>">Sign out</a>
			<?php endif; ?>
		</div>
	</nav>
	<main>