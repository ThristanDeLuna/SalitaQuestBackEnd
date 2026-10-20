<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - Salita Quest preview</title>
<style>
	/* Grayscale, like the wireframes. Real colors come later from the UI Design Guide. */
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
	/* C's screens: panels, tables, game buttons (shapes from C's wireframes) */
	.sub { color: #666; font-size: 13px; margin: 0 0 14px; }
	.panel { border: 1px solid #333; padding: 14px 16px; margin: 0 0 16px; background: #fff; }
	.panel h2 { margin: 0 0 8px; font-size: 16px; }
	.panel.ok { border-width: 2px; }
	.panel.bad { border: 2px solid #a00; color: #a00; }
	.panel.grey { background: #ececec; }
	table { border-collapse: collapse; width: 100%; font-size: 13px; margin-bottom: 14px; }
	th { background: #ddd; text-align: left; font-size: 11px; text-transform: uppercase; }
	th, td { border: 1px solid #999; padding: 8px 10px; }
	.cols { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; max-width: 640px; margin-bottom: 14px; }
	.cols h3 { margin: 0 0 6px; font-size: 12px; color: #555; }
	button.pick, label.pick { display: flex; justify-content: space-between; align-items: center; width: 100%; padding: 10px 12px; margin: 0 0 8px;
		border: 1px solid #333; background: #fff; font: inherit; font-size: 14px; cursor: pointer; text-align: left; }
	button.pick.on, label.pick.on { background: #ccc; font-weight: bold; }
	.tag { display: inline-block; min-width: 22px; text-align: center; background: #222; color: #fff; font-size: 11px; font-weight: bold; padding: 2px 4px; }
	.tag:empty { background: transparent; }
	.tiles { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
	.tile { border: 1px solid #333; padding: 12px 16px; min-width: 150px; }
	.tile small { display: block; font-size: 11px; color: #555; text-transform: uppercase; } .tile b { font-size: 26px; }
	button.btn { cursor: pointer; font-family: inherit; }
	button.btn:disabled { color: #999; border-color: #bbb; cursor: not-allowed; }
	details.audio summary { display: inline-block; border: 1px solid #333; padding: 7px 12px; cursor: pointer; font-weight: bold; font-size: 13px; list-style: none; }
	details.audio p { margin: 8px 0 0; font-size: 13px; color: #555; }
	.inline-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
	@media (max-width: 640px) { .shell { flex-direction: column; } .side { width: auto; } }
</style>
</head>
<body>
<!-- Demo role switch: NOT a real sign-in. Real login is Google + JWT (DEC-05). -->
<div class="banner">
	<a href="<?= e(site_url('preview/checking-1')) ?>"><b>Checking 1 overview</b></a>
	<span>&middot; demo role (not real sign-in):</span>
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
