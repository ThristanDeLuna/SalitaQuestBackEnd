<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| Game settings (used by the games controllers)
| -------------------------------------------------------------------
| Loaded with $this->config->load('games') and read with
| config_item('game_regions'), the same way as app/config/submissions.php.
| Nothing secret is written in this file.
*/

// The five region codes the games use (DEC-32, CF-01).
// A code with no row in the regions table is ignored by the games (Games::active_regions()).
// For now the list lives here; B's regions endpoints will own it later.
$config['game_regions'] = ['tagalog', 'cebuano', 'hiligaynon', 'kapampangan', 'ilocano'];
