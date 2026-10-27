<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Mymemory_lib (Member D): asks MyMemory for a SUGGESTED English meaning of a
 * word written in one of our three languages.
 *
 * Load it in a controller:  $this->call->library('mymemory_lib');
 * Use it:                   $text = $this->mymemory_lib->suggest('salamat', 'cebuano');
 *
 * suggest() returns the suggestion, or NULL on ANY problem. The caller must
 * treat NULL as "no suggestion" and leave the box empty for the contributor
 * (DEC-07: MyMemory is weak for Bisaya and Hiligaynon, so an admin checks).
 * Every call writes one api_logs row. The word itself is never logged.
 */
class Mymemory_lib
{
    // Our region code => MyMemory language code (RFC 3066 / ISO).
    // Checked on Oct 24 with tools/mymemory_check.php: MyMemory accepts tl, ceb and hil.
    const LANG = ['tagalog' => 'tl', 'cebuano' => 'ceb', 'hiligaynon' => 'hil'];

    private $_lava;
    private $url;      // endpoint, from app/config/mymemory.php
    private $email;    // optional contact email ('' if not set)
    private $timeout;  // seconds to wait for MyMemory

    public function __construct()
    {
        $this->_lava = lava_instance();
        // Load app/config/mymemory.php so config_item() can read its keys
        $this->_lava->config->load('mymemory');
        $this->url     = config_item('mymemory_url');
        $this->email   = config_item('mymemory_email');
        // "?: 6" matters: a timeout of 0 would make curl wait forever
        $this->timeout = (int) config_item('mymemory_timeout') ?: 6;
    }

    /** Suggested English meaning of $word, or NULL. */
    public function suggest(string $word, string $region_code): ?string
    {
        $word = trim($word);
        // MyMemory accepts at most 500 bytes in q; we only want short words.
        if ($word === '' || strlen($word) > 200 || !isset(self::LANG[$region_code])) {
            return null;
        }

        $params = [
            'q'        => $word,
            'langpair' => self::LANG[$region_code] . '|en', // regional language -> English
        ];
        // Optional contact email (MyMemory's "de" parameter), from app/config/mymemory.php
        if ($this->email) {
            $params['de'] = $this->email;
        }

        $start = microtime(true);
        $ch = curl_init($this->url . '?' . http_build_query($params));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $this->timeout]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ms = (int) round((microtime(true) - $start) * 1000);

        $text = $this->parse_response(is_string($body) ? $body : null, $status, $word);

        // Host and path only: the query string holds the word.
        $this->_log('api.mymemory.translated.net/get', $status ?: null, $text !== null, $ms);

        return $text;
    }

    /**
     * Pure function (no network), so it can be unit tested.
     * Returns the translated text or NULL if the answer is unusable.
     */
    public function parse_response(?string $body, int $http_status, string $word): ?string
    {
        if ($http_status !== 200 || $body === null) {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }
        // MyMemory repeats its own status inside the JSON (number or string).
        if ((int) ($data['responseStatus'] ?? 0) !== 200) {
            return null;
        }
        $text = $data['responseData']['translatedText'] ?? null;
        if (!is_string($text)) {
            return null;
        }
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        // MyMemory sends some error messages as if they were translations, with
        // status 200. Seen in a real test: "Please, specify two different
        // languages". The quota message is not seen yet (assumed MYMEMORY WARNING).
        foreach (['MYMEMORY WARNING', 'PLEASE, SPECIFY', 'INVALID LANGUAGE'] as $bad) {
            if (stripos($text, $bad) !== false) {
                return null;
            }
        }
        // Same text back means it did not translate anything.
        if (strcasecmp($text, trim($word)) === 0) {
            return null;
        }
        return $text;
    }

    private function _log(string $endpoint, ?int $status, bool $ok, int $ms): void
    {
        try {
            $this->_lava->call->library('api_logger');
            $this->_lava->api_logger->log('mymemory', $endpoint, $status, $ok, $ms);
        } catch (Throwable $e) {
            error_log('mymemory call not logged'); // api_logger missing (PR #8 not merged yet)
        }
    }
}