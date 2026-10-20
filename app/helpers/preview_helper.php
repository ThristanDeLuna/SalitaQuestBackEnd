<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Member A: helpers for the Checking 1 preview pages only.
 * Delete this file together with Preview.php when the React app exists.
 */

// Escape text before printing it in HTML (stops injected <script> tags).
if ( ! function_exists('e')) {
	function e($text)
	{
		return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
	}
}

// Build a preview link and keep the demo role (?as=...) in it.
// Example: pv_url('regions/tagalog', 'admin') -> /preview/regions/tagalog?as=admin
if ( ! function_exists('pv_url')) {
	function pv_url($path, $role)
	{
		$url = site_url('preview' . ($path === '' ? '' : '/' . $path));
		return $role === 'visitor' ? $url : $url . '?as=' . urlencode($role);
	}
}