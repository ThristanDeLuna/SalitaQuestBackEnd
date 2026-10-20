<?php
defined('PREVENT_DIRECT_ACCESS') or exit('No direct script access allowed');
/**
 * ------------------------------------------------------------------
 * LavaLust - an opensource lightweight PHP MVC Framework
 * ------------------------------------------------------------------
 *
 * MIT License
 *
 * Copyright (c) 2020 Ronald M. Marasigan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package LavaLust
 * @author Ronald M. Marasigan <ronald.marasigan@yahoo.com>
 * @since Version 1
 * @link https://github.com/ronmarasigan/LavaLust
 * @license https://opensource.org/licenses/MIT MIT License
 */

/*
| -------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------
| Here is where you can register web routes for your application.
|
|
*/
/** @var object $router **/

$router->get('/', 'Welcome::index');
// ---- Member A: Checking 1 preview pages (remove when the React app exists) ----
// Development only, same rule as config.php: a missing APP_ENV counts as development.
// On Render APP_ENV=production, so these routes do not exist there (404).
if ((getenv('APP_ENV') ?: 'development') === 'development') {
	$router->get('/preview', 'Preview::index');
	$router->get('/preview/regions/{region}', 'Preview::region');
	$router->get('/preview/play/matching/{region}', 'Preview::matching');
	$router->get('/preview/play/listening/{region}', 'Preview::listening');
	$router->get('/preview/passport', 'Preview::passport');
	$router->get('/preview/submit', 'Preview::submit');
	$router->get('/preview/admin/approvals', 'Preview::approvals');
	$router->get('/preview/admin/regions', 'Preview::regions');
	$router->get('/preview/admin/words', 'Preview::words');
	$router->get('/preview/admin/audio', 'Preview::audio');
}
// ---- end Member A preview ----
$router->get('db/migrate',  'MigrationController::migrate');
$router->get('db/status',   'MigrationController::status');
$router->get('db/rollback', 'MigrationController::rollback');
