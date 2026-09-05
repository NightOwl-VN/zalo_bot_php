<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use ZaloBot\Sdk\Exceptions\ValidationException;
use ZaloBot\Sdk\Modules\MediaModule;
use ZaloBot\Sdk\ZaloClient;

final class MediaModuleTest extends TestCase
{
    private const TOKEN = '123456789:abc-def-ghi-jkl';

    private function client(array $responses = []): ZaloClient
    {
        $mock = MockHttpClient::sequence($responses);
        return new ZaloClient(self::TOKEN, httpClient: $mock);
    }

    private static function okResponse(string $body = '{}'): \Psr\Http\Message\ResponseInterface
    {
        return MockHttpClient::response($body);
    }

    // ── Upload validation ──────────────────────────────────────

    public function testUploadImageRejectsMissingFile(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('File not found');

        $client = new MediaModule($this->client());
        $client->uploadImage('/nonexistent/file.png');
    }

    public function testUploadFileRejectsMissingFile(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('File not found');

        $client = new MediaModule($this->client());
        $client->uploadFile('/nonexistent/file.bin');
    }

    // ── getMediaUrl validation ─────────────────────────────────

    public function testGetMediaUrlRejectsEmptyAttachmentId(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('attachmentId is required');

        $client = new MediaModule($this->client());
        $client->getMediaUrl('');
    }

    public function testGetMediaUrlReturnsUrlFromResponse(): void
    {
        $mediaModule = new MediaModule($this->client([
            self::okResponse(json_encode([
                'ok' => true,
                'result' => ['url' => 'https://cdn.zalo.com/test.png'],
            ])),
        ]));

        $url = $mediaModule->getMediaUrl('att-123');

        $this->assertSame('https://cdn.zalo.com/test.png', $url);
    }

    public function testGetMediaUrlReturnsNullWhenNoUrlInResponse(): void
    {
        $mediaModule = new MediaModule($this->client([
            self::okResponse(json_encode([
                'ok' => true,
                'result' => [],
            ])),
        ]));

        $this->assertNull($mediaModule->getMediaUrl('att-123'));
    }

    public function testGetMediaUrlRejectsPrivateUrl(): void
    {
        $mediaModule = new MediaModule($this->client([
            self::okResponse(json_encode([
                'ok' => true,
                'result' => ['url' => 'http://127.0.0.1/admin'],
            ])),
        ]));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('private/internal host');

        $mediaModule->getMediaUrl('att-123');
    }

    // ── SSRF protection ───────────────────────────────────────

    /** @dataProvider ssrfUrlProvider */
    public function testSsrfProtectionRejectsPrivateUrls(string $url): void
    {
        $mediaModule = new MediaModule($this->client([
            self::okResponse(json_encode([
                'ok' => true,
                'result' => ['url' => $url],
            ])),
        ]));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('private/internal host');

        $mediaModule->getMediaUrl('att-123');
    }

    /** @return array<string, array{string}> */
    public static function ssrfUrlProvider(): array
    {
        return [
            'loopback 127.x'      => ['http://127.0.0.1/secret'],
            'loopback 127.x long' => ['http://127.0.0.1:8080/secret'],
            '10.x network'        => ['http://10.0.0.1/internal'],
            '172.16 network'      => ['http://172.16.0.1/internal'],
            '192.168 network'     => ['http://192.168.1.1/admin'],
            '169.254 link-local'  => ['http://169.254.169.254/metadata'],
            '0.x network'         => ['http://0.0.0.0/secret'],
            'IPv6 loopback'       => ['http://[::1]/secret'],
            'IPv6 mapped v4 loopback' => ['http://[::ffff:127.0.0.1]/secret'],
            'localhost'           => ['http://localhost/admin'],
            'decimal IP 127.0.0.1' => ['http://2130706433/admin'],
            'hex IP 127.0.0.1'    => ['http://0x7f000001/admin'],
            'octal IP'            => ['http://0177.0.0.1/admin'],
        ];
    }

    /** @dataProvider ssrfUrlProvider */
    public function testSsrfProtectionRejectsUrlsFromGetMediaUrl(string $url): void
    {
        $mediaModule = new MediaModule($this->client([
            self::okResponse(json_encode([
                'ok' => true,
                'result' => ['url' => $url],
            ])),
        ]));

        $this->expectException(ValidationException::class);
        $mediaModule->getMediaUrl('att-123');
    }

    public function testGetMediaUrlAllowsPublicUrls(): void
    {
        $mediaModule = new MediaModule($this->client([
            self::okResponse(json_encode([
                'ok' => true,
                'result' => ['url' => 'https://cdn.zalo.com/image.png'],
            ])),
        ]));

        $url = $mediaModule->getMediaUrl('att-123');
        $this->assertSame('https://cdn.zalo.com/image.png', $url);
    }

    public function testGetMediaUrlRejectsNonHttpProtocol(): void
    {
        $mediaModule = new MediaModule($this->client([
            self::okResponse(json_encode([
                'ok' => true,
                'result' => ['url' => 'ftp://files.example.com/test.bin'],
            ])),
        ]));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must use http/https');

        $mediaModule->getMediaUrl('att-123');
    }

    public function testGetMediaUrlRejectsInvalidUrl(): void
    {
        $mediaModule = new MediaModule($this->client([
            self::okResponse(json_encode([
                'ok' => true,
                'result' => ['url' => 'not-a-url'],
            ])),
        ]));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid URL');

        $mediaModule->getMediaUrl('att-123');
    }

    // ── downloadMedia validation ───────────────────────────────

    public function testDownloadMediaRejectsEmptyAttachmentId(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('attachmentId is required');

        $mediaModule = new MediaModule($this->client());
        $mediaModule->downloadMedia('', '/tmp/test.png');
    }

    public function testDownloadMediaRejectsEmptySavePath(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('savePath is required');

        $mediaModule = new MediaModule($this->client());
        $mediaModule->downloadMedia('att-123', '  ');
    }

    public function testDownloadMediaWritesFileFromPublicUrl(): void
    {
        $savePath = tempnam(sys_get_temp_dir(), 'dl-test') . '-download.png';
        @unlink($savePath);

        $mock = MockHttpClient::sequence([
            MockHttpClient::response(json_encode([
                'ok' => true,
                'result' => ['url' => 'https://example.com/image.png'],
            ])),
            MockHttpClient::response('binary-image-content', 200),
        ]);
        $client = new ZaloClient(self::TOKEN, httpClient: $mock);
        $mediaModule = new MediaModule($client);

        $result = $mediaModule->downloadMedia('att-123', $savePath);

        $this->assertSame($savePath, $result);
        $this->assertFileExists($savePath);
        $this->assertSame('binary-image-content', (string) file_get_contents($savePath));
        $this->assertSame(2, $mock->getCallCount());
        $this->assertSame('https://example.com/image.png', $mock->requests[1]['uri']);

        unlink($savePath);
    }

    public function testDownloadMediaRejectsPrivateUrlDuringDownload(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('private/internal host');

        $mock = MockHttpClient::sequence([
            MockHttpClient::response(json_encode([
                'ok' => true,
                'result' => ['url' => 'http://192.168.1.1/admin'],
            ])),
        ]);
        $client = new ZaloClient(self::TOKEN, httpClient: $mock);
        (new MediaModule($client))->downloadMedia('att-123', '/tmp/test.bin');
    }

    // ── downloadMedia partial-file cleanup ─────────────────────

    /**
     * P0 regression: a non-2xx status during download must not leave a
     * partial/garbage file behind on disk.
     */
    public function testDownloadMediaRemovesPartialFileOnBadHttpStatus(): void
    {
        $savePath = tempnam(sys_get_temp_dir(), 'dl-cleanup') . '-bad-status.png';
        @unlink($savePath);

        $mock = MockHttpClient::sequence([
            MockHttpClient::response(json_encode([
                'ok' => true,
                'result' => ['url' => 'https://example.com/image.png'],
            ])),
            MockHttpClient::response('partial-content', 404),
        ]);
        $client = new ZaloClient(self::TOKEN, httpClient: $mock);
        $module = new MediaModule($client);

        try {
            $module->downloadMedia('att-123', $savePath);
            $this->fail('Expected exception on 404 download');
        } catch (\Throwable $e) {
            $this->assertFileDoesNotExist($savePath, 'Partial file must be removed after failure');
        }
    }

    /**
     * P0 regression: a transport failure mid-download must not leave a
     * partial file behind on disk.
     */
    public function testDownloadMediaRemovesPartialFileOnTransportFailure(): void
    {
        $savePath = tempnam(sys_get_temp_dir(), 'dl-cleanup') . '-transport.png';
        @unlink($savePath);

        $throwConnect = new \GuzzleHttp\Exception\ConnectException(
            'Connection reset mid-transfer',
            new \GuzzleHttp\Psr7\Request('GET', 'test'),
        );
        $mock = MockHttpClient::sequenceWithFailures([
            ['response', MockHttpClient::response(json_encode([
                'ok' => true,
                'result' => ['url' => 'https://example.com/image.png'],
            ]))],
            ['throw', $throwConnect],
        ]);
        $client = new ZaloClient(self::TOKEN, httpClient: $mock);
        $module = new MediaModule($client);

        try {
            $module->downloadMedia('att-123', $savePath);
            $this->fail('Expected NetworkException');
        } catch (\ZaloBot\Sdk\Exceptions\NetworkException $e) {
            $this->assertFileDoesNotExist($savePath, 'Partial file must be removed after transport failure');
        }
    }

    /**
     * An unwritable target directory must fail fast and leave nothing behind.
     */
    public function testDownloadMediaRejectsUnwritablePath(): void
    {
        $mock = MockHttpClient::sequence([
            MockHttpClient::response(json_encode([
                'ok' => true,
                'result' => ['url' => 'https://example.com/image.png'],
            ])),
        ]);
        $client = new ZaloClient(self::TOKEN, httpClient: $mock);

        $this->expectException(\ZaloBot\Sdk\Exceptions\ValidationException::class);
        (new MediaModule($client))->downloadMedia('att-123', '/nonexistent-dir/sub/image.png');
    }

    // ── ZaloClient upload uses injected client ─────────────────

    public function testUploadDelegatesToClient(): void
    {
        $capturedUri = null;
        $mock = MockHttpClient::handler(function ($request) use (&$capturedUri) {
            $capturedUri = (string) $request->getUri();
            return MockHttpClient::response(json_encode([
                'ok' => true,
                'result' => ['url' => 'https://cdn.zalo.com/file.png'],
            ]));
        });

        $client = new ZaloClient(self::TOKEN, httpClient: $mock);

        $tmpFile = tempnam(sys_get_temp_dir(), 'up-test');
        file_put_contents($tmpFile, 'file-contents');

        $client->upload('me/media/images', [
            'file' => new \CURLFile($tmpFile, 'text/plain', 'test.txt'),
        ]);

        $this->assertStringContainsString('/bot' . self::TOKEN . '/me/media/images', $capturedUri);
        unlink($tmpFile);
    }
}
