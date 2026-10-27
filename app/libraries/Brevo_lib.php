<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Brevo_lib (Member D): sends one transactional email through the Brevo
 * HTTP API (DEC-06: not SMTP, because SMTP may be blocked on Render).
 *
 * Settings are in app/config/brevo.php. Its secret values come from environment
 * variables (values live only in .env and Render, never in Git):
 *   BREVO_API_KEY, BREVO_SENDER_EMAIL (must be a verified sender in Brevo),
 *   BREVO_SENDER_NAME (optional, default "Salita Quest").
 *
 * Load:  $this->call->library('brevo_lib');
 * Use:   $mail = $this->brevo_lib->approved_email($word);
 *        $ok   = $this->brevo_lib->send($email, $name, $mail['subject'], $mail['html']);
 * send() returns TRUE/FALSE and never throws. A failed email must not undo an
 * approval, so the caller just records the failure.
 */
class Brevo_lib
{
    private $_lava;
    private $url;           // endpoint, from app/config/brevo.php
    private $api_key;       // never log or return it
    private $sender_email;  // verified sender in Brevo
    private $sender_name;
    private $timeout;       // seconds to wait for Brevo

    public function __construct()
    {
        $this->_lava = lava_instance();
        // Load app/config/brevo.php so config_item() can read its keys
        $this->_lava->config->load('brevo');
        $this->url          = config_item('brevo_url');
        $this->api_key      = (string) config_item('brevo_api_key');
        $this->sender_email = (string) config_item('brevo_sender_email');
        $this->sender_name  = (string) config_item('brevo_sender_name');
        // "?: 8" matters: a timeout of 0 would make curl wait forever
        $this->timeout      = (int) config_item('brevo_timeout') ?: 8;
    }

    public function send(string $to_email, string $to_name, string $subject, string $html): bool
    {
        // Missing key or sender (not configured) or a bad address: do not call Brevo
        if ($this->api_key === '' || $this->sender_email === ''
            || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $payload = $this->build_payload($this->sender_email, $this->sender_name ?: 'Salita Quest',
                                        $to_email, $to_name, $subject, $html);

        $start = microtime(true);
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['api-key: ' . $this->api_key, 'content-type: application/json',
                                       'accept: application/json'],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ms = (int) round((microtime(true) - $start) * 1000);

        $ok = $this->is_success($status, is_string($body) ? $body : null);
        // Never log the key, the recipient or the message.
        $this->_log('api.brevo.com/v3/smtp/email', $status ?: null, $ok, $ms);
        return $ok;
    }

    /** Pure: the JSON body Brevo expects (sender, to, subject, htmlContent). */
    public function build_payload(string $sender_email, string $sender_name,
                                  string $to_email, string $to_name,
                                  string $subject, string $html): array
    {
        return [
            'sender'      => ['email' => $sender_email, 'name' => $sender_name],
            'to'          => [['email' => $to_email, 'name' => $to_name !== '' ? $to_name : $to_email]],
            'subject'     => $subject,
            'htmlContent' => $html,
        ];
    }

    /** Pure: Brevo answers 201 with a messageId when it accepted the email. */
    public function is_success(int $http_status, ?string $body): bool
    {
        if ($http_status !== 201 || $body === null) {
            return false;
        }
        $data = json_decode($body, true);
        return is_array($data) && !empty($data['messageId']);
    }

    /** Email text when an admin approves a word. $word is user input: escaped. */
    public function approved_email(string $word): array
    {
        $w = htmlspecialchars($word, ENT_QUOTES, 'UTF-8');
        return [
            'subject' => 'Your word was approved on Salita Quest',
            'html'    => "<p>Good news! Your suggested word <strong>{$w}</strong> was approved "
                       . "and is now in the word list.</p><p>Salamat for helping others learn!</p>",
        ];
    }

    /** Email text when an admin rejects a word. $reason is optional (can be ''). */
    public function rejected_email(string $word, string $reason = ''): array
    {
        $w = htmlspecialchars($word, ENT_QUOTES, 'UTF-8');
        $r = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        $why = $r !== '' ? "<p>Reason from the admin: {$r}</p>" : '';
        return [
            'subject' => 'Your word was not approved on Salita Quest',
            'html'    => "<p>Thank you for suggesting <strong>{$w}</strong>. "
                       . "An admin could not approve it this time.</p>{$why}"
                       . "<p>You are welcome to suggest another word.</p>",
        ];
    }

    private function _log(string $endpoint, ?int $status, bool $ok, int $ms): void
    {
        try {
            $this->_lava->call->library('api_logger');
            $this->_lava->api_logger->log('brevo', $endpoint, $status, $ok, $ms);
        } catch (Throwable $e) {
            error_log('brevo call not logged'); // api_logger missing (PR #8 not merged yet)
        }
    }
}
