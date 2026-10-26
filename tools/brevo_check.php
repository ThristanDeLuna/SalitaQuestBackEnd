<?php
// Sends ONE test email through Brevo (run on YOUR computer):
//   php tools/brevo_check.php your.own.email@gmail.com
// Reads BREVO_API_KEY and BREVO_SENDER_EMAIL from the local .env (git-ignored).
// Prints only the HTTP status and Brevo's messageId, never the key.
$env = [];
foreach (@file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if ($line[0] !== '#' && strpos($line, '=') !== false) {
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\"'");
    }
}
$to = $argv[1] ?? '';
if (empty($env['BREVO_API_KEY']) || empty($env['BREVO_SENDER_EMAIL']) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    exit("Need BREVO_API_KEY and BREVO_SENDER_EMAIL in .env and an email address as argument.\n");
}
$payload = [
    'sender' => ['email' => $env['BREVO_SENDER_EMAIL'], 'name' => 'Salita Quest'],
    'to' => [['email' => $to]],
    'subject' => 'Salita Quest test email',
    'htmlContent' => '<p>If you can read this, Brevo works. Check: was it in the inbox or in spam?</p>',
];
$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['api-key: ' . $env['BREVO_API_KEY'], 'content-type: application/json', 'accept: application/json']]);
$body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
echo "HTTP $code\n" . $body . "\n(201 + messageId = Brevo accepted it; then check your inbox AND spam)\n";
