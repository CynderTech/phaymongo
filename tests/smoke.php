<?php
// Dependency-light smoke test for PHP 7.4-8.1 where Pest 3 can't be installed.
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Paymongo\Phaymongo\Phaymongo;

$history = [];
$stack = HandlerStack::create(new MockHandler(array_fill(0, 6, new Response(200, [], '{"data":{"ok":true}}'))));
$stack->push(Middleware::history($history));

$client = new Phaymongo('pk', 'sk', [], ['handler' => $stack]);
$client->paymentIntent()->create(19.99, ['gcash']);
$client->paymentMethod()->retrieveById('pm_1');
$client->source()->create(10, 'gcash', 'https://a.test/ok', 'https://a.test/fail');
$client->payment()->retrieveAll(null, null, 5);
$client->refund()->create(1, 'pay_1', 'others');
$client->link()->archive('a/b');

$expected = [
    'https://api.paymongo.com/v1/payment_intents',
    'https://api.paymongo.com/v1/payment_methods/pm_1',
    'https://api.paymongo.com/v1/sources',
    'https://api.paymongo.com/v1/payments?limit=5',
    'https://api.paymongo.com/v1/refunds',
    'https://api.paymongo.com/v1/links/a%2Fb/archive',
];
foreach ($expected as $i => $uri) {
    $actual = (string) $history[$i]['request']->getUri();
    if ($actual !== $uri) {
        fwrite(STDERR, "FAIL #$i: expected $uri, got $actual\n");
        exit(1);
    }
}
echo "smoke ok on PHP " . PHP_VERSION . "\n";
