<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!-- C's screen 2: listening quiz. One question at a time; all answers are sent together at the end. -->
<h1>Listening quiz</h1>
<p class="sub"><?= e($region[0]) ?> - <?= e($region[1]) ?> - 5 questions, 10 points each</p>

<?php if ($result): ?>
	<?php include __DIR__ . '/_result.php'; ?>
<?php else: ?>
	<form method="post" action="<?= e(pv_url('play/listening/' . $code, $role)) ?>" id="lform">
		<?php $i = 0; $n = count($questions); foreach ($questions as $qid => $q): $i++; ?>
			<div class="panel q">
				<p class="sub">Question <?= $i ?> of <?= $n ?></p>
				<p>Listen, then pick the meaning.</p>
				<!-- No recordings yet (Cloudinary comes later), so the button shows the word instead. -->
				<details class="audio">
					<summary>&#9654; Play audio</summary>
					<p>No recording in the preview yet. The word is: <b><?= e($q['word']) ?></b></p>
				</details>
				<div class="cols" style="margin-top:12px">
					<?php foreach ($q['options'] as $token => $meaning): ?>
						<label class="pick"><span><input type="radio" name="answer[<?= (int) $qid ?>]" value="<?= e($token) ?>"> <?= e($meaning) ?></span></label>
					<?php endforeach; ?>
				</div>
				<?php if ($i < $n): ?>
					<button type="button" class="btn next">Next question</button>
				<?php else: ?>
					<button type="submit" class="btn dark">Check answers</button>
				<?php endif; ?>
				<small class="note">Answers are sent to the server together at the end.</small>
			</div>
		<?php endforeach; ?>
	</form>

	<script>
	// Show one question at a time. Without JavaScript all questions show and the form still works.
	(function () {
		var qs = [].slice.call(document.querySelectorAll('#lform .q'));
		function show(n) { qs.forEach(function (q, i) { q.style.display = i === n ? '' : 'none'; }); }
		qs.forEach(function (q, i) {
			var next = q.querySelector('.next');
			if (next) next.addEventListener('click', function () { show(i + 1); });
		});
		// Mark the chosen option, like "Selected" in the wireframe.
		document.getElementById('lform').addEventListener('change', function (ev) {
			var group = ev.target.name;
			[].slice.call(document.querySelectorAll('input[name="' + group + '"]')).forEach(function (r) {
				r.closest('label').classList.toggle('on', r.checked);
			});
		});
		show(0);
	})();
	</script>
<?php endif; ?>
