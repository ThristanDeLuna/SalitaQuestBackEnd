<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!-- Checking 1 overview: the four things the sheet asks us to demonstrate, in its order. -->
<h1>Checking 1: System design and project setup</h1>
<p class="sub">Salita Quest &middot; the four items the checking sheet asks to demonstrate</p>

<?php
// Shows one diagram: an image inline, a PDF as an embedded viewer, or a "not merged yet" note.
$show = function ($name) use ($docs) {
	$d = $docs[$name];
	echo '<div class="panel"><h2>' . e($d['label']) . ' <small class="note">(Member ' . e($d['owner']) . ')</small></h2>';
	if ( ! $d['exists']) {
		echo '<div class="panel grey" style="margin:0">Not in this copy yet: <code>' . e($d['path']) . '</code>. It comes from ' . e($d['from']) . '.</div>';
	} elseif ($d['ext'] === 'pdf') {
		$url = site_url('preview/doc/' . $name);
		echo '<iframe src="' . e($url) . '" style="width:100%;height:560px;border:1px solid #999" title="' . e($d['label']) . '"></iframe>';
		echo '<a class="btn" href="' . e($url) . '" target="_blank" rel="noopener">Open full size</a>';
	} else {
		$url = site_url('preview/doc/' . $name);
		echo '<a href="' . e($url) . '" target="_blank" rel="noopener"><img src="' . e($url) . '" alt="' . e($d['label']) . '" style="max-width:100%;border:1px solid #999"></a>';
		echo '<br><small class="note">Click the image to open it full size.</small>';
	}
	echo '</div>';
};
?>

<h2 id="s1">1. Site map and initial interface designs</h2>
<?php $show('sitemap'); ?>
<?php foreach (['wire-a', 'wire-b', 'wire-c', 'wire-d-submit', 'wire-d-approvals'] as $n) $show($n); ?>
<p class="note">The working versions of C's screens are in the app itself: try them from the Routes list below.</p>

<h2 id="s2">2. Database design</h2>
<?php $show('erd'); ?>
<div class="panel">
	<h2>From design to code: migrations in <code>app/migrations</code></h2>
	<table>
		<tr><th>File</th></tr>
		<?php foreach ($migrations as $f): ?><tr><td><code><?= e($f) ?></code></td></tr><?php endforeach; ?>
	</table>
	<small class="note">Each member has a number range (A 003-009, B 010-019, C 020-029, D 030-039, DEC-09). Files not listed here are still in open pull requests.</small>
</div>

<h2 id="s3">3. Working LavaLust project</h2>
<div class="tiles">
	<div class="tile"><small>Framework</small><b style="font-size:18px"><?= e($lava) ?></b></div>
	<div class="tile"><small>PHP</small><b style="font-size:18px"><?= e(PHP_VERSION) ?></b></div>
	<div class="tile"><small>Environment</small><b style="font-size:18px"><?= e($env) ?></b></div>
</div>
<div class="panel">
	<h2>Controllers in <code>app/controllers</code></h2>
	<?php foreach ($controllers as $f): ?><code><?= e($f) ?></code>&nbsp; <?php endforeach; ?>
	<p class="note">This page itself is rendered by LavaLust: route &rarr; <code>Preview::checking1()</code> &rarr; views in <code>app/views/preview/</code>.</p>
</div>

<h2 id="s4">4. Initial routes and navigation</h2>
<p class="sub">Read from <code>app/config/routes.php</code>, the same way <code>php lava route:list</code> does. Click a route to open it.</p>
<table>
	<tr><th>Method</th><th>URI</th><th>Handler</th><th>Try it</th></tr>
	<?php foreach ($routes as $r): ?>
		<?php
		$uri  = $r['uri'];
		$open = '';
		if (strpos($uri, 'db/') === 0) {
			$open = 'Command line only';                                  // the runner refuses browsers
		} elseif ($r['method'] === 'POST') {
			$open = 'Form submit (from its page)';
		} elseif (strpos($uri, '/preview/doc/') === 0) {
			$open = 'Diagrams above';
		} else {
			$path = str_replace('{region}', 'tagalog', ltrim($uri, '/'));   // sample value for {region}
			$open = '<a class="btn" href="' . e(site_url($path) . (strpos($path, 'preview') === 0 && strpos($path, 'checking-1') === FALSE ? '?as=admin' : '')) . '">Open</a>';
		}
		?>
		<tr>
			<td><?= e($r['method']) ?></td>
			<td><code><?= e($uri) ?></code></td>
			<td><code><?= e($r['handler']) ?></code></td>
			<td><?= $open ?></td>
		</tr>
	<?php endforeach; ?>
</table>
<p class="note">"Open" signs you in as the demo Admin so every page opens. The <code>/preview</code> routes exist only in development; on Render (production) they answer 404.</p>
