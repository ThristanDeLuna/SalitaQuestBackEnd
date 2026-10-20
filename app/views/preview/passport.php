<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!-- C's screen 3: my passport. Numbers come from this browser's demo progress (session). -->
<h1>My passport</h1>
<p class="sub">Your points, stamps and game progress</p>

<div class="tiles">
	<div class="tile"><small>Total points</small><b><?= (int) $points ?></b></div>
	<div class="tile"><small>Streak</small><b><?= (int) $streak['current'] ?> day<?= (int) $streak['current'] === 1 ? '' : 's' ?></b><br><small>Longest: <?= (int) $streak['longest'] ?> days</small></div>
	<div class="tile"><small>Stamps</small><b><?= (int) $stamps ?> of <?= (int) $total_regions ?></b></div>
</div>

<?php if ($wotd): ?>
	<div class="panel">
		<small>WORD OF THE DAY - <?= e(strtoupper($wotd_region)) ?></small>
		<h2><?= e($wotd['word']) ?> - <?= e(strtolower($wotd['translation'])) ?></h2>
		<details class="audio"><summary>&#9654; Play audio</summary><p>No recording in the preview yet.</p></details>
	</div>
<?php endif; ?>

<table>
	<tr><th>Region</th><th>Words available</th><th>Matching</th><th>Listening</th><th>Stamp</th></tr>
	<?php foreach ($rows as $r): ?>
		<tr>
			<td><b><?= e($r['name']) ?></b></td>
			<td><?= e($r['available']) ?></td>
			<td><?= e($r['matching']) ?></td>
			<td><?= e($r['listening']) ?></td>
			<td><?= e($r['stamp']) ?></td>
		</tr>
	<?php endforeach; ?>
</table>
<p class="note">A region with too few words or recordings shows its games as unavailable.</p>

<form method="post" action="<?= e(pv_url('passport/reset', $role)) ?>" class="inline-form">
	<button class="btn" type="submit">Reset demo progress</button>
	<small class="note">Progress is kept in this browser only (preview). The real passport reads the database.</small>
</form>
