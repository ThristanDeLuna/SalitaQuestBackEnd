<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php
/* Result of one game round, shared by matching.php and listening.php.
   $result comes from Game_rules::score_round() plus a review list (Preview::_score). */
$is_matching = $title === 'Matching game';
$again       = pv_url(($is_matching ? 'play/matching/' : 'play/listening/') . $code, $role);
?>
<div class="panel <?= $result['passed'] ? 'ok' : 'bad' ?>">
	<h2><?= $result['passed'] ? 'Passed' : 'Not passed' ?>: score <?= (int) $result['score'] ?> / <?= (int) $result['max_score'] ?> (<?= (int) $result['percent'] ?>%)</h2>
	+<?= (int) $result['score'] ?> points.
	<?php if ( ! $result['passed']): ?>
		This attempt counts for your streak, not for the stamp (you need 60%).
	<?php endif; ?>
</div>

<?php if ($result['new_stamp']): ?>
	<div class="panel ok"><h2>Stamp earned: <?= e($region[0]) ?></h2>You passed both games in this region.</div>
<?php endif; ?>

<table>
	<tr><th><?= e($region[0]) ?></th><th>Your answer</th><th>Correct answer</th><th>Result</th></tr>
	<?php foreach ($result['review'] as $r): ?>
		<tr>
			<td><b><?= e($r['item']) ?></b></td>
			<td><?= e($r['chosen']) ?></td>
			<td><?= e($r['right']) ?></td>
			<td><?= $r['correct'] ? '&#10003; Right' : '&#10007; Wrong' ?></td>
		</tr>
	<?php endforeach; ?>
</table>

<?php if ($result['passed']): ?>
	<a class="btn" href="<?= e($again) ?>">Play again</a>
	<?php if ($is_matching): ?>
		<a class="btn dark" href="<?= e(pv_url('play/listening/' . $code, $role)) ?>">Go to listening quiz</a>
	<?php else: ?>
		<a class="btn dark" href="<?= e(pv_url('passport', $role)) ?>">View passport</a>
	<?php endif; ?>
<?php else: ?>
	<a class="btn dark" href="<?= e($again) ?>">Try again</a>
	<a class="btn" href="<?= e(pv_url('passport', $role)) ?>">View passport</a>
<?php endif; ?>
