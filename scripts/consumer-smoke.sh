#!/usr/bin/env bash
#
# Consumer smoke test — proves a fresh consumer project can install this SDK
# via a path repository, autoload it, and construct a bot with a custom
# PSR-18 client without calling any network.
set -euo pipefail

SDK_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT

echo "== Consumer smoke test =="
echo "SDK:      $SDK_DIR"
echo "Consumer: $WORK_DIR"

# Create a consumer composer.json that requires the SDK as a path repo.
cat > "$WORK_DIR/composer.json" <<JSON
{
    "name": "acme/consumer-smoke",
    "repositories": [
        {
            "type": "path",
            "url": "$(echo "$SDK_DIR" | sed 's/"/\\"/g')",
            "options": { "symlink": false }
        }
    ],
    "require": {
        "hoangkhacphuc/zalobot-sdk": "@dev"
    },
    "config": { "allow-plugins": false },
    "minimum-stability": "dev",
    "prefer-stable": true
}
JSON

pushd "$WORK_DIR" >/dev/null

# Install runtime deps (no --no-dev since Guzzle is a runtime requirement of ZaloBot).
if ! composer install --no-interaction --prefer-dist 2>/dev/null; then
    composer install --no-interaction
fi

# A small standalone mock PSR-18 + PSR-7 that does not require Guzzle.
cat > MockClient.php <<'MOCK'
<?php
declare(strict_types=1);
namespace SmokeTest;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class MockClient implements ClientInterface
{
    public array $requests = [];

    public function __construct(
        private string $body = '{"ok":true,"result":{"id":"mock-1"}}',
        private int    $status = 200,
        private ?\Throwable $throw = null,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($this->throw !== null) { throw $this->throw; }
        $this->getBodyFrom($request);
        $this->requests[] = ['method' => $request->getMethod(), 'uri' => (string) $request->getUri()];
        return new MockResponse($this->status, $this->body);
    }

    private function getBodyFrom(RequestInterface $request): void
    {
        $b = $request->getBody();
        if ($b && !$b->eof()) { $b->getContents(); }
    }
}

final class MockResponse implements ResponseInterface
{
    public function __construct(private int $status, private string $body) {}
    public function getProtocolVersion(): string { return '1.1'; }
    public function withProtocolVersion($v): static { return $this; }
    public function getHeaders(): array { return ['content-type' => ['application/json']]; }
    public function hasHeader($n): bool { return $n === 'content-type'; }
    public function getHeader($n): array { return $this->hasHeader($n) ? ['application/json'] : []; }
    public function getHeaderLine($n): string { return $this->hasHeader($n) ? 'application/json' : ''; }
    public function withHeader($n, $v): static { return $this; }
    public function withAddedHeader($n, $v): static { return $this; }
    public function withoutHeader($n): static { return $this; }
    public function getBody(): StreamInterface { return new MockStream($this->body); }
    public function withBody(StreamInterface $b): static { return $this; }
    public function getStatusCode(): int { return $this->status; }
    public function withStatus($code, $reason = ''): static { return $this; }
    public function getReasonPhrase(): string { return ''; }
}

final class MockStream implements StreamInterface
{
    private string $data;
    private int $pos = 0;
    public function __construct(string $d) { $this->data = $d; }
    public function __toString(): string { return $this->data; }
    public function close(): void { $this->pos = 0; $this->data = ''; }
    public function detach() { return null; }
    public function getSize(): ?int { return strlen($this->data); }
    public function tell(): int { return $this->pos; }
    public function eof(): bool { return $this->pos >= strlen($this->data); }
    public function isSeekable(): bool { return true; }
    public function seek($offset, $w = SEEK_SET): void { $this->pos = $offset; }
    public function rewind(): void { $this->pos = 0; }
    public function isWritable(): bool { return false; }
    public function write($d): int { return 0; }
    public function isReadable(): bool { return true; }
    public function read($len): string { return ''; }
    public function getContents(): string { return $this->data; }
    public function getMetadata($k = null) { return null; }
}
MOCK

cat > smoke.php <<'PHP'
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/MockClient.php';

use SmokeTest\MockClient;
use ZaloBot\Sdk\Config;
use ZaloBot\Sdk\ZaloBot;
use ZaloBot\Sdk\ZaloClient;
use ZaloBot\Sdk\Modules\MessageModule;
use ZaloBot\Sdk\Modules\UserModule;
use ZaloBot\Sdk\Modules\MediaModule;
use ZaloBot\Sdk\Modules\WebhookModule;
use ZaloBot\Sdk\WebhookEvent;

// 1) ZaloBot with custom PSR-18 client — exercises the full constructor chain.
$mock = new MockClient();
$bot  = new ZaloBot(['botToken' => '123456789:smoke'], $mock);
$result = $bot->message->getMe();
assert($result['result']['id'] === 'mock-1', 'getMe must return mock data');
assert(count($mock->requests) === 1, 'mock must have captured exactly one request');

// 2) Direct ZaloClient injection via named arg.
$client   = new ZaloClient('123456789:x', httpClient: $mock, retryDelayMs: 0);
$message  = new MessageModule($client);
$user     = new UserModule($client);
$media    = new MediaModule($client);
$webhook  = new WebhookModule(secretKey: '12345678');

// 3) Webhook parseEvent returns plain array (backward compat).
$eventArray = $webhook->parseEvent([
    'result' => [
        'event_name' => 'message.text.received',
        'message'    => ['text' => 'hello', 'from' => ['id' => 'u1'], 'date' => 1700000000000, 'message_id' => 'm1'],
    ],
]);
assert(is_array($eventArray), 'parseEvent must return array');
assert($eventArray['event'] === 'user_text', 'event normalization');
assert($eventArray['userId'] === 'u1', 'userId extracted');

// 4) Webhook parseEventDto returns typed DTO with readonly props.
$dto = $webhook->parseEventDto([
    'result' => [
        'event_name' => 'message.image.received',
        'message'    => ['photo' => 'https://cdn.example.com/x.png', 'from' => ['id' => 'u2'], 'date' => 1700000001000, 'message_id' => 'm2'],
    ],
]);
assert($dto instanceof WebhookEvent, 'parseEventDto returns WebhookEvent');
assert($dto->isImage(), 'dto.isImage');
assert($dto->event === 'user_image');
assert($dto->userId === 'u2');
assert($dto['event'] === 'user_image', 'ArrayAccess works');

// 5) Config from env override must work.
$config = new Config(
    botToken: 'env-override',
    secretKey: 'supersecretkey',
    timeout: 5000,
    maxRetries: 0,
);
assert($config->hasSecretKey(), 'Config::hasSecretKey');
assert($config->toArray()['timeout'] === 5000);

echo "PASS: consumer autoloading, constructor injection, parseEvent, parseEventDto, Config all OK\n";
PHP

php smoke.php

popd >/dev/null
echo "== Consumer smoke test OK =="
