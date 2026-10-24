<?php
// One-off check of MyMemory for our three languages (run on YOUR computer):
//   php tools/mymemory_check.php
// Prints what MyMemory answers so you can judge the quality. It sends only the
// short words below, nothing private. Waits 1 second between calls.
$tests = [
    // [region, language code, regional word, English concept]
    ['tagalog',    'tl',  'Salamat', 'Thank you'], ['tagalog',    'tl',  'Tubig',  'Water'],
    ['tagalog',    'tl',  'Maganda', 'Beautiful'], ['tagalog',    'tl',  'Kain',   'Eat'],
    ['cebuano',    'ceb', 'Salamat', 'Thank you'], ['cebuano',    'ceb', 'Tubig',  'Water'],
    ['cebuano',    'ceb', 'Nindot',  'Beautiful'], ['cebuano',    'ceb', 'Kaon',   'Eat'],
    ['hiligaynon', 'hil', 'Salamat', 'Thank you'], ['hiligaynon', 'hil', 'Tubig',  'Water'],
    ['hiligaynon', 'hil', 'Matahom', 'Beautiful'], ['hiligaynon', 'hil', 'Kaon',   'Eat'],
];
function ask($q, $pair) {
    $url = 'https://api.mymemory.translated.net/get?' . http_build_query(['q' => $q, 'langpair' => $pair]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $d = json_decode((string) $body, true);
    $text = $d['responseData']['translatedText'] ?? '(no text)';
    $match = $d['responseData']['match'] ?? '-';
    return "HTTP $code | json {$d['responseStatus']} | match $match | $text";
}
foreach ($tests as [$region, $lang, $word, $english]) {
    echo str_pad("$region", 11) . "\n";
    echo "  $lang|en  '$word'  -> " . ask($word, "$lang|en") . "\n";   // what our form will do
    echo "  en|$lang  '$english' -> " . ask($english, "en|$lang") . "\n"; // the other direction
    sleep(1);
}
