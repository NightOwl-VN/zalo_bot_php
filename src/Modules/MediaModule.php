<?php

declare(strict_types=1);

namespace ZaloBot\Sdk\Modules;

use ZaloBot\Sdk\ZaloClient;
use ZaloBot\Sdk\Exceptions\ValidationException;

/**
 * Media module - Upload and manage media files.
 */
class MediaModule
{
    /** SSRF protection: patterns that block private/internal hostnames. */
    private const PRIVATE_IP_PATTERNS = [
        '/^127\./',
        '/^0\./',
        '/^10\./',
        '/^172\.(1[6-9]|2\d|3[01])\./',
        '/^192\.168\./',
        '/^169\.254\./',
        '/^localhost$/i',
        '/^::1$/i',
    ];

    public function __construct(protected ZaloClient $client)
    {
    }

    /**
     * Upload an image file.
     *
     * @param string $filePath Local file path
     */
    public function uploadImage(string $filePath, array $options = []): array
    {
        return $this->upload($filePath, 'image', $options);
    }

    /**
     * Upload a file.
     *
     * @param string $filePath Local file path
     */
    public function uploadFile(string $filePath, array $options = []): array
    {
        return $this->upload($filePath, 'file', $options);
    }

    /**
     * Get media URL by attachment ID.
     */
    public function getMediaUrl(string $attachmentId, bool $redirect = false): ?string
    {
        if (trim($attachmentId) === '') {
            throw new ValidationException('attachmentId is required', 'attachmentId');
        }

        $params = $redirect ? ['redirect' => 'true'] : [];
        $result = $this->client->get("me/media/{$attachmentId}", $params);

        $url = $result['url'] ?? $result['data']['url'] ?? null;

        if ($url !== null) {
            $this->validateDownloadUrl($url);
        }

        return $url;
    }

    /**
     * Download media file to local path.
     */
    public function downloadMedia(string $attachmentId, string $savePath): string
    {
        if (trim($attachmentId) === '') {
            throw new ValidationException('attachmentId is required', 'attachmentId');
        }
        if (trim($savePath) === '') {
            throw new ValidationException('savePath is required', 'savePath');
        }

        $url = $this->getMediaUrl($attachmentId, true);
        if ($url === null) {
            throw new ValidationException("No download URL for attachment {$attachmentId}", 'attachmentId');
        }

        $this->validateDownloadUrl($url);

        $fp = fopen($savePath, 'w');
        if ($fp === false) {
            throw new ValidationException("Cannot write to {$savePath}", 'savePath');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($error) {
            @unlink($savePath);
            throw new ValidationException("Download failed: {$error}", 'url');
        }

        return $savePath;
    }

    // ─────────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────────

    private function upload(string $filePath, string $type, array $options = []): array
    {
        if (!file_exists($filePath)) {
            throw new ValidationException("File not found: {$filePath}", 'file');
        }
        if (filesize($filePath) === 0) {
            throw new ValidationException("File is empty: {$filePath}", 'file');
        }

        $endpoint = $type === 'image' ? 'me/media/images' : 'me/media/files';
        $filename = basename($filePath);

        $postFields = [
            'file' => new \CURLFile($filePath, mime_content_type($filePath) ?: 'application/octet-stream', $filename),
        ];

        return $this->client->upload($endpoint, $postFields);
    }

    private function validateDownloadUrl(string $urlString): void
    {
        $parsed = parse_url($urlString);
        if ($parsed === false || empty($parsed['host'])) {
            throw new ValidationException("Invalid URL: {$urlString}", 'url');
        }

        $protocol = $parsed['scheme'] ?? '';
        if (!in_array($protocol, ['http', 'https'], true)) {
            throw new ValidationException("URL must use http/https, got: {$protocol}", 'url');
        }

        $host = strtolower($parsed['host']);
        foreach (self::PRIVATE_IP_PATTERNS as $pattern) {
            if (preg_match($pattern, $host)) {
                throw new ValidationException("URL must not target a private/internal host: {$host}", 'url');
            }
        }
    }
}
