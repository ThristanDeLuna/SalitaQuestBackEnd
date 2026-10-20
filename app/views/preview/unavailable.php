<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!-- "Not available yet" state from C's wireframes: too few words or recordings. -->
<h1><?= e($title) ?></h1>
<p class="sub"><?= e($region[0]) ?> - <?= e($region[1]) ?></p>
<div class="panel grey"><h2>Not available yet</h2><?= e($unavailable) ?></div>
<a class="btn" href="<?= e(pv_url('regions/' . $code, $role)) ?>">Back to word list</a>
<a class="btn" href="<?= e(pv_url('passport', $role)) ?>">View passport</a>
