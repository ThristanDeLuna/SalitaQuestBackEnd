<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!-- C's screen 1: matching game. The answer key is in the session; this page only has tokens. -->
<h1>Matching game</h1>
<p class="sub"><?= e($region[0]) ?> - <?= e($region[1]) ?> - 4 pairs, 10 points each</p>

<?php if ($result): ?>
	<?php include __DIR__ . '/_result.php'; ?>
<?php else: ?>
	<p>Pick a <?= e($region[0]) ?> word, then pick its English meaning. Letters show your pairs.</p>
	<form method="post" action="<?= e(pv_url('play/matching/' . $code, $role)) ?>" id="mform">
		<div class="cols">
			<div>
				<h3><?= e(strtoupper($region[0])) ?></h3>
				<?php foreach ($left as $id => $w): ?>
					<button type="button" class="pick" data-word="<?= (int) $id ?>"><span><?= e($w) ?></span><span class="tag"></span></button>
					<!-- Filled in by the script below: pair[word id] = token of the chosen meaning -->
					<input type="hidden" name="pair[<?= (int) $id ?>]" id="pair-<?= (int) $id ?>" value="">
				<?php endforeach; ?>
			</div>
			<div>
				<h3>ENGLISH</h3>
				<?php foreach ($right as $token => $meaning): ?>
					<button type="button" class="pick" data-opt="<?= e($token) ?>"><span><?= e($meaning) ?></span><span class="tag"></span></button>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="inline-form">
			<button class="btn dark" type="submit">Check answers</button>
			<span id="count">0 of <?= count($left) ?> paired.</span>
			<small class="note">The result appears after Check answers.</small>
		</div>
	</form>
	<noscript><p class="note">Pairing needs JavaScript switched on.</p></noscript>

	<script>
	// Click a word, then a meaning: they get the same letter (A-D).
	// The script only fills the hidden inputs; the server does all the scoring.
	(function () {
		var form = document.getElementById('mform');
		var words = [].slice.call(form.querySelectorAll('[data-word]'));
		var opts = [].slice.call(form.querySelectorAll('[data-opt]'));
		var letters = 'ABCDEFGH', picked = null, pairs = {};   // pairs: word id -> token

		function draw() {
			var n = 0;
			opts.forEach(function (o) { o.querySelector('.tag').textContent = ''; });
			words.forEach(function (w, i) {
				var id = w.dataset.word, token = pairs[id] || '';
				document.getElementById('pair-' + id).value = token;
				w.querySelector('.tag').textContent = token ? letters[i] : '';
				w.classList.toggle('on', id === picked);
				if (token) {
					n++;
					opts.forEach(function (o) { if (o.dataset.opt === token) o.querySelector('.tag').textContent = letters[i]; });
				}
			});
			document.getElementById('count').textContent = n + ' of ' + words.length + ' paired.';
		}
		words.forEach(function (w) {
			w.addEventListener('click', function () { picked = w.dataset.word; draw(); });
		});
		opts.forEach(function (o) {
			o.addEventListener('click', function () {
				if (!picked) return;                                  // pick a word first
				for (var k in pairs) { if (pairs[k] === o.dataset.opt) delete pairs[k]; }  // one word per meaning
				pairs[picked] = o.dataset.opt;
				picked = null;
				draw();
			});
		});
		draw();
	})();
	</script>
<?php endif; ?>
