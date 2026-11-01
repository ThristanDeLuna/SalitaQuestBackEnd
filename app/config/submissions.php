<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

// Rules for contributor suggestions. Read with config_item('submissions_...').
$config['submissions_regions']      = ['tagalog', 'cebuano', 'hiligaynon']; // allowed region codes
$config['submissions_max_pending']  = 20;   // per user; more answers 429
$config['submissions_word_max']     = 100;  // matches the column size
$config['submissions_meaning_max']  = 255;