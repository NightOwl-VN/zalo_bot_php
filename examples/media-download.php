<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ZaloBot\Sdk\ZaloBot;
use ZaloBot\Sdk\Exceptions\ZaloBotException;

$bot = ZaloBot::fromEnv();

/**
 * Example: download media to local disk.
 * Reference: https://bot.zapps.me/docs/apis/media/
 */

// 1. Retrieve a media URL by attachment ID (non-redirect mode, safe default)
$attachmentId = getenv('ZALO_BOT_ATTACHMENT_ID') ?: 'attachment_id_here';
try {
    $url = $bot->media->getMediaUrl($attachmentId, redirect: false);
    echo "Media URL: {$url}" . PHP_EOL;
} catch (ZaloBotException $e) {
    fwrite(STDERR, 'Failed to retrieve media URL: ' . $e->getMessage() . PHP_EOL);
}

// 2. Download to a file (redirect mode fetches the content directly)
$savePath = sys_get_temp_dir() . '/downloaded-file';
try {
    $saved = $bot->media->downloadMedia($attachmentId, $savePath);
    echo "Saved to: {$saved}" . PHP_EOL;
    echo "File size: " . filesize($saved) . " bytes" . PHP_EOL;
} catch (ZaloBotException $e) {
    fwrite(STDERR, 'Download failed: ' . $e->getMessage() . PHP_EOL);
}
