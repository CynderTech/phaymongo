<?php

/**
 * @runInSeparateProcess
 */

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Paymongo\Phaymongo\PaymongoClient;
use Paymongo\Phaymongo\PaymongoException;

it('can create a request without payload', function () {
    $client = new PaymongoClient('a', 'b');

    $request = $client->createRequest('GET', '/');

    expect($request->hasHeader('Authorization'))->toBeTrue();
    expect($request->getHeader('Authorization')[0])->toBe('Basic ' . base64_encode('b'));
    expect((string) $request->getBody())->toBe('');
});

it('can create a request with payload', function () {
    $client = new PaymongoClient('a', 'b');

    $request = $client->createRequest('POST', '/', ['foo' => 'bar']);

    expect($request->hasHeader('Authorization'))->toBeTrue();
    expect($request->getHeader('Authorization')[0])->toBe('Basic ' . base64_encode('b'));
    expect((string) $request->getBody())->toBe('{"foo":"bar"}');
});

it('can send a request and return unwrapped data by default', function () {
    $mockHandler = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], '{"data":"bar"}'),
    ]);

    $handlerStack = HandlerStack::create($mockHandler);
    $client = new PaymongoClient('a', 'b', [], ['handler' => $handlerStack]);

    $request = $client->createRequest('GET', '/');
    $response = $client->sendRequest($request);

    expect($response)->toBe('bar');
});

it('can send a request and return wrapped data', function () {
    $mockHandler = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], '{"data":"bar"}'),
    ]);

    $handlerStack = HandlerStack::create($mockHandler);
    $client = new PaymongoClient('a', 'b', ['unwrap' => false], ['handler' => $handlerStack]);

    $request = $client->createRequest('GET', '/');
    $response = $client->sendRequest($request);

    expect($response)->toBe(['data' => 'bar']);
});

it('can send a request and return the HTTP message instance', function () {
    $mockHandler = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], '{"data":"bar"}'),
    ]);

    $handlerStack = HandlerStack::create($mockHandler);
    $client = new PaymongoClient('a', 'b', ['return_response' => true], ['handler' => $handlerStack]);

    $request = $client->createRequest('GET', '/');
    $response = $client->sendRequest($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getBody()->__toString())->toBe('{"data":"bar"}');
});

it('can send a request and throw a paymongo exception', function () {
    $mockHandler = new MockHandler([
        new ClientException('Some error happened', new Request('GET', '/'), new Response(400, ['Content-Type' => 'application/json'], '{"errors":[{"detail": "some error"}]}')),
    ]);

    $handlerStack = HandlerStack::create($mockHandler);
    $client = new PaymongoClient('a', 'b', [], ['handler' => $handlerStack]);

    $request = $client->createRequest('GET', '/');
    try {
        $client->sendRequest($request);
    } catch (PaymongoException $e) {
        $formattedMessages = $e->format_errors();

        expect($formattedMessages[0])->toBe('some error');

        throw $e;
    }
})->throws(PaymongoException::class);

it('can send a request and throw a client exception', function () {
    $mockHandler = new MockHandler([
        new ClientException('Some error happened', new Request('GET', '/'), new Response(400, ['Content-Type' => 'application/json'], '{}')),
    ]);

    $handlerStack = HandlerStack::create($mockHandler);
    $client = new PaymongoClient('a', 'b', [], ['handler' => $handlerStack]);

    $request = $client->createRequest('GET', '/');
    $client->sendRequest($request);
})->throws(ClientException::class);

it('can send a successful request and throw an error from the API', function () {
    $mockHandler = new MockHandler([
        new Response(400, ['Content-Type' => 'application/json'], '{"errors":[{"detail": "some error"}]}'),
    ]);

    $handlerStack = HandlerStack::create($mockHandler);
    $client = new PaymongoClient('a', 'b', [], ['handler' => $handlerStack]);

    $request = $client->createRequest('GET', '/');
    $client->sendRequest($request);
})->throws(PaymongoException::class);

it('can create a resource', function () {
    $client = \Mockery::mock('Paymongo\Phaymongo\PaymongoClient')->makePartial();

    $payload = ['data' => 'bar'];

    $client->shouldReceive('createRequest')
        ->withArgs(['POST', '', $payload, false])
        ->atLeast()
        ->times(1)
        ->andReturn(true);

    $client->shouldReceive('sendRequest')
        ->withArgs([true, []])
        ->atLeast()
        ->times(1);

    $client->createResource($payload);
});

it('can retrieve a resource by ID', function () {
    $client = \Mockery::mock('Paymongo\Phaymongo\PaymongoClient')->makePartial();

    $id = 1;

    $client->shouldReceive('createRequest')
        ->withArgs(['GET', '/' . $id])
        ->atLeast()
        ->times(1)
        ->andReturn(true);

    $client->shouldReceive('sendRequest')
        ->withArgs([true, []])
        ->atLeast()
        ->times(1);

    $client->retrieveResourceById($id);
});

it('rejects unknown client options', function () {
    new PaymongoClient('a', 'b', ['secret_key' => 'attacker']);
})->throws(InvalidArgumentException::class);

it('url-encodes resource ids so they cannot alter the path', function () {
    $client = new class('a', 'b') extends PaymongoClient {
        protected $base_resource_key = 'payments';

        public function path(...$segments)
        {
            return $this->resourcePath(...$segments);
        }
    };

    expect($client->path('payments', '../refunds?x=1#y'))->toBe('payments/..%2Frefunds%3Fx%3D1%23y');
});

it('sends requests to the /v1 API path', function () {
    $history = [];
    $handlerStack = HandlerStack::create(new MockHandler([
        new Response(200, [], '{"data":"ok"}'),
    ]));
    $handlerStack->push(\GuzzleHttp\Middleware::history($history));

    $client = new PaymongoClient('a', 'b', [], ['handler' => $handlerStack]);
    $client->sendRequest($client->createRequest('GET', 'payments/pay_1'));

    expect((string) $history[0]['request']->getUri())->toBe('https://api.paymongo.com/v1/payments/pay_1');
});

it('throws a client exception for non-json error bodies', function () {
    $handlerStack = HandlerStack::create(new MockHandler([
        new Response(502, [], '<html>Bad Gateway</html>'),
    ]));
    $client = new PaymongoClient('a', 'b', [], ['handler' => $handlerStack]);

    $client->sendRequest($client->createRequest('GET', 'payments'));
})->throws(\GuzzleHttp\Exception\ServerException::class);

it('includes the status code and previous exception on paymongo errors', function () {
    $handlerStack = HandlerStack::create(new MockHandler([
        new Response(422, [], '{"errors":[{"detail":"bad"}]}'),
    ]));
    $client = new PaymongoClient('a', 'b', [], ['handler' => $handlerStack]);

    try {
        $client->sendRequest($client->createRequest('GET', 'payments'));
    } catch (PaymongoException $e) {
        expect($e->getCode())->toBe(422);
        expect($e->getPrevious())->not->toBeNull();
        expect($e->getErrors())->toBe([['detail' => 'bad']]);
        return;
    }

    throw new \Exception('Expected PaymongoException');
});

it('masks api keys in debug output', function () {
    $client = new PaymongoClient('pk_live_secret', 'sk_live_secret');

    ob_start();
    var_dump($client);
    print_r($client);
    $dump = ob_get_clean();

    expect($dump)->not->toContain('sk_live_secret')->not->toContain('pk_live_secret');
});

it('sends float amounts as exact integer centavos', function () {
    $history = [];
    $handlerStack = HandlerStack::create(new MockHandler([
        new Response(200, [], '{"data":{}}'),
    ]));
    $handlerStack->push(\GuzzleHttp\Middleware::history($history));

    $refund = new \Paymongo\Phaymongo\Refund('a', 'b', [], ['handler' => $handlerStack]);
    $refund->create(19.99, 'pay_1', 'others');

    $body = json_decode((string) $history[0]['request']->getBody(), true);
    expect($body['data']['attributes']['amount'])->toBe(1999);
});
