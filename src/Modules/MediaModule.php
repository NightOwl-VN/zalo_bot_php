<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk\Modules;

use ZaloBot\Sdk\Exceptions\ValidationException;
use ZaloBot\Sdk\ZaloClient;

/** Upload, retrieve, and safely download Zalo media. */
class MediaModule
{
    public function __construct(protected ZaloClient $client)
    {
    }

    public function uploadImage(string $filePath, array $options = []): array
    {
        return $this->upload($filePath, 'image', $options);
    }

    public function uploadFile(string $filePath, array $options = []): array
    {
        return $this->upload($filePath, 'file', $options);
    }

    public function getMediaUrl(string $attachmentId, bool $redirect = false): ?string
    {
        $this->requireValue($attachmentId, 'attachmentId');
        $result = $this->client->get("me/media/{$attachmentId}", $redirect ? ['redirect' => 'true'] : []);
        $url = $result['url'] ?? $result['data']['url'] ?? $result['result']['url'] ?? null;
        if ($url !== null) {
            $this->validateDownloadUrl((string) $url);
        }
        return $url;
    }

    /** Download media using the injected PSR-18 client, never raw cURL. */
    public function downloadMedia(string $attachmentId, string $savePath): string
    {
        $this->requireValue($attachmentId, 'attachmentId');
        $this->requireValue($savePath, 'savePath');
        $url = $this->getMediaUrl($attachmentId, true);
        if ($url === null) {
            throw new ValidationException("No download URL for attachment {$attachmentId}", 'attachmentId');
        }
        $this->validateDownloadUrl($url);

        try {
            $response = $this->client->download($url);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                throw new ValidationException(
                    "Download failed with HTTP status {$response->getStatusCode()}",
                    'url',
                );
            }
            $stream = $response->getBody();
            $target = fopen($savePath, 'wb');
            if ($target === false) {
                throw new ValidationException("Cannot write to {$savePath}", 'savePath');
            }
            while (!$stream->eof()) {
                fwrite($target, $stream->read(8192));
            }
            fclose($target);
        } catch (ValidationException $e) {
            if (is_file($savePath)) {
                @unlink($savePath);
            }
            throw $e;
        } catch (\Throwable $e) {
            if (is_file($savePath)) {
                @unlink($savePath);
            }
            throw $e;
        }

        return $savePath;
    }

    private function upload(string $filePath, string $type, array $options = []): array
    {
        if (!is_file($filePath)) {
            throw new ValidationException("File not found: {$filePath}", 'file');
        }
        if (filesize($filePath) === 0) {
            throw new ValidationException("File is empty: {$filePath}", 'file');
        }
        $endpoint = $type === 'image' ? 'me/media/images' : 'me/media/files';
        $filename = $options['filename'] ?? basename($filePath);
        return $this->client->upload($endpoint, [
            'file' => new \CURLFile($filePath, mime_content_type($filePath) ?: 'application/octet-stream', $filename),
        ]);
    }

    private function requireValue(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new ValidationException("{$field} is required", $field);
        }
    }

    /**
     * Validate URL syntax and reject all private, loopback, link-local, and reserved targets.
     * DNS is resolved before use; every resolved IPv4/IPv6 address is checked.
     */
    private function validateDownloadUrl(string $urlString): void
    {
        $parsed = parse_url($urlString);
        if ($parsed === false || empty($parsed['host'])) {
            throw new ValidationException("Invalid URL: {$urlString}", 'url');
        }
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new ValidationException("URL must use http/https, got: {$scheme}", 'url');
        }

        $host = strtolower(rtrim($parsed['host'], '.'));
        if ($this->isPrivateIp($host) || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new ValidationException("URL must not target a private/internal host: {$host}", 'url');
        }

        // Resolve DNS and reject if any address is private (DNS rebinding defense).
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            foreach ($records as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;
                if ($address !== null && $this->isPrivateIp($address)) {
                    throw new ValidationException("URL must not target a private/internal host: {$host}", 'url');
                }
            }
        }
    }

    /**
     * Determine if a hostname/IP is a private, loopback, or reserved address.
     * Handles standard IPv4/IPv6, hex/decimal/octal IPs, IPv6-mapped IPv4,
     * link-local, ULA, and unspecified addresses.
     */
    private function isPrivateIp(string $host): bool
    {
        $host = trim($host, '[]');

        // Pure numeric IP hostname: 2130706433, 0x7f000001, 017700000001
        if (preg_match('/^[0-9]{1,10}$/', $host)) {
            return $this->checkPrivateIpv4(long2ip((int) $host));
        }
        if (preg_match('/^0x[0-9a-f]+$/i', $host)) {
            return $this->checkPrivateIpv4(long2ip((int) hexdec(substr($host, 2))));
        }
        if (preg_match('/^0[0-7]+$/', $host)) {
            return $this->checkPrivateIpv4(long2ip((int) octdec($host)));
        }

        // Standard dotted IPv4
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $this->checkPrivateIpv4($host);
        }

        // Standard IPv6
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $this->checkPrivateIpv6($host);
        }

        // Dotted 4-part numeric host with non-standard notation (octal/hex parts),
        // e.g. 0177.0.0.1 or 0x7f.0.0.1. Standard dotted-quad IPv4 was already
        // handled above, so anything numeric here is suspect — check BOTH the
        // plain-decimal and the octal/hex interpretations; reject if either is private.
        if (preg_match('/^(?:[0-9xX]+\.){3}[0-9xX]+$/', $host)
            || preg_match('/^(?:[0-9a-fx]+\.){3}[0-9a-fx]+$/i', $host)
        ) {
            $parts = explode('.', $host);
            if (count($parts) === 4) {
                foreach ([$this->ipFromParts($parts, false), $this->ipFromParts($parts, true)] as $candidate) {
                    if ($candidate !== null && $this->checkPrivateIpv4($candidate)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Rebuild a dotted-quad IPv4 string from host parts, interpreting each part
     * as decimal (strict) or octal/hex (loose). Returns null when not representable.
     */
    private function ipFromParts(array $parts, bool $loose): ?string
    {
        $bytes = [];
        foreach ($parts as $part) {
            $value = $loose
                ? (str_starts_with($part, '0x') || str_starts_with($part, '0X')
                    ? hexdec($part)
                    : octdec(ltrim($part, '0') === '' ? '0' : $part))
                : (int) $part;
            if ($value < 0 || $value > 255) {
                return null;
            }
            $bytes[] = (int) $value;
        }
        return implode('.', $bytes);
    }

    private function checkPrivateIpv4(string $host): bool
    {
        $ip = ip2long($host);
        if ($ip === false) {
            return true;
        }
        $ip = sprintf('%u', $ip);
        // 0.0.0.0/8, 10.0.0.0/8, 100.64.0.0/10, 127.0.0.0/8,
        // 169.254.0.0/16, 172.16.0.0/12, 192.168.0.0/16
        foreach ([['0', '16777215'], ['167772160', '184549375'], ['2147483648', '2148136959'], ['2130706432', '2147483647'], ['2851995648', '2852061183'], ['2886729728', '2887778303'], ['3232235520', '3232301055']] as [$low, $high]) {
            if ((int) $ip >= (int) $low && (int) $ip <= (int) $high) {
                return true;
            }
        }
        return false;
    }

    private function checkPrivateIpv6(string $host): bool
    {
        $packed = inet_pton($host);
        if ($packed === false) {
            return true;
        }
        // ::1, unspecified (::), ULA (fc00::/7), link-local (fe80::/10), IPv4-mapped
        return $host === '::1'
            || ($packed[0] === "\0" && $packed[1] === "\0")
            || (($packed[0] & "\xfc") === "\xfc")
            || ($packed[0] === "\xfe" && ($packed[1] & "\xc0") === "\x80")
            || (substr($packed, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff"
                && $this->checkPrivateIpv4((string) inet_ntop(substr($packed, 12))));
    }
}
