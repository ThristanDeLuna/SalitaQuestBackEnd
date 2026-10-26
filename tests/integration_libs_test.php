<?php
// Plain-PHP test for the pure parts of Mymemory_lib and Brevo_lib. No network,
// no database, no LavaLust. Run from the backend folder:
//   php tests/integration_libs_test.php
define('PREVENT_DIRECT_ACCESS', true);
function lava_instance() { return new stdClass(); } // stub: the pure methods never use it
require __DIR__ . '/../app/libraries/Mymemory_lib.php';
require __DIR__ . '/../app/libraries/Brevo_lib.php';

$passed = 0; $failed = 0;
function check($name, $got, $want) {
    global $passed, $failed;
    if ($got === $want) { $passed++; echo "  ok   $name\n"; }
    else { $failed++; echo "  FAIL $name\n       got:  " . var_export($got, true) . "\n       want: " . var_export($want, true) . "\n"; }
}

$m = new Mymemory_lib();
echo "Mymemory_lib::parse_response\n";
$good = '{"responseData":{"translatedText":"Thank you","match":0.99},"responseStatus":200}';
check('good answer',                  $m->parse_response($good, 200, 'salamat'), 'Thank you');
check('status as a string "200"',     $m->parse_response('{"responseData":{"translatedText":"Water"},"responseStatus":"200"}', 200, 'tubig'), 'Water');
check('HTTP error -> null',           $m->parse_response($good, 429, 'salamat'), null);
check('no body -> null',              $m->parse_response(null, 200, 'salamat'), null);
check('bad JSON -> null',             $m->parse_response('<html>', 200, 'salamat'), null);
check('JSON status 403 -> null',      $m->parse_response('{"responseData":{"translatedText":"x"},"responseStatus":403}', 200, 'a'), null);
check('empty text -> null',           $m->parse_response('{"responseData":{"translatedText":"  "},"responseStatus":200}', 200, 'a'), null);
check('quota warning -> null',        $m->parse_response('{"responseData":{"translatedText":"MYMEMORY WARNING: YOU USED ALL AVAILABLE FREE TRANSLATIONS"},"responseStatus":200}', 200, 'a'), null);
check('error sent as a translation -> null', $m->parse_response('{"responseData":{"translatedText":"Please, specify two different languages"},"responseStatus":200}', 200, 'tubig'), null);
check('same text back -> null',       $m->parse_response('{"responseData":{"translatedText":"Salamat"},"responseStatus":200}', 200, 'salamat'), null);
check('suggest(): unknown region',    $m->suggest('salamat', 'bisaya'), null);
check('suggest(): empty word',        $m->suggest('   ', 'cebuano'), null);
check('suggest(): word too long',     $m->suggest(str_repeat('a', 201), 'cebuano'), null);

$b = new Brevo_lib();
echo "Brevo_lib\n";
$p = $b->build_payload('noreply@example.com', 'Salita Quest', 'ana@example.com', 'Ana', 'Hi', '<p>x</p>');
check('payload sender',   $p['sender'], ['email' => 'noreply@example.com', 'name' => 'Salita Quest']);
check('payload to',       $p['to'], [['email' => 'ana@example.com', 'name' => 'Ana']]);
check('payload fields',   [$p['subject'], $p['htmlContent']], ['Hi', '<p>x</p>']);
check('empty name uses the email', $b->build_payload('a@b.co', 'S', 'ana@example.com', '', 's', 'h')['to'][0]['name'], 'ana@example.com');
check('201 + messageId = success', $b->is_success(201, '{"messageId":"<1@x>"}'), true);
check('201 without messageId',     $b->is_success(201, '{}'), false);
check('400 = failure',             $b->is_success(400, '{"code":"invalid_parameter"}'), false);
check('no body = failure',         $b->is_success(201, null), false);
$e = $b->approved_email('<script>x</script>');
check('approved email escapes the word', strpos($e['html'], '<script>') === false, true);
$r = $b->rejected_email('kaon', 'Spelling "kaon" is fine, but it already exists');
check('rejected email shows the reason', strpos($r['html'], 'already exists') !== false, true);
check('rejected email without reason has no reason line', strpos($b->rejected_email('kaon')['html'], 'Reason') === false, true);
check('send(): bad email returns false (no network)', $b->send('not-an-email', 'x', 's', 'h'), false);
check('send(): no key returns false (no network)',    $b->send('ana@example.com', 'x', 's', 'h'), false);

echo "\n$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
