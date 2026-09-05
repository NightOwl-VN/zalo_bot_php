<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use ZaloBot\Sdk\Exceptions\ApiException;
use ZaloBot\Sdk\Exceptions\AuthException;
use ZaloBot\Sdk\Exceptions\NetworkException;
use ZaloBot\Sdk\Exceptions\RateLimitException;
use ZaloBot\Sdk\Exceptions\TimeoutException;
use ZaloBot\Sdk\Exceptions\ValidationException;
use ZaloBot\Sdk\ZaloClient;

final class ZaloClientTest extends TestCase
{
    private const TOKEN = '123456789:abc-def-ghi-jkl';

    public function testAcceptsInjectedPsr18HttpClient(): void
    {
        $mock = MockHttpClient::of(json_encode(['ok' => true, 'result' => ['id' => 'bot-1']]));
        $client = new ZaloClient(self::TOKEN, httpClient: $mock);

        $result = $client->get('getMe');

        $this->assertSame('bot-1', $result['result']['id']);
        $this->assertSame(1, $mock->getCallCount());
        $this->assertSame('GET', $mock->requests[0]['method']);
        $this->assertStringContainsString('/bot' . self::TOKEN . '/getMe', $mock->requests[0]['uri']);
    }

    public function testGetSendsQueryParams(): void
    {
        $mock = MockHttpClient::of(json_encode(['ok' => true, 'result' => []]));
        $client = new ZaloClient(self::TOKEN, httpClient: $mock);

        $client->get('me/followers', ['limit' => 25, 'cursor' => 'abc']);

        $this->assertStringContainsString('limit=25', $mock->requests[0]['uri']);
        $this->assertStringContainsString('cursor=abc', $mock->requests[0]['uri']);
    }

    public function testPostSendsJsonBody(): void
    {
        $captured = null;
        $mock = MockHttpClient::handler(static function ($request) use (&$captured) {
            $captured = $request;
            return MockHttpClient::response(json_encode(['ok' => true, 'result' => []]));
        });
        $client = new ZaloClient(self::TOKEN, httpClient: $mock);

        $client->post('sendMessage', ['chat_id' => 'chat-1', 'text' => 'Hello']);

        $this->assertSame('POST', $captured->getMethod());
        $this->assertSame(
            ['chat_id' => 'chat-1', 'text' => 'Hello'],
            json_decode((string) $captured->getBody(), true),
        );
        $this->assertSame('application/json', $captured->getHeaderLine('Content-Type'));
    }

    // ── Error mapping ──────────────────────────────────────────

    public function testMapsUnauthorizedToAuthException(): void
    {
        $mock = MockHttpClient::of(
            json_encode(['ok' => false, 'description' => 'Invalid token']),
            401,
        );
        $client = new ZaloClient(self::TOKEN, maxRetries: 0, httpClient: $mock);

        try {
            $client->get('getMe');
            $this->fail('Expected AuthException');
        } catch (AuthException $e) {
            $this->assertSame(401, $e->getHttpStatus());
            $this->assertSame('Invalid token', $e->getMessage());
        }
    }

    public function testMapsForbiddenToAuthException(): void
    {
        $mock = MockHttpClient::of('{}', 403);
        $client = new ZaloClient(self::TOKEN, maxRetries: 0, httpClient: $mock);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid or expired bot token');

        $client->get('getMe');
    }

    public function testMapsRateLimitToRateLimitExceptionWithRetryAfter(): void
    {
        $mock = MockHttpClient::of(
            json_encode(['ok' => false, 'description' => 'Too many requests']),
            429,
            ['Retry-After' => '7'],
        );
        $client = new ZaloClient(self::TOKEN, maxRetries: 0, httpClient: $mock);

        try {
            $client->get('getMe');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(429, $e->getHttpStatus());
            $this->assertSame(7, $e->getRetryAfter());
            $this->assertSame('Too many requests', $e->getMessage());
        }
    }

    public function testMapsClientErrorToApiException(): void
    {
        $mock = MockHttpClient::of(
            json_encode(['ok' => false, 'error_code' => 2003, 'description' => 'User not found']),
            404,
        );
        $client = new ZaloClient(self::TOKEN, maxRetries: 0, httpClient: $mock);

        try {
            $client->get('u-1');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(404, $e->getHttpStatus());
            $this->assertSame(2003, $e->getApiErrorCode());
        }
    }

    public function testMapsServerErrorsToApiException(): void
    {
        $mock = MockHttpClient::of('{}', 500);
        $client = new ZaloClient(self::TOKEN, maxRetries: 0, httpClient: $mock);

        $this->expectException(ApiException::class);
        $client->get('getMe');
    }

    public function testApiLevelErrorWithOkFalseThrowsApiException(): void
    {
        $mock = MockHttpClient::of(
            json_encode(['ok' => false, 'error_code' => 1200, 'description' => 'Chat not found']),
            200,
        );
        $client = new ZaloClient(self::TOKEN, maxRetries: 0, httpClient: $mock);

        try {
            $client->post('sendMessage', ['chat_id' => 'x', 'text' => 'hi']);
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(1200, $e->getApiErrorCode());
            $this->assertSame(200, $e->getHttpStatus());
        }
    }

    public function testNetworkFailureThrowsNetworkExceptionWithPrevious(): void
    {
        $mock = MockHttpClient::networkFailure('Connection refused');
        $client = new ZaloClient(self::TOKEN, maxRetries: 0, httpClient: $mock);

        try {
            $client->get('getMe');
            $this->fail('Expected NetworkException');
        } catch (NetworkException $e) {
            $this->assertStringContainsString('Connection refused', $e->getMessage());
            $this->assertNotNull($e->getPrevious(), 'Previous exception chain must be preserved');
        }
    }

    // ── Retry logic ──────────────────────────────────────────

    public function testRetriesOn429AndSucceeds(): void
    {
        $mock = MockHttpClient::sequence([
            MockHttpClient::response('{}', 429),
            MockHttpClient::response(json_encode(['ok' => true, 'result' => ['id' => 'bot-1']])),
        ]);
        $client = new ZaloClient(self::TOKEN, maxRetries: 1, httpClient: $mock);

        $result = $client->get('getMe');

        $this->assertSame('bot-1', $result['result']['id']);
        $this->assertSame(2, $mock->getCallCount());
    }

    /**
     * P0 regression: the retry loop previously mutated $uri in place, so a
     * retry after a 429/503 re-appended the query string (?a=1?a=1) — and a
     * GET retry would stack params from previous attempts. Each attempt
     * must send the identical, uncorrupted URI.
     */
    public function testRetryDoesNotDoubleAppendQueryParams(): void
    {
        $uris = [];
        $mock = MockHttpClient::handler(static function ($request) use (&$uris) {
            $uris[] = (string) $request->getUri();
            $count = count($uris);
            return $count < 3
                ? MockHttpClient::response('{}', 503)
                : MockHttpClient::response(json_encode(['ok' => true, 'result' => []]));
        });
        $client = new ZaloClient(self::TOKEN, maxRetries: 3, httpClient: $mock, retryDelayMs: 0);

        $client->get('getMe', ['offset' => 42, 'limit' => 10]);

        $this->assertCount(3, $uris);
        $this->assertSame($uris[0], $uris[1], 'Attempt 2 must send the same URI as attempt 1');
        $this->assertSame($uris[0], $uris[2], 'Attempt 3 must send the same URI as attempt 1');
        $this->assertStringContainsString('offset=42', $uris[0]);
        $this->assertStringContainsString('limit=10', $uris[0]);
        $this->assertSame(1, substr_count($uris[0], '?'), 'Query string must appear exactly once');
        $this->assertSame(1, substr_count($uris[0], 'offset='));
        $this->assertSame(1, substr_count($uris[0], 'limit='));
    }

    /**
     * Same class of bug for POST: the request body is rebuilt every attempt,
     * so payload corruption from a mutated shared variable must never happen.
     */
    public function testRetrySendsIdenticalJsonBody(): void
    {
        $bodies = [];
        $mock = MockHttpClient::handler(static function ($request) use (&$bodies) {
            $bodies[] = (string) $request->getBody();
            return count($bodies) < 2
                ? MockHttpClient::response('{}', 502)
                : MockHttpClient::response(json_encode(['ok' => true, 'result' => []]));
        });
        $client = new ZaloClient(self::TOKEN, maxRetries: 2, httpClient: $mock, retryDelayMs: 0);

        $client->post('sendMessage', ['chat_id' => 'c-1', 'text' => 'hello']);

        $this->assertCount(2, $bodies);
        $this->assertSame($bodies[0], $bodies[1], 'Every retry must send an identical body');
        $this->assertSame(
            ['chat_id' => 'c-1', 'text' => 'hello'],
            json_decode($bodies[0], true),
        );
    }

    public function testRetriesOn502And503And504(): void
    {
        foreach ([502, 503, 504] as $status) {
            $mock = MockHttpClient::sequence([
                MockHttpClient::response('{}', $status),
                MockHttpClient::response(json_encode(['ok' => true, 'result' => []])),
            ]);
            $client = new ZaloClient(self::TOKEN, maxRetries: 1, httpClient: $mock);

            $client->get('getMe');

            $this->assertSame(2, $mock->getCallCount(), "Should retry on HTTP {$status}");
        }
    }

    public function testRetriesOn408RequestTimeout(): void
    {
        $mock = MockHttpClient::sequence([
            MockHttpClient::response('{}', 408),
            MockHttpClient::response(json_encode(['ok' => true, 'result' => []])),
        ]);
        $client = new ZaloClient(self::TOKEN, maxRetries: 1, httpClient: $mock);

        $client->get('getMe');

        $this->assertSame(2, $mock->getCallCount());
    }

    public function testDoesNotRetryOnClientErrors(): void
    {
        foreach ([400, 401, 403, 404, 422] as $status) {
            $mock = MockHttpClient::of('{}', $status);
            $client = new ZaloClient(self::TOKEN, maxRetries: 3, httpClient: $mock);

            try {
                $client->get('getMe');
                $this->fail("Expected exception for HTTP {$status}");
            } catch (ApiException|AuthException $e) {
                $this->assertSame(1, $mock->getCallCount(), "Must not retry on HTTP {$status}");
            }
        }
    }

    public function testExhaustedRetriesOn429ThrowsRateLimitException(): void
    {
        $mock = MockHttpClient::of('{}', 429);
        $client = new ZaloClient(self::TOKEN, maxRetries: 1, httpClient: $mock);

        $this->expectException(RateLimitException::class);
        $client->get('getMe');

        $this->assertSame(2, $mock->getCallCount());
    }

    /**
     * The PSR-18 mock throws RequestException (with a 502 response) on every
     * attempt. The client retries it (502 is transient) and, once retries are
     * exhausted, throws NetworkException with the original transport error
     * preserved as the previous exception.
     */
    public function testRetriesOnNetworkTransientsThenSucceeds(): void
    {
        // Second call succeeds after first throws network error
        $mock = MockHttpClient::clientError(502, json_encode(['ok' => true, 'result' => []]));
        $client = new ZaloClient(self::TOKEN, maxRetries: 1, httpClient: $mock);

        try {
            $client->get('getErr');
            $this->fail('Expected NetworkException after exhausting retries');
        } catch (NetworkException $e) {
            $this->assertSame(2, $mock->getCallCount(), '502 transport errors must be retried');
            $this->assertNotNull($e->getPrevious(), 'Original PSR-18 error must be chained');
        }
    }

    /**
     * A transient network failure (connection refused) followed by a healthy
     * response must recover: the retry loop treats non-timeout ConnectException
     * as transient and re-issues the request.
     */
    public function testRetriesOnTransientConnectErrorThenSucceeds(): void
    {
        $mock = MockHttpClient::sequenceWithFailures([
            ['throw', new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('GET', 'test'))],
            ['response', MockHttpClient::response(json_encode(['ok' => true, 'result' => ['id' => 'bot-9']]))],
        ]);
        $client = new ZaloClient(self::TOKEN, maxRetries: 2, httpClient: $mock, retryDelayMs: 0);

        $result = $client->get('getMe');

        $this->assertSame('bot-9', $result['result']['id']);
        $this->assertSame(2, $mock->getCallCount());
    }

    // ── Upload ───────────────────────────────────────────────

    public function testUploadSendsMultipartBody(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'upload-test');
        file_put_contents($tmpFile, 'image-data');

        $captured = null;
        $mock = MockHttpClient::handler(static function ($request) use (&$captured) {
            $captured = $request;
            return MockHttpClient::response(json_encode(['ok' => true, 'result' => ['url' => 'https://cdn.example.com/x.png']]));
        });
        $client = new ZaloClient(self::TOKEN, httpClient: $mock);

        $result = $client->upload('me/media/images', [
            'file' => new \CURLFile($tmpFile, 'image/png', 'test.png'),
        ]);

        $this->assertSame('https://cdn.example.com/x.png', $result['result']['url']);
        $this->assertStringContainsString('multipart/form-data', $captured->getHeaderLine('Content-Type'));
        $body = (string) $captured->getBody();
        $this->assertStringContainsString('name="file"', $body);
        $this->assertStringContainsString('filename="test.png"', $body);
        $this->assertStringContainsString('image-data', $body);

        unlink($tmpFile);
    }

    // ── Misc ──────────────────────────────────────────────────

    public function testUpdateBotTokenChangesRequestUrl(): void
    {
        $mock = MockHttpClient::of(json_encode(['ok' => true, 'result' => []]));
        $client = new ZaloClient(self::TOKEN, httpClient: $mock);

        $client->updateBotToken('999:new-token');
        $client->get('getMe');

        $this->assertStringContainsString('/bot999:new-token/getMe', $mock->requests[0]['uri']);
    }

    public function testUpdateBotTokenRejectsEmpty(): void
    {
        $client = new ZaloClient(self::TOKEN);

        $this->expectException(ValidationException::class);
        $client->updateBotToken('   ');
    }

    public function testNonJsonResponseReturnsRawString(): void
    {
        $mock = MockHttpClient::of('plain-text-response');
        $client = new ZaloClient(self::TOKEN, httpClient: $mock);

        $this->assertSame('plain-text-response', $client->get('getMe'));
    }

    public function testEmptyJsonObjectResponseReturnsRawString(): void
    {
        // json_decode('{}') returns [] — falls back to raw
        $mock = MockHttpClient::of('{}');
        $client = new ZaloClient(self::TOKEN, httpClient: $mock);

        $this->assertSame('{}', $client->get('getMe'));
    }

    public function testNetworkExceptionOnGenericThrowable(): void
    {
        $mock = MockHttpClient::handler(static function ($request) {
            throw new \RuntimeException('boom');
        });
        $client = new ZaloClient(self::TOKEN, maxRetries: 0, httpClient: $mock);

        $this->expectException(NetworkException::class);
        $client->get('getMe');
    }

    public function testBaseUrlTrimsTrailingSlash(): void
    {
        $mock = MockHttpClient::of(json_encode(['ok' => true, 'result' => []]));
        $client = new ZaloClient(self::TOKEN, baseURL: 'https://bot-api.zaloplatforms.com/', httpClient: $mock);

        $this->assertSame(
            'https://bot-api.zaloplatforms.com/bot' . self::TOKEN,
            $client->getRequestBaseUrl(),
        );
    }
}
