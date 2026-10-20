<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!-- C's screen 4: admin audio files. The file is really checked, but not kept (no Cloudinary yet). -->
<h1>Audio files</h1>
<p class="sub">Upload one pronunciation recording per word</p>

<form method="get" action="<?= e(pv_url('admin/audio', $role)) ?>" class="inline-form" style="margin-bottom:14px">
	<input type="hidden" name="as" value="<?= e($role) ?>">
	<label>Region
		<select name="region" onchange="this.form.submit()">
			<option value="">All regions</option>
			<?php foreach ($region_names as $code => $name): ?>
				<option value="<?= e($code) ?>" <?= $code === $filter ? 'selected' : '' ?>><?= e($name) ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<noscript><button class="btn" type="submit">Filter</button></noscript>
	<span class="panel grey" style="margin:0;padding:6px 10px"><b><?= (int) $missing ?> word<?= $missing === 1 ? '' : 's' ?> without audio</b></span>
</form>

<?php if ($message): ?><div class="panel ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="panel bad"><?= e($error) ?></div><?php endif; ?>

<?php $back = pv_url('admin/audio', $role, $filter !== '' ? ['region' => $filter] : []); ?>
<?php if ($open): ?>
	<!-- Upload panel: opens when Upload or Replace is pressed (?upload=word id). -->
	<div class="panel">
		<h2>Upload audio for <?= e($open['word']) ?> (<?= e($region_names[$open['region']]) ?>)</h2>
		<form method="post" enctype="multipart/form-data" action="<?= e($back) ?>" class="inline-form">
			<input type="hidden" name="MAX_FILE_SIZE" value="2097152">
			<input type="hidden" name="word_id" value="<?= (int) $open['id'] ?>">
			<input type="file" name="audio" accept=".mp3,.m4a,.wav,audio/*" required>
			<button class="btn dark" type="submit">Upload</button>
			<a class="btn" href="<?= e($back) ?>">Cancel</a>
		</form>
		<small class="note">mp3, m4a or wav, up to 2 MB (C's proposal).</small>
	</div>
<?php endif; ?>

<table>
	<tr><th>Word</th><th>Region</th><th>Audio</th><th>Action</th></tr>
	<?php foreach ($rows as $r): ?>
		<?php $link = pv_url('admin/audio', $role, array_merge($filter !== '' ? ['region' => $filter] : [], ['upload' => $r['id']])); ?>
		<tr>
			<td><b><?= e($r['word']) ?></b></td>
			<td><?= e($r['region']) ?></td>
			<td><?= $r['has'] ? 'Uploaded' : 'No audio' ?></td>
			<td>
				<?php if ($r['has']): ?>
					<button class="btn" disabled title="No real recordings in the preview">Play</button>
					<a class="btn" href="<?= e($link) ?>">Replace</a>
				<?php else: ?>
					<a class="btn dark" href="<?= e($link) ?>">Upload</a>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
</table>
